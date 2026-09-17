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

		$benutzer = wp_get_current_user();
		if ( ! $benutzer || ! $benutzer->ID ) {
			return new WP_Error( 'wpaeg_nutzer', __( 'Nicht angemeldet.', 'wp-agency-edit' ) );
		}

		// Gegen das eigene Benutzerkonto prüfen.
		if ( ! wp_check_password( $passwort, $benutzer->user_pass, $benutzer->ID ) ) {
			$v = (int) get_transient( self::versuche_schluessel() ) + 1;
			set_transient( self::versuche_schluessel(), $v, self::SPERRE );
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
