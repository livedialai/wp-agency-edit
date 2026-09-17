<?php
/**
 * Der Agent: spricht mit dem Sprachmodell und führt Werkzeugaufrufe auf der
 * betreuten Website aus.
 *
 * Aufbau wie im Website-Plugin, mit einem entscheidenden Unterschied: die
 * Werkzeugliste kommt von der Gegenstelle, und ausgeführt wird dort per HTTP.
 * Der API-Schlüssel liegt hier — die Kundenseite braucht keinen.
 *
 * @package WP_Agency_Edit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Werkzeugrunde gegen ein Sprachmodell.
 */
class WP_Agency_Edit_Agent {

	/**
	 * Zwischenspeicher für die Fähigkeitsliste einer Website (Sekunden).
	 */
	const ZWISCHENSPEICHER = 300;

	/**
	 * Standardwerte des API-Zugangs.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'base_url'   => 'https://api.deepseek.com',
			'modell'     => 'deepseek-flash',
			'schluessel' => '',
			'temperatur' => 0.3,
			'max_runden' => 8,
			'timeout'    => 120,
			'thinking'   => 0,
		);
	}

	/**
	 * Einstellungen.
	 *
	 * @return array
	 */
	public static function settings(): array {
		$s = get_option( 'wp_agency_edit_llm', array() );
		return array_merge( self::defaults(), is_array( $s ) ? $s : array() );
	}

	/**
	 * Ist der Zugang vollständig?
	 *
	 * @return bool
	 */
	public static function bereit(): bool {
		$s = self::settings();
		return '' !== trim( (string) $s['base_url'] ) && '' !== trim( (string) $s['modell'] ) && '' !== trim( (string) $s['schluessel'] );
	}

	/**
	 * Schlüssel maskiert anzeigen.
	 *
	 * @return string
	 */
	public static function maske(): string {
		$k = (string) self::settings()['schluessel'];
		if ( '' === $k ) {
			return '';
		}
		return strlen( $k ) < 8 ? '••••' : substr( $k, 0, 4 ) . '…' . substr( $k, -4 );
	}

	/**
	 * Fähigkeitsname in einen gültigen Funktionsnamen wandeln.
	 *
	 * @param string $name Fähigkeitsname.
	 * @return string
	 */
	public static function funktionsname( string $name ): string {
		$n = str_replace( array( '/', '-' ), '_', $name );
		return substr( preg_replace( '/[^a-zA-Z0-9_]/', '', $n ), 0, 64 );
	}

	/**
	 * Fähigkeiten der Gegenstelle, mit kurzem Zwischenspeicher.
	 *
	 * @param array $site Eintrag.
	 * @return array|WP_Error
	 */
	public static function faehigkeiten( array $site ) {
		$schluessel = 'wpaeg_faehig_' . md5( (string) $site['id'] );
		$zwischen   = get_transient( $schluessel );
		if ( is_array( $zwischen ) && $zwischen ) {
			return $zwischen;
		}
		$liste = WP_Agency_Edit_Fernruf::faehigkeiten( $site );
		if ( is_wp_error( $liste ) ) {
			return $liste;
		}
		set_transient( $schluessel, $liste, self::ZWISCHENSPEICHER );
		return $liste;
	}

	/**
	 * Werkzeugdefinitionen aus der Fähigkeitsliste bauen.
	 *
	 * @param array $faehigkeiten Liste von der Gegenstelle.
	 * @return array{0: array, 1: array}
	 */
	protected static function werkzeuge( array $faehigkeiten ): array {
		$tools = array();
		$map   = array();

		foreach ( $faehigkeiten as $f ) {
			$name  = (string) ( $f['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$fname         = self::funktionsname( $name );
			$map[ $fname ] = $name;

			$schema = $f['input_schema'] ?? array();
			if ( ! is_array( $schema ) || empty( $schema ) ) {
				$schema = array( 'type' => 'object', 'properties' => array() );
			}
			if ( isset( $schema['properties'] ) && array() === $schema['properties'] ) {
				$schema['properties'] = new stdClass();
			}
			$beschreibung = trim( (string) ( $f['description'] ?? '' ) );
			if ( '' === $beschreibung ) {
				$beschreibung = (string) ( $f['label'] ?? $name );
			}

			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $fname,
					'description' => $beschreibung,
					'parameters'  => $schema,
				),
			);
		}

		return array( $tools, $map );
	}

