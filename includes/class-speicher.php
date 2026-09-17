<?php
/**
 * Ablage der betreuten Websites samt verschlüsselter Zugangstoken.
 *
 * Die Token liegen nicht im Klartext in der Datenbank, sondern mit
 * sodium_crypto_secretbox verschlüsselt. Der Schlüssel wird aus den Salzen der
 * Installation abgeleitet und steht damit in der wp-config.php — nicht in der
 * Datenbank. Ein Datenbankauszug allein nützt also niemandem.
 *
 * @package WP_Agency_Edit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verwaltung der Website-Liste.
 */
class WP_Agency_Edit_Speicher {

	/**
	 * Option mit der Liste.
	 */
	const OPTION = 'wp_agency_edit_sites';

	/**
	 * Schlüssel für die Verschlüsselung ableiten.
	 *
	 * @return string 32 Byte.
	 */
	private static function schluessel(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . '|wp-agency-edit|' . wp_salt( 'secure_auth' ), true );
	}

	/**
	 * Verschlüsselt einen Token.
	 *
	 * @param string $klar Klartext.
	 * @return string Base64 aus Nonce und Geheimtext.
	 */
	public static function verschluesseln( string $klar ): string {
		if ( '' === $klar ) {
			return '';
		}
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $klar, $nonce, self::schluessel() ) );
		}
		// Rückfall: OpenSSL mit AES-256-GCM.
		$iv  = random_bytes( 12 );
		$tag = '';
		$geheim = openssl_encrypt( $klar, 'aes-256-gcm', self::schluessel(), OPENSSL_RAW_DATA, $iv, $tag );
		return 'o1:' . base64_encode( $iv . $tag . $geheim );
	}

	/**
	 * Entschlüsselt einen Token.
	 *
	 * @param string $gespeichert Gespeicherter Wert.
	 * @return string Klartext oder leer.
	 */
	public static function entschluesseln( string $gespeichert ): string {
		if ( '' === $gespeichert ) {
			return '';
		}
		try {
			if ( 0 === strpos( $gespeichert, 's1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
				$roh   = base64_decode( substr( $gespeichert, 3 ), true );
				if ( false === $roh || strlen( $roh ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
					return '';
				}
				$nonce = substr( $roh, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
				$klar  = sodium_crypto_secretbox_open( substr( $roh, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, self::schluessel() );
				return is_string( $klar ) ? $klar : '';
			}
			if ( 0 === strpos( $gespeichert, 'o1:' ) ) {
				$roh = base64_decode( substr( $gespeichert, 3 ), true );
				if ( false === $roh || strlen( $roh ) < 29 ) {
					return '';
				}
				$klar = openssl_decrypt( substr( $roh, 28 ), 'aes-256-gcm', self::schluessel(), OPENSSL_RAW_DATA, substr( $roh, 0, 12 ), substr( $roh, 12, 16 ) );
				return is_string( $klar ) ? $klar : '';
			}
		} catch ( Throwable $e ) {
			return '';
		}
		return '';
	}

	/**
	 * Alle Websites.
	 *
	 * @return array
	 */
	public static function alle(): array {
		$liste = get_option( self::OPTION, array() );
		return is_array( $liste ) ? array_values( $liste ) : array();
	}

	/**
	 * Eine Website anhand der Kennung.
	 *
	 * @param string $id Kennung.
	 * @return array|null
	 */
	public static function eine( string $id ): ?array {
		foreach ( self::alle() as $s ) {
			if ( isset( $s['id'] ) && $s['id'] === $id ) {
				return $s;
			}
		}
		return null;
	}

	/**
	 * Kennung für Klartextwerte (Adresse oder Benutzer), damit die Liste
	 * zusammenführt statt zu duplizieren.
	 *
	 * @param string $url  Adresse.
	 * @param string $user Benutzername.
	 * @return string
	 */
	public static function kennung( string $url, string $user ): string {
		return substr( md5( untrailingslashit( strtolower( $url ) ) . '|' . strtolower( $user ) ), 0, 12 );
	}

	/**
	 * Website speichern (neu oder aktualisieren).
	 *
	 * @param array $werte Felder: name, url, user, token, id.
	 * @return array|WP_Error Gespeicherter Eintrag.
	 */
	public static function speichern( array $werte ) {
		$url  = untrailingslashit( trim( (string) ( $werte['url'] ?? '' ) ) );
		$user = trim( (string) ( $werte['user'] ?? '' ) );
		$name = trim( (string) ( $werte['name'] ?? '' ) );

		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			return new WP_Error( 'wpaeg_url', __( 'Bitte eine vollständige Adresse angeben, beginnend mit https://', 'wp-agency-edit' ) );
		}
		if ( '' === $user ) {
			return new WP_Error( 'wpaeg_benutzer', __( 'Bitte den Benutzernamen angeben, dem das Anwendungspasswort gehört.', 'wp-agency-edit' ) );
		}

		$id    = (string) ( $werte['id'] ?? '' );
		$liste = self::alle();

		// Bestehenden Eintrag suchen.
		$treffer = null;
		foreach ( $liste as $i => $s ) {
			if ( ( '' !== $id && ( $s['id'] ?? '' ) === $id )
				|| ( $s['url'] ?? '' ) === $url ) {
				$treffer = $i;
				break;
			}
		}

		$alt    = ( null !== $treffer ) ? $liste[ $treffer ] : array();
		$token  = trim( (string) ( $werte['token'] ?? '' ) );
		$neuer  = array(
			'id'        => $alt['id'] ?? self::kennung( $url, $user ),
			'name'      => '' !== $name ? $name : ( $alt['name'] ?? wp_parse_url( $url, PHP_URL_HOST ) ),
			'url'       => $url,
			'user'      => $user,
			// Leeres Feld behält den bisherigen Token.
			'token'     => '' !== $token ? self::verschluesseln( $token ) : ( $alt['token'] ?? '' ),
			'status'    => $alt['status'] ?? '',
			'geprueft'  => $alt['geprueft'] ?? '',
			'wp'        => $alt['wp'] ?? '',
			'wpname'    => $alt['wpname'] ?? '',
			'kennung'   => $alt['kennung'] ?? '',
		);

		if ( null !== $treffer ) {
			$liste[ $treffer ] = $neuer;
		} else {
			$liste[] = $neuer;
		}

		update_option( self::OPTION, array_values( $liste ) );
		return $neuer;
	}

	/**
	 * Ergebnis einer Prüfung festhalten.
	 *
	 * @param string $id     Kennung.
	 * @param array  $stand  Felder: status, wp, wpname, kennung.
	 * @return void
	 */
	public static function stand( string $id, array $stand ): void {
		$liste = self::alle();
		foreach ( $liste as $i => $s ) {
			if ( ( $s['id'] ?? '' ) === $id ) {
				$liste[ $i ] = array_merge( $s, $stand, array( 'geprueft' => current_time( 'mysql' ) ) );
			}
		}
		update_option( self::OPTION, array_values( $liste ) );
	}

	/**
	 * Website entfernen.
	 *
	 * @param string $id Kennung.
	 * @return void
	 */
	public static function entfernen( string $id ): void {
		$liste = array_values(
			array_filter(
				self::alle(),
				static fn( $s ) => ( $s['id'] ?? '' ) !== $id
			)
		);
		update_option( self::OPTION, $liste );
	}

	/**
	 * Zugangsdaten einer Website im Klartext (nur zur Laufzeit).
	 *
	 * @param array $site Eintrag.
	 * @return array{url: string, user: string, token: string}
	 */
	public static function zugang( array $site ): array {
		return array(
			'url'   => (string) ( $site['url'] ?? '' ),
			'user'  => (string) ( $site['user'] ?? '' ),
			'token' => self::entschluesseln( (string) ( $site['token'] ?? '' ) ),
		);
	}
}
