<?php
/**
 * Fernruf: spricht mit einer betreuten WordPress-Installation.
 *
 * Anmeldung über ein Anwendungspasswort (HTTP Basic). Für die Gegenstelle ist
 * das ein normaler Benutzer, ihre Berechtigungsprüfungen greifen unverändert.
 *
 * @package WP_Agency_Edit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP-Zugriff auf eine betreute Website.
 */
class WP_Agency_Edit_Fernruf {

	/**
	 * Zeitgrenze in Sekunden.
	 */
	const TIMEOUT = 60;

	/**
	 * Kopfzeilen für die Anmeldung bauen.
	 *
	 * @param array $zugang url, user, token.
	 * @return array
	 */
	private static function kopf( array $zugang ): array {
		return array(
			'Authorization' => 'Basic ' . base64_encode( $zugang['user'] . ':' . $zugang['token'] ),
			'Accept'        => 'application/json',
			'User-Agent'    => 'WP-Agency-Edit/' . ( defined( 'WPAEG_VERSION' ) ? WPAEG_VERSION : '1.0' ) . '; ' . home_url(),
		);
	}

	/**
	 * Eine Anfrage an die Gegenstelle.
	 *
	 * @param array  $site    Eintrag aus der Liste.
	 * @param string $pfad    Pfad ab /wp-json/.
	 * @param string $methode GET oder POST.
	 * @param array  $koerper Körper für POST.
	 * @return array|WP_Error Daten und HTTP-Code.
	 */
	private static function ruf( array $site, string $pfad, string $methode = 'GET', array $koerper = array() ) {
		$zugang = WP_Agency_Edit_Speicher::zugang( $site );
		if ( '' === $zugang['url'] || '' === $zugang['token'] ) {
			return new WP_Error( 'wpaeg_zugang', __( 'Für diese Website fehlen Adresse oder Zugangstoken.', 'wp-agency-edit' ) );
		}

		$args = array(
			'method'  => $methode,
			'timeout' => self::TIMEOUT,
			'headers' => self::kopf( $zugang ),
		);
		if ( 'POST' === $methode ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $koerper, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$args['data_format']             = 'body';
		}

		$antwort = wp_remote_request( $zugang['url'] . '/wp-json/' . ltrim( $pfad, '/' ), $args );
		if ( is_wp_error( $antwort ) ) {
			return new WP_Error( 'wpaeg_netz', __( 'Keine Verbindung: ', 'wp-agency-edit' ) . $antwort->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $antwort );
		$roh  = (string) wp_remote_retrieve_body( $antwort );
		$daten = json_decode( $roh, true );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error(
				'wpaeg_recht',
				sprintf(
					/* translators: %d: HTTP-Status */
					__( 'Zugang abgelehnt (HTTP %d). Stimmen Benutzername und Anwendungspasswort?', 'wp-agency-edit' ),
					$code
				)
			);
		}
		if ( $code < 200 || $code >= 300 ) {
			$meldung = '';
			if ( is_array( $daten ) ) {
				$meldung = (string) ( $daten['message'] ?? '' );
			}
			return new WP_Error(
				'wpaeg_http',
				sprintf(
					/* translators: 1: HTTP-Status, 2: Meldung */
					__( 'HTTP %1$d: %2$s', 'wp-agency-edit' ),
					$code,
					'' !== $meldung ? wp_strip_all_tags( $meldung ) : substr( $roh, 0, 200 )
				)
			);
		}
		return array( 'daten' => $daten, 'code' => $code );
	}

	/**
	 * Auskunft der Gegenstelle.
	 *
	 * @param array $site Eintrag.
	 * @return array|WP_Error
	 */
	public static function auskunft( array $site ) {
		$r = self::ruf( $site, 'wp-ai-edit/v1/auskunft' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return is_array( $r['daten'] ) ? $r['daten'] : new WP_Error( 'wpaeg_format', __( 'Unerwartete Antwort der Gegenstelle.', 'wp-agency-edit' ) );
	}

	/**
	 * Fähigkeiten der Gegenstelle, die dieses Plugin dort anbietet.
	 *
	 * @param array $site Eintrag.
	 * @return array|WP_Error Liste aus name, label, description, input_schema.
	 */
	public static function faehigkeiten( array $site ) {
		$r = self::ruf( $site, 'wp-abilities/v1/abilities' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$alle = is_array( $r['daten'] ) ? $r['daten'] : array();
		$raus = array();
		foreach ( $alle as $a ) {
			if ( ! is_array( $a ) || ! isset( $a['name'] ) ) {
				continue;
			}
			$name = (string) $a['name'];
			if ( 0 !== strpos( $name, 'kiedit/' ) ) {
				continue;
			}
			$raus[] = array(
				'name'         => $name,
				'label'        => (string) ( $a['label'] ?? $name ),
				'description'  => (string) ( $a['description'] ?? '' ),
				'input_schema' => is_array( $a['input_schema'] ?? null ) ? $a['input_schema'] : array(),
				'meta'         => is_array( $a['meta'] ?? null ) ? $a['meta'] : array(),
			);
		}
		return $raus;
	}

	/**
	 * Eine Fähigkeit auf der Gegenstelle ausführen.
	 *
	 * @param array  $site  Eintrag.
	 * @param string $name  Fähigkeitsname, z. B. kiedit/get-page.
	 * @param array  $input Eingabe.
	 * @return array|WP_Error
	 */
	public static function ausfuehren( array $site, string $name, array $input ) {
		// Der Schraegstrich im Namen ist Teil der Route, darf also nicht kodiert werden.
		$sauber = preg_replace( '/[^a-zA-Z0-9\-\/_]/', '', $name );
		$r      = self::ruf(
			$site,
			'wp-abilities/v1/abilities/' . $sauber . '/run',
			'POST',
			array( 'input' => $input )
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return is_array( $r['daten'] ) ? $r['daten'] : array( 'ergebnis' => $r['daten'] );
	}

	/**
	 * Verbindung prüfen und den Stand festhalten.
	 *
	 * @param array $site Eintrag.
	 * @return array|WP_Error Kurzbericht.
	 */
	public static function pruefen( array $site ) {
		$auskunft = self::auskunft( $site );
		if ( is_wp_error( $auskunft ) ) {
			WP_Agency_Edit_Speicher::stand(
				(string) $site['id'],
				array( 'status' => 'Fehler: ' . $auskunft->get_error_message() )
			);
			return $auskunft;
		}
		$wpaie = is_array( $auskunft['wpaie'] ?? null ) ? $auskunft['wpaie'] : array();
		WP_Agency_Edit_Speicher::stand(
			(string) $site['id'],
			array(
				'status'  => 'ok',
				'wp'      => (string) ( $auskunft['wp'] ?? '' ),
				'wpname'  => (string) ( $auskunft['name'] ?? '' ),
				'kennung' => (string) ( $auskunft['kennung'] ?? '' ),
			)
		);
		return array(
			'name'         => (string) ( $auskunft['name'] ?? '' ),
			'wp'           => (string) ( $auskunft['wp'] ?? '' ),
			'php'          => (string) ( $auskunft['php'] ?? '' ),
			'plugin'       => (string) ( $wpaie['version'] ?? '' ),
			'faehigkeiten' => (int) ( $wpaie['faehigkeiten'] ?? 0 ),
			'kennung'      => (string) ( $auskunft['kennung'] ?? '' ),
			'nutzer'       => (string) ( $auskunft['nutzer']['name'] ?? '' ),
		);
	}
}