	/**
	 * Werkzeugaufruf auf der Gegenstelle ausführen.
	 *
	 * @param array  $site      Eintrag.
	 * @param string $fname     Funktionsname.
	 * @param array  $arguments Argumente.
	 * @param array  $map       Rückabbildung.
	 * @return string JSON.
	 */
	protected static function ausfuehren( array $site, string $fname, array $arguments, array $map ): string {
		if ( ! isset( $map[ $fname ] ) ) {
			return wp_json_encode( array( 'fehler' => 'Unbekanntes Werkzeug: ' . $fname ) );
		}
		$ergebnis = WP_Agency_Edit_Fernruf::ausfuehren( $site, $map[ $fname ], $arguments );
		if ( is_wp_error( $ergebnis ) ) {
			return wp_json_encode( array( 'fehler' => $ergebnis->get_error_message() ) );
		}
		$json = wp_json_encode( $ergebnis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! $json ) {
			$json = wp_json_encode( array( 'ergebnis' => 'nicht serialisierbar' ) );
		}
		if ( strlen( $json ) > 24000 ) {
			$json = substr( $json, 0, 24000 ) . ' …(gekürzt)';
		}
		return $json;
	}

	/**
	 * Gespräch führen, bis das Modell eine Textantwort liefert.
	 *
	 * @param array $messages Nachrichten im OpenAI-Format.
	 * @param array $site     Website-Eintrag.
	 * @return string|WP_Error
	 */
	public static function chat( array $messages, array $site ) {
		$s = self::settings();
		if ( ! self::bereit() ) {
			return new WP_Error( 'wpaeg_llm', __( 'Kein API-Zugang hinterlegt (Basis-URL, Modell, Schlüssel).', 'wp-agency-edit' ) );
		}

		$faehigkeiten = self::faehigkeiten( $site );
		if ( is_wp_error( $faehigkeiten ) ) {
			return $faehigkeiten;
		}
		list( $tools, $map ) = self::werkzeuge( $faehigkeiten );

		$url    = rtrim( (string) $s['base_url'], '/' ) . '/chat/completions';
		$verlauf = array_values( $messages );
		$max     = max( 1, (int) $s['max_runden'] );
		$runden  = 0;
		$benutzt = array();

		while ( $runden < $max ) {
			++$runden;

			$koerper = array(
				'model'       => (string) $s['modell'],
				'messages'    => $verlauf,
				'temperature' => (float) $s['temperatur'],
			);
			if ( ! empty( $tools ) ) {
				$koerper['tools']       = $tools;
				$koerper['tool_choice'] = 'auto';
			}
			if ( ! empty( $s['thinking'] ) ) {
				$koerper['thinking'] = array( 'type' => 'enabled' );
			}

			$antwort = wp_remote_post(
				$url,
				array(
					'timeout'     => (int) $s['timeout'],
					'headers'     => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . (string) $s['schluessel'],
						'Accept'        => 'application/json',
					),
					'body'        => wp_json_encode( $koerper, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
					'data_format' => 'body',
				)
			);
			if ( is_wp_error( $antwort ) ) {
				return new WP_Error( 'wpaeg_netz', __( 'Verbindung zum Sprachmodell fehlgeschlagen: ', 'wp-agency-edit' ) . $antwort->get_error_message() );
			}

			$code  = (int) wp_remote_retrieve_response_code( $antwort );
			$roh   = (string) wp_remote_retrieve_body( $antwort );
			$daten = json_decode( $roh, true );

			if ( $code < 200 || $code >= 300 ) {
				$meldung = is_array( $daten ) ? (string) ( $daten['error']['message'] ?? ( $daten['message'] ?? '' ) ) : '';
				return new WP_Error(
					'wpaeg_api',
					sprintf(
						/* translators: 1: Status, 2: Meldung */
						__( 'Sprachmodell antwortet mit HTTP %1$d: %2$s', 'wp-agency-edit' ),
						$code,
						'' !== $meldung ? $meldung : substr( $roh, 0, 300 )
					)
				);
			}
			if ( ! is_array( $daten ) || ! isset( $daten['choices'][0]['message'] ) ) {
				return new WP_Error( 'wpaeg_format', __( 'Unerwartete Antwort des Sprachmodells.', 'wp-agency-edit' ) );
			}

			$nachricht = $daten['choices'][0]['message'];
			$aufrufe   = $nachricht['tool_calls'] ?? array();

			if ( empty( $aufrufe ) ) {
				$text = trim( (string) ( $nachricht['content'] ?? '' ) );
				if ( '' === $text ) {
					$text = __( '(leere Antwort des Modells)', 'wp-agency-edit' );
				}
				if ( ! empty( $benutzt ) ) {
					$text .= "\n\n— Auf der Website ausgeführt: " . implode( ', ', array_unique( $benutzt ) );
				}
				return $text;
			}

			$verlauf[] = array(
				'role'       => 'assistant',
				'content'    => $nachricht['content'] ?? '',
				'tool_calls' => $aufrufe,
			);

			foreach ( $aufrufe as $aufruf ) {
				$fname = (string) ( $aufruf['function']['name'] ?? '' );
				$args  = json_decode( (string) ( $aufruf['function']['arguments'] ?? '{}' ), true );
				$args  = is_array( $args ) ? $args : array();

				$benutzt[] = $map[ $fname ] ?? $fname;

				$verlauf[] = array(
					'role'         => 'tool',
					'tool_call_id' => (string) ( $aufruf['id'] ?? '' ),
					'name'         => $fname,
					'content'      => self::ausfuehren( $site, $fname, $args, $map ),
				);
			}
		}

		return new WP_Error(
			'wpaeg_runden',
			sprintf(
				/* translators: %d: Anzahl Runden */
				__( 'Abbruch nach %d Werkzeugrunden. Bitte die Anweisung enger fassen.', 'wp-agency-edit' ),
				$max
			)
		);
	}

