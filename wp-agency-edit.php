<?php
/**
 * Plugin Name:       WP Agency Edit
 * Plugin URI:        https://github.com/livedialai/wp-agency-edit
 * Description:       Zentrale für betreute WordPress-Websites: Websites mit Adresse und Anwendungspasswort hinterlegen, Verbindung prüfen und per KI-Chat Änderungen auf der jeweiligen Kundenseite vornehmen. Das Sprachmodell läuft hier — die Kundenseite braucht keinen API-Zugang.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            Weser AI
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-agency-edit
 *
 * @package WP_Agency_Edit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAEG_VERSION', '1.0.0' );
define( 'WPAEG_FILE', __FILE__ );
define( 'WPAEG_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAEG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Hauptklasse.
 */
class WP_Agency_Edit {

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		require_once WPAEG_DIR . 'includes/class-speicher.php';
		require_once WPAEG_DIR . 'includes/class-fernruf.php';
		require_once WPAEG_DIR . 'includes/class-agent.php';

		add_action( 'admin_menu', array( $this, 'menue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_footer', array( $this, 'widget' ) );
		add_action( 'rest_api_init', array( $this, 'routen' ) );
	}

	/**
	 * Menüpunkt.
	 *
	 * @return void
	 */
	public function menue(): void {
		add_menu_page(
			__( 'Agentur', 'wp-agency-edit' ),
			__( 'Agentur', 'wp-agency-edit' ),
			'manage_options',
			'wp-agency-edit',
			array( $this, 'seite' ),
			'dashicons-admin-multisite',
			58
		);
	}

	/**
	 * Systemanweisung für den Agenten.
	 *
	 * @param array $site Website-Eintrag.
	 * @return string
	 */
	public static function prompt( array $site ): string {
		$datei = WPAEG_DIR . 'prompts/agentur.md';
		$text  = file_exists( $datei ) ? (string) file_get_contents( $datei ) : '';
		if ( '' === trim( $text ) ) {
			$text = "Du bedienst eine fremde WordPress-Website über deren Fähigkeiten. Lies zuerst den Zustand, bevor du etwas änderst. Änderungen legst du als Vorschlag vor; der Eigentümer der Website gibt sie frei. Antworte knapp auf Deutsch.";
		}
		return $text . "\n\nBetreute Website: " . ( $site['name'] ?? '' ) . ' (' . ( $site['url'] ?? '' ) . ')'
			. ( ! empty( $site['wp'] ) ? ', WordPress ' . $site['wp'] : '' ) . '.';
	}

