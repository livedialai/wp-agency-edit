<?php
/**
 * Sitzungssperre der Zentrale.
 *
 * Vor dem Bearbeiten einer Website muss die Bedienperson ihr Passwort erneut
 * eingeben. Danach bleibt die Sitzung entsperrt, solange sie arbeitet — nach
 * fünf Minuten Ruhe fällt sie von selbst zu. Wird die Website gewechselt, ist
 * sie sofort wieder zu.
 *
 * Geprüft wird gegen das Benutzerkonto dieser Installation. Es gibt also kein
 * zweites Passwortsystem, sondern eine erneute Bestätigung — dasselbe Muster,
 * das WordPress selbst für heikle Bereiche verwendet.
 *
 * @package WP_Agency_Edit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sitzung und Passwortbestätigung.
 */
class WP_Agency_Edit_Sitzung {

	/**
	 * Ruhezeit in Sekunden, bis die Sitzung zufällt.
	 */
	const DAUER = 300;

	/**
	 * Wie viele Fehlversuche bis zur Sperre?
	 */
	const VERSUCHE = 5;

	/**
	 * Sperrzeit nach zu vielen Fehlversuchen (Sekunden).
	 */
	const SPERRE = 600;

	/**
	 * Option mit dem eigenen Agentur-Passwort (Hash).
	 */
	const PASSWORT = 'wp_agency_edit_sperre';

	/**
	 * Kennung des Sitzungsspeichers.
	 *
	 * @return string
	 */
	private static function schluessel(): string {
		return 'wpaeg_sitzung_' . get_current_user_id();
	}

	/**
	 * Kennung des Fehlversuchszählers.
	 *
	 * @return string
	 */
	private static function versuche_schluessel(): string {
		return 'wpaeg_versuche_' . get_current_user_id();
	}

	/**
	 * Ist bereits ein Agentur-Passwort gesetzt?
	 *
	 * @return bool
	 */
	public static function eingerichtet(): bool {
		$o = get_option( self::PASSWORT, array() );
		return is_array( $o ) && ! empty( $o['hash'] );
	}

	/**
	 * Wann wurde das Passwort gesetzt?
	 *
	 * @return string
	 */
	public static function gesetzt_am(): string {
		$o = get_option( self::PASSWORT, array() );
		return is_array( $o ) && ! empty( $o['zeit'] ) ? (string) $o['zeit'] : '';
	}

	/**
	 * Ersteinrichtung: Agentur-Passwort festlegen.
	 *
	 * @param string $neu       Neues Passwort.
	 * @param string $wiederholung Wiederholung.
	 * @return true|WP_Error
	 */
	public static function festlegen( string $neu, string $wiederholung ) {
		if ( self::eingerichtet() ) {
			return new WP_Error( 'wpaeg_schon', __( 'Es ist bereits ein Agentur-Passwort gesetzt.', 'wp-agency-edit' ) );
		}
		return self::setzen( $neu, $wiederholung );
	}

	/**
	 * Passwort ändern (bisheriges nötig).
	 *
	 * @param string $bisher      Bisheriges Passwort.
	 * @param string $neu         Neues Passwort.
	 * @param string $wiederholung Wiederholung.
	 * @return true|WP_Error
	 */
	public static function aendern( string $bisher, string $neu, string $wiederholung ) {
		if ( ! self::eingerichtet() ) {
			return new WP_Error( 'wpaeg_keins', __( 'Es ist noch kein Agentur-Passwort gesetzt.', 'wp-agency-edit' ) );
		}
		if ( self::restversuche() < 1 ) {
			return new WP_Error( 'wpaeg_gesperrt', __( 'Zu viele Fehlversuche. Bitte später erneut versuchen.', 'wp-agency-edit' ) );
		}
		if ( ! self::pruefen_passwort( $bisher ) ) {
			self::fehlversuch_zaehlen();
			return new WP_Error(
				'wpaeg_falsch',
				sprintf(
					/* translators: %d: verbleibende Versuche */
					__( 'Bisheriges Passwort stimmt nicht. Noch %d Versuche.', 'wp-agency-edit' ),
					self::restversuche()
				)
			);
		}
		$gesetzt = self::setzen( $neu, $wiederholung );
		if ( ! is_wp_error( $gesetzt ) ) {
			// Nach einer Änderung ist die Sitzung zu — das neue Passwort wird
			// sofort gebraucht.
			self::sperren();
		}
		return $gesetzt;
	}

	/**
	 * Passwort speichern (gehasht).
	 *
	 * @param string $neu         Neues Passwort.
	 * @param string $wiederholung Wiederholung.
	 * @return true|WP_Error
	 */
	private static function setzen( string $neu, string $wiederholung ) {
		if ( strlen( $neu ) < 8 ) {
			return new WP_Error( 'wpaeg_kurz', __( 'Das Passwort braucht mindestens 8 Zeichen.', 'wp-agency-edit' ) );
		}
		if ( $neu !== $wiederholung ) {
			return new WP_Error( 'wpaeg_ungleich', __( 'Die beiden Eingaben stimmen nicht überein.', 'wp-agency-edit' ) );
		}
		update_option(
			self::PASSWORT,
			array(
				'hash' => wp_hash_password( $neu ),
				'zeit' => current_time( 'mysql' ),
			),
			false
		);
		delete_transient( self::versuche_schluessel() );
		return true;
	}