	/**
	 * Sprachmodell prüfen.
	 *
	 * @return array|WP_Error
	 */
	public static function test() {
		$s = self::settings();
		if ( ! self::bereit() ) {
			return new WP_Error( 'wpaeg_llm', __( 'Kein API-Zugang hinterlegt.', 'wp-agency-edit' ) );
		}
		$antwort = wp_remote_post(
			rtrim( (string) $s['base_url'], '/' ) . '/chat/completions',
			array(
				'timeout'     => 60,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . (string) $s['schluessel'],
				),
				'body'        => wp_json_encode(
					array(
						'model'    => (string) $s['modell'],
						'messages' => array( array( 'role' => 'user', 'content' => 'Antworte mit genau einem Wort: OK' ) ),
					)
				),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $antwort ) ) {
			return $antwort;
		}
		$code  = (int) wp_remote_retrieve_response_code( $antwort );
		$daten = json_decode( (string) wp_remote_retrieve_body( $antwort ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'wpaeg_api', 'HTTP ' . $code . ': ' . (string) ( $daten['error']['message'] ?? '' ) );
		}
		return array(
			'antwort' => trim( (string) ( $daten['choices'][0]['message']['content'] ?? '' ) ),
			'modell'  => (string) $s['modell'],
		);
	}
}