	/**
	 * Oberfläche.
	 *
	 * @return void
	 */
	public function seite(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$meldung = '';
		$fehler  = '';

		// Website hinzufügen oder ändern.
		if ( isset( $_POST['wpaeg_site'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpaeg_site'] ) ), 'wpaeg_site' ) ) {
			$ergebnis = WP_Agency_Edit_Speicher::speichern(
				array(
					'id'    => isset( $_POST['site_id'] ) ? sanitize_text_field( wp_unslash( $_POST['site_id'] ) ) : '',
					'name'  => isset( $_POST['site_name'] ) ? sanitize_text_field( wp_unslash( $_POST['site_name'] ) ) : '',
					'url'   => isset( $_POST['site_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['site_url'] ) ) ) : '',
					'user'  => isset( $_POST['site_user'] ) ? sanitize_text_field( wp_unslash( $_POST['site_user'] ) ) : '',
					'token' => isset( $_POST['site_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['site_token'] ) ) ) : '',
				)
			);
			if ( is_wp_error( $ergebnis ) ) {
				$fehler = $ergebnis->get_error_message();
			} else {
				$meldung = __( 'Website gespeichert.', 'wp-agency-edit' );
				// Gleich prüfen, damit der Zustand stimmt.
				$pruef = WP_Agency_Edit_Fernruf::pruefen( $ergebnis );
				if ( is_wp_error( $pruef ) ) {
					$fehler = __( 'Gespeichert, aber die Verbindung schlug fehl: ', 'wp-agency-edit' ) . $pruef->get_error_message();
				} else {
					$meldung .= ' ' . sprintf(
						/* translators: 1: Name, 2: WordPress-Version, 3: Zahl */
						__( 'Verbunden mit %1$s, WordPress %2$s, %3$d Fähigkeiten.', 'wp-agency-edit' ),
						$pruef['name'],
						$pruef['wp'],
						$pruef['faehigkeiten']
					);
				}
			}
		}

		// Verbindung prüfen.
		if ( isset( $_GET['pruefen'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpaeg_pruefen' ) ) {
			$site = WP_Agency_Edit_Speicher::eine( sanitize_text_field( wp_unslash( $_GET['pruefen'] ) ) );
			if ( $site ) {
				$pruef = WP_Agency_Edit_Fernruf::pruefen( $site );
				if ( is_wp_error( $pruef ) ) {
					$fehler = $pruef->get_error_message();
				} else {
					$meldung = sprintf(
						/* translators: 1: Name, 2: Version, 3: Zahl, 4: Benutzer */
						__( '%1$s · WordPress %2$s · %3$d Fähigkeiten · Zugang: %4$s', 'wp-agency-edit' ),
						$pruef['name'],
						$pruef['wp'],
						$pruef['faehigkeiten'],
						$pruef['nutzer']
					);
					delete_transient( 'wpaeg_faehig_' . md5( (string) $site['id'] ) );
				}
			}
		}

		// Entfernen.
		if ( isset( $_GET['entfernen'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpaeg_entfernen' ) ) {
			WP_Agency_Edit_Speicher::entfernen( sanitize_text_field( wp_unslash( $_GET['entfernen'] ) ) );
			$meldung = __( 'Website entfernt.', 'wp-agency-edit' );
		}

		// API-Zugang speichern.
		if ( isset( $_POST['wpaeg_llm'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpaeg_llm'] ) ), 'wpaeg_llm' ) ) {
			$alt = WP_Agency_Edit_Agent::settings();
			$key = isset( $_POST['llm_schluessel'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['llm_schluessel'] ) ) ) : '';
			$neu = array(
				'base_url'   => isset( $_POST['llm_base_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['llm_base_url'] ) ) ) : $alt['base_url'],
				'modell'     => isset( $_POST['llm_modell'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_modell'] ) ) : $alt['modell'],
				'temperatur' => isset( $_POST['llm_temperatur'] ) ? (float) str_replace( ',', '.', (string) $_POST['llm_temperatur'] ) : $alt['temperatur'],
				'max_runden' => isset( $_POST['llm_max_runden'] ) ? max( 1, min( 20, (int) $_POST['llm_max_runden'] ) ) : $alt['max_runden'],
			);
			if ( '' !== $key ) {
				$neu['schluessel'] = $key;
			}
			update_option( 'wp_agency_edit_llm', array_merge( $alt, $neu ) );
			$meldung = __( 'API-Zugang gespeichert.', 'wp-agency-edit' );
		}

		$sites = WP_Agency_Edit_Speicher::alle();
		$llm   = WP_Agency_Edit_Agent::settings();
		$bearbeiten = null;
		if ( isset( $_GET['bearbeiten'] ) ) {
			$bearbeiten = WP_Agency_Edit_Speicher::eine( sanitize_text_field( wp_unslash( $_GET['bearbeiten'] ) ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Agentur', 'wp-agency-edit' ); ?></h1>

			<?php if ( $meldung ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $meldung ); ?></p></div>
			<?php endif; ?>
			<?php if ( $fehler ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $fehler ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Betreute Websites', 'wp-agency-edit' ); ?></h2>
			<?php if ( ! $sites ) : ?>
				<p><?php esc_html_e( 'Noch keine Website hinterlegt. Unten Adresse und Anwendungspasswort eintragen.', 'wp-agency-edit' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1100px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'wp-agency-edit' ); ?></th>
							<th><?php esc_html_e( 'Adresse', 'wp-agency-edit' ); ?></th>
							<th><?php esc_html_e( 'Zugang', 'wp-agency-edit' ); ?></th>
							<th><?php esc_html_e( 'Zustand', 'wp-agency-edit' ); ?></th>
							<th><?php esc_html_e( 'Geprüft', 'wp-agency-edit' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sites as $s ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $s['name'] ?? '' ); ?></strong></td>
								<td><code><?php echo esc_html( $s['url'] ?? '' ); ?></code></td>
								<td><?php echo esc_html( $s['user'] ?? '' ); ?> <?php echo empty( $s['token'] ) ? '<em>(' . esc_html__( 'ohne Token', 'wp-agency-edit' ) . ')</em>' : ''; ?></td>
								<td>
									<?php if ( 'ok' === ( $s['status'] ?? '' ) ) : ?>
										<span style="color:green;">● <?php echo esc_html( ( $s['wpname'] ?? '' ) . ' · WP ' . ( $s['wp'] ?? '' ) ); ?></span>
									<?php elseif ( ! empty( $s['status'] ) ) : ?>
										<span style="color:#b32d2e;">● <?php echo esc_html( substr( (string) $s['status'], 0, 80 ) ); ?></span>
									<?php else : ?>
										<span style="color:#a7aaad;">● <?php esc_html_e( 'noch nicht geprüft', 'wp-agency-edit' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $s['geprueft'] ?? '—' ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wp-agency-edit', 'pruefen' => $s['id'] ), admin_url( 'admin.php' ) ), 'wpaeg_pruefen' ) ); ?>"><?php esc_html_e( 'Prüfen', 'wp-agency-edit' ); ?></a>
									<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-agency-edit', 'bearbeiten' => $s['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Ändern', 'wp-agency-edit' ); ?></a>
									<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wp-agency-edit', 'entfernen' => $s['id'] ), admin_url( 'admin.php' ) ), 'wpaeg_entfernen' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Website wirklich entfernen?', 'wp-agency-edit' ) ); ?>');"><?php esc_html_e( 'Entfernen', 'wp-agency-edit' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php echo $bearbeiten ? esc_html__( 'Website ändern', 'wp-agency-edit' ) : esc_html__( 'Website hinzufügen', 'wp-agency-edit' ); ?></h2>
			<form method="post" style="max-width:900px;">
				<?php wp_nonce_field( 'wpaeg_site', 'wpaeg_site' ); ?>
				<input type="hidden" name="site_id" value="<?php echo esc_attr( $bearbeiten['id'] ?? '' ); ?>">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="site_name"><?php esc_html_e( 'Name', 'wp-agency-edit' ); ?></label></th>
						<td><input type="text" class="regular-text" id="site_name" name="site_name" value="<?php echo esc_attr( $bearbeiten['name'] ?? '' ); ?>" placeholder="Star Food"></td>
					</tr>
					<tr>
						<th scope="row"><label for="site_url"><?php esc_html_e( 'Adresse', 'wp-agency-edit' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="site_url" name="site_url" value="<?php echo esc_attr( $bearbeiten['url'] ?? '' ); ?>" placeholder="https://starfood.pizza" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="site_user"><?php esc_html_e( 'Benutzer', 'wp-agency-edit' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="site_user" name="site_user" value="<?php echo esc_attr( $bearbeiten['user'] ?? '' ); ?>" placeholder="starfood_admin" required>
							<p class="description"><?php esc_html_e( 'Der Benutzer auf der Kundenseite, dem das Anwendungspasswort gehört.', 'wp-agency-edit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="site_token"><?php esc_html_e( 'Anwendungspasswort', 'wp-agency-edit' ); ?></label></th>
						<td>
							<input type="password" class="regular-text code" id="site_token" name="site_token" value="" autocomplete="off" placeholder="<?php echo $bearbeiten ? esc_attr__( 'leer lassen behält das bisherige', 'wp-agency-edit' ) : 'xxxx xxxx xxxx xxxx'; ?>">
							<p class="description"><?php esc_html_e( 'Wird verschlüsselt gespeichert. Auf der Kundenseite unter Einstellungen → WP AI Edit → Fernzugriff anlegen.', 'wp-agency-edit' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<button class="button button-primary"><?php esc_html_e( 'Speichern und prüfen', 'wp-agency-edit' ); ?></button>
					<?php if ( $bearbeiten ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-agency-edit' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Abbrechen', 'wp-agency-edit' ); ?></a>
					<?php endif; ?>
				</p>
			</form>

			<h2><?php esc_html_e( 'Sprachmodell', 'wp-agency-edit' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Läuft hier in der Zentrale. Die Kundenseiten brauchen keinen eigenen API-Zugang.', 'wp-agency-edit' ); ?></p>
			<form method="post" style="max-width:900px;">
				<?php wp_nonce_field( 'wpaeg_llm', 'wpaeg_llm' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="llm_base_url"><?php esc_html_e( 'Basis-URL', 'wp-agency-edit' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="llm_base_url" name="llm_base_url" value="<?php echo esc_attr( $llm['base_url'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="llm_modell"><?php esc_html_e( 'Modell', 'wp-agency-edit' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="llm_modell" name="llm_modell" value="<?php echo esc_attr( $llm['modell'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="llm_schluessel"><?php esc_html_e( 'Schlüssel', 'wp-agency-edit' ); ?></label></th>
						<td>
							<input type="password" class="regular-text code" id="llm_schluessel" name="llm_schluessel" value="" autocomplete="off" placeholder="<?php echo esc_attr( WP_Agency_Edit_Agent::maske() ); ?>">
							<p class="description"><?php esc_html_e( 'Leer lassen behält den bisherigen Schlüssel.', 'wp-agency-edit' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Grenzen', 'wp-agency-edit' ); ?></th>
						<td>
							<label><?php esc_html_e( 'Temperatur', 'wp-agency-edit' ); ?> <input type="text" size="5" name="llm_temperatur" value="<?php echo esc_attr( $llm['temperatur'] ); ?>"></label>
							&nbsp;
							<label><?php esc_html_e( 'Werkzeugrunden', 'wp-agency-edit' ); ?> <input type="number" size="4" name="llm_max_runden" value="<?php echo esc_attr( $llm['max_runden'] ); ?>" min="1" max="20"></label>
						</td>
					</tr>
				</table>
				<p><button class="button button-primary"><?php esc_html_e( 'Speichern', 'wp-agency-edit' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Skripte und Stile.
	 *
	 * @param string $hook Aktuelle Seite.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_style( 'wpaeg-widget', WPAEG_URL . 'assets/css/widget.css', array(), WPAEG_VERSION );
		wp_enqueue_script( 'wpaeg-widget', WPAEG_URL . 'assets/js/widget.js', array(), WPAEG_VERSION, true );
		wp_localize_script(
			'wpaeg-widget',
			'WPAEG',
			array(
				'rest'   => esc_url_raw( rest_url( 'wp-agency-edit/v1' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'seite'  => admin_url( 'admin.php?page=wp-agency-edit' ),
			)
		);
	}

	/**
	 * Das Chatfenster ausgeben.
	 *
	 * @return void
	 */
	public function widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sites = WP_Agency_Edit_Speicher::alle();
		if ( ! $sites ) {
			return;
		}
		$bereit = WP_Agency_Edit_Agent::bereit();
		?>
		<div id="wpaeg-widget" class="wpaeg-zu">
			<button type="button" id="wpaeg-knopf" aria-expanded="false">🏢 <?php esc_html_e( 'Agentur', 'wp-agency-edit' ); ?></button>
			<div id="wpaeg-fenster" hidden>
				<div class="wpaeg-kopf">
					<select id="wpaeg-site">
						<?php foreach ( $sites as $s ) : ?>
							<option value="<?php echo esc_attr( $s['id'] ); ?>"><?php echo esc_html( $s['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="wpaeg-neu" title="<?php esc_attr_e( 'Gespräch zurücksetzen', 'wp-agency-edit' ); ?>">↺</button>
				</div>
				<?php if ( ! $bereit ) : ?>
					<p class="wpaeg-hinweis"><?php esc_html_e( 'Kein Sprachmodell hinterlegt. Bitte auf der Agentur-Seite eintragen.', 'wp-agency-edit' ); ?></p>
				<?php endif; ?>
				<div id="wpaeg-verlauf"></div>
				<form id="wpaeg-form">
					<textarea id="wpaeg-eingabe" rows="2" placeholder="<?php esc_attr_e( 'Was soll auf dieser Website geändert werden?', 'wp-agency-edit' ); ?>"></textarea>
					<button type="submit"><?php esc_html_e( 'Senden', 'wp-agency-edit' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * REST-Routen.
	 *
	 * @return void
	 */
	public function routen(): void {
		$recht = static fn() => current_user_can( 'manage_options' );

		register_rest_route(
			'wp-agency-edit/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'permission_callback' => $recht,
				'callback'            => static function () {
					return new WP_REST_Response(
						array(
							'bereit' => WP_Agency_Edit_Agent::bereit(),
							'sites'  => array_map(
								static fn( $s ) => array(
									'id'     => $s['id'] ?? '',
									'name'   => $s['name'] ?? '',
									'url'    => $s['url'] ?? '',
									'status' => $s['status'] ?? '',
								),
								WP_Agency_Edit_Speicher::alle()
							),
						)
					);
				},
			)
		);

		register_rest_route(
			'wp-agency-edit/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'permission_callback' => $recht,
				'callback'            => array( $this, 'chat' ),
			)
		);

		register_rest_route(
			'wp-agency-edit/v1',
			'/reset',
			array(
				'methods'             => 'POST',
				'permission_callback' => $recht,
				'callback'            => array( $this, 'reset' ),
			)
		);
	}

	/**
	 * Verlaufsschlüssel für Benutzer und Website.
	 *
	 * @param string $site_id Kennung.
	 * @return string
	 */
	private function verlauf_key( string $site_id ): string {
		return 'wpaeg_verlauf_' . get_current_user_id() . '_' . md5( $site_id );
	}

	/**
	 * Chat.
	 *
	 * @param WP_REST_Request $anfrage Anfrage.
	 * @return WP_REST_Response
	 */
	public function chat( WP_REST_Request $anfrage ): WP_REST_Response {
		$site_id  = (string) $anfrage->get_param( 'site' );
		$nachricht = trim( (string) $anfrage->get_param( 'nachricht' ) );
		$site     = WP_Agency_Edit_Speicher::eine( $site_id );

		if ( ! $site ) {
			return new WP_REST_Response( array( 'fehler' => __( 'Website nicht gefunden.', 'wp-agency-edit' ) ), 404 );
		}
		if ( '' === $nachricht ) {
			return new WP_REST_Response( array( 'fehler' => __( 'Keine Nachricht.', 'wp-agency-edit' ) ), 400 );
		}

		$key     = $this->verlauf_key( $site_id );
		$verlauf = get_transient( $key );
		$verlauf = is_array( $verlauf ) ? $verlauf : array();

		if ( ! $verlauf ) {
			$verlauf[] = array( 'role' => 'system', 'content' => self::prompt( $site ) );
		}
		$verlauf[] = array( 'role' => 'user', 'content' => $nachricht );

		$antwort = WP_Agency_Edit_Agent::chat( $verlauf, $site );
		if ( is_wp_error( $antwort ) ) {
			return new WP_REST_Response( array( 'fehler' => $antwort->get_error_message() ), 200 );
		}

		$verlauf[] = array( 'role' => 'assistant', 'content' => $antwort );
		// Nur die letzten Schritte behalten, damit der Kontext nicht wächst.
		if ( count( $verlauf ) > 17 ) {
			$kopf    = array_slice( $verlauf, 0, 1 );
			$rest    = array_slice( $verlauf, -15 );
			$verlauf = array_merge( $kopf, $rest );
		}
		set_transient( $key, $verlauf, 4 * HOUR_IN_SECONDS );

		return new WP_REST_Response( array( 'antwort' => $antwort, 'site' => $site['name'] ), 200 );
	}

	/**
	 * Verlauf verwerfen.
	 *
	 * @param WP_REST_Request $anfrage Anfrage.
	 * @return WP_REST_Response
	 */
	public function reset( WP_REST_Request $anfrage ): WP_REST_Response {
		delete_transient( $this->verlauf_key( (string) $anfrage->get_param( 'site' ) ) );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}

new WP_Agency_Edit();