	/**
	 * Passwort zurücksetzen — nur über die Einstellungsseite.
	 *
	 * @return void
	 */
	public static function zuruecksetzen(): void {
		delete_option( self::PASSWORT );
		self::sperren();
	}

	/**
	 * Passwort gegen den gespeicherten Hash prüfen, mit Rückfall auf das
	 * eigene WordPress-Passwort.
	 *
	 * @param string $passwort Eingabe.
	 * @return bool
	 */
	private static function pruefen_passwort( string $passwort ): bool {
		$o = get_option( self::PASSWORT, array() );
		if ( is_array( $o ) && ! empty( $o['hash'] ) && wp_check_password( $passwort, (string) $o['hash'] ) ) {
			return true;
		}
		// Rückfall: das eigene WordPress-Passwort öffnet ebenfalls. Damit ist
		// niemand ausgesperrt, der sein Passwort vergisst.
		$benutzer = wp_get_current_user();
		return $benutzer && $benutzer->ID && wp_check_password( $passwort, $benutzer->user_pass, $benutzer->ID );
	}

	/**
	 * Einen Fehlversuch zählen.
	 *
	 * @return void
	 */
	private static function fehlversuch_zaehlen(): void {
		$v = (int) get_transient( self::versuche_schluessel() ) + 1;
		set_transient( self::versuche_schluessel(), $v, self::SPERRE );
	}

	/**
	 * Ist die Sitzung entsperrt — und zwar für genau diese Website?
	 *
	 * @param string $site_id Kennung der Website.
	 * @return bool
	 */
	public static function entsperrt( string $site_id ): bool {
		$s = get_transient( self::schluessel() );
		if ( ! is_array( $s ) || empty( $s['zeit'] ) || ( $s['site'] ?? '' ) !== $site_id ) {
			return false;
		}
		return ( time() - (int) $s['zeit'] ) < self::dauer();
	}

	/**
	 * Ruhezeit, einstellbar über die Einstellungen.
	 *
	 * @return int Sekunden.
	 */
	public static function dauer(): int {
		$s = get_option( 'wp_agency_edit_llm', array() );
		$m = isset( $s['sperre_minuten'] ) ? (int) $s['sperre_minuten'] : 5;
		return max( 1, min( 120, $m ) ) * 60;
	}

	/**
	 * Verbleibende Sekunden bis zum Zufallen.
	 *
	 * @param string $site_id Kennung.
	 * @return int
	 */
	public static function restzeit( string $site_id ): int {
		if ( ! self::entsperrt( $site_id ) ) {
			return 0;
		}
		$s = get_transient( self::schluessel() );
		$rest = self::dauer() - ( time() - (int) $s['zeit'] );
		return max( 0, $rest );
	}

	/**
	 * Lebenszeichen: die Ruhezeit beginnt von vorn.
	 *
	 * @param string $site_id Kennung.
	 * @return void
	 */
	public static function beruehren( string $site_id ): void {
		if ( ! self::entsperrt( $site_id ) ) {
			return;
		}
		set_transient(
			self::schluessel(),
			array( 'site' => $site_id, 'zeit' => time() ),
			self::dauer() + 60
		);
	}

	/**
	 * Sitzung sperren (Abmelden).
	 *
	 * @return void
	 */
	public static function sperren(): void {
		delete_transient( self::schluessel() );
	}

	/**
	 * Wie viele Fehlversuche sind noch erlaubt?
	 *
	 * @return int
	 */
	public static function restversuche(): int {
		$v = (int) get_transient( self::versuche_schluessel() );
		return max( 0, self::VERSUCHE - $v );
	}

	/**
	 * Sitzung mit dem eigenen Passwort entsperren.
	 *
	 * @param string $passwort Eingegebenes Passwort.
	 * @param string $site_id  Kennung der Website.
	 * @return true|WP_Error
	 */
	public static function entsperren( string $passwort, string $site_id ) {
		if ( '' === $passwort ) {
			return new WP_Error( 'wpaeg_leer', __( 'Bitte das Passwort eingeben.', 'wp-agency-edit' ) );
		}
		if ( self::restversuche() < 1 ) {
			return new WP_Error(
				'wpaeg_gesperrt',
				sprintf(
					/* translators: %d: Minuten */
					__( 'Zu viele Fehlversuche. Bitte %d Minuten warten.', 'wp-agency-edit' ),
					(int) ( self::SPERRE / 60 )
				)
			);
		}

		if ( ! self::eingerichtet() ) {
			return new WP_Error( 'wpaeg_ersteinrichtung', __( 'Bitte zuerst ein Agentur-Passwort festlegen.', 'wp-agency-edit' ) );
		}
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'wpaeg_nutzer', __( 'Nicht angemeldet.', 'wp-agency-edit' ) );
		}

		// Gegen den gespeicherten Hash prüfen, mit Rückfall auf das WordPress-Passwort.
		if ( ! self::pruefen_passwort( $passwort ) ) {
			self::fehlversuch_zaehlen();
			return new WP_Error(
				'wpaeg_falsch',
				sprintf(
					/* translators: %d: verbleibende Versuche */
					__( 'Passwort stimmt nicht. Noch %d Versuche.', 'wp-agency-edit' ),
					self::restversuche()
				)
			);
		}

		delete_transient( self::versuche_schluessel() );
		set_transient(
			self::schluessel(),
			array( 'site' => $site_id, 'zeit' => time() ),
			self::dauer() + 60
		);
		return true;
	}
}
