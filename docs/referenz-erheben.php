<?php
/**
 * Erhebt den Ist-Zustand des Agentur-Plugins fuer die Funktionsreferenz.
 *
 * Aufruf auf der Agentur-Instanz:
 *   wp eval-file docs/referenz-erheben.php > /tmp/wpaeg-doku.json
 *
 * Alles kommt aus dem laufenden System: Klassen und Methoden ueber Reflexion,
 * Routen aus dem REST-Server, Optionen aus der Datenbank, Haken aus dem
 * Quelltext. Nichts wird von Hand gepflegt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ersten Absatz eines Docblocks holen, ohne Sternchen und Auszeichnungen.
 *
 * @param string|false $doku Docblock.
 * @return string
 */
function self_hilfe_absatz( $doku ): string {
	if ( ! $doku ) {
		return '';
	}
	$zeilen = array();
	foreach ( explode( "\n", $doku ) as $z ) {
		$z = trim( preg_replace( '#^\s*/?\*+/?\s?#', '', $z ) );
		if ( '' === $z || 0 === strpos( $z, '@' ) ) {
			if ( $zeilen ) {
				break;   // Absatz zu Ende
			}
			continue;
		}
		$zeilen[] = $z;
	}
	return trim( implode( ' ', $zeilen ) );
}

$wurzel = WPAEG_DIR;
$daten  = array(
	'zeit'     => current_time( 'mysql' ),
	'wp'       => get_bloginfo( 'version' ),
	'php'      => PHP_VERSION,
	'plugin'   => WPAEG_VERSION,
	'website'  => home_url(),
	'klassen'  => array(),
	'routen'   => array(),
	'optionen' => array(),
	'haken'    => array(),
	'dateien'  => array(),
);

/* --- Klassen und ihre oeffentlichen Methoden --------------------------- */

$klassen_dateien = glob( $wurzel . 'includes/class-*.php' );
$klassen_dateien[] = $wurzel . 'wp-agency-edit.php';

foreach ( $klassen_dateien as $datei ) {
	if ( ! file_exists( $datei ) ) {
		continue;
	}
	$inhalt = (string) file_get_contents( $datei );
	$rel    = str_replace( $wurzel, '', $datei );

	// Klassennamen aus dem Quelltext
	if ( ! preg_match_all( '/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/m', $inhalt, $treffer ) ) {
		continue;
	}
	foreach ( $treffer[1] as $klasse ) {
		if ( ! class_exists( $klasse ) ) {
			continue;
		}
		$r = new ReflectionClass( $klasse );
		$eintrag = array(
			'klasse'   => $klasse,
			'datei'    => ltrim( $rel, '/' ),
			'zweck'    => '',
			'methoden' => array(),
		);
		$eintrag['zweck'] = self_hilfe_absatz( $r->getDocComment() );
		foreach ( $r->getMethods( ReflectionMethod::IS_PUBLIC ) as $methode ) {
			if ( 0 === strpos( $methode->getName(), '__' ) ) {
				continue;
			}
			$kurz = self_hilfe_absatz( $methode->getDocComment() );
			$parameter = array();
			foreach ( $methode->getParameters() as $p ) {
				$parameter[] = ( $p->hasType() ? (string) $p->getType() . ' ' : '' ) . '$' . $p->getName();
			}
			$eintrag['methoden'][] = array(
				'name'      => $methode->getName(),
				'statisch'  => $methode->isStatic(),
				'parameter' => implode( ', ', $parameter ),
				'kurz'      => $kurz,
			);
		}
		usort( $eintrag['methoden'], static fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
		$daten['klassen'][] = $eintrag;
	}
}

/* --- REST-Routen ------------------------------------------------------- */

do_action( 'rest_api_init' );
foreach ( rest_get_server()->get_routes() as $pfad => $routen ) {
	if ( false === strpos( $pfad, '/wp-agency-edit/' ) ) {
		continue;
	}
	foreach ( $routen as $r ) {
		$methoden = isset( $r['methods'] ) ? (array) $r['methods'] : array();
		$namen    = array_keys( $methoden );
		// WordPress legt je Methode einen eigenen Eintrag an - zusammenfassen.
		sort( $namen );
		$schluessel = $pfad . '|' . implode( ',', $namen );
		if ( isset( $gesehen[ $schluessel ] ) ) {
			continue;
		}
		$gesehen[ $schluessel ] = true;
		$daten['routen'][] = array(
			'pfad'       => $pfad,
			'methoden'   => implode( ', ', $namen ),
			'geschuetzt' => isset( $r['permission_callback'] ),
			'parameter'  => ! empty( $r['args'] ) ? array_keys( (array) $r['args'] ) : array(),
		);
	}
	unset( $gesehen );
}

/* --- Optionen ----------------------------------------------------------- */

// Namen aus dem Quelltext ableiten: so erscheint auch eine Option, die noch
// gar nicht angelegt wurde.
$aus_quelle = array();

// Erst die Klassenkonstanten — viele Optionen heißen dort, nicht als Zeichenkette.
foreach ( get_declared_classes() as $klasse ) {
	if ( 0 !== strpos( $klasse, 'WP_Agency_Edit' ) ) {
		continue;
	}
	try {
		foreach ( ( new ReflectionClass( $klasse ) )->getConstants() as $konst => $wert ) {
			if ( is_string( $wert ) && 0 === strpos( $wert, 'wp_agency_edit_' ) ) {
				$aus_quelle[ $wert ] = true;
			}
		}
	} catch ( Throwable $e ) {
		continue;
	}
}
foreach ( array_merge(
	(array) glob( WPAEG_DIR . '*.php' ),
	(array) glob( WPAEG_DIR . 'includes/*.php' ),
	(array) glob( WPAEG_DIR . 'admin/*.php' )
) as $datei ) {
	$inhalt = (string) file_get_contents( $datei );
	if ( preg_match_all( '/(?:get|update|delete)_option\(\s*[\'"]([^\'"]+)[\'"]/', $inhalt, $t ) ) {
		foreach ( $t[1] as $n ) {
			$aus_quelle[ trim( $n ) ] = true;
		}
	}
}
foreach ( $aus_quelle as $name => $_ ) {
	if ( 0 === strpos( $name, '_transient' ) ) {
		unset( $aus_quelle[ $name ] );
	}
}
ksort( $aus_quelle );
foreach ( array_keys( $aus_quelle ) as $name ) {
	$wert = get_option( $name, null );
	$daten['optionen'][] = array(
		'name'      => $name,
		'typ'       => is_array( $wert ) ? 'Feldgruppe' : gettype( $wert ),
		'felder'    => is_array( $wert ) ? implode( ', ', array_keys( $wert ) ) : '',
		'vorhanden' => null !== $wert,
		'groesse'   => null !== $wert ? strlen( maybe_serialize( $wert ) ) : 0,
	);
}

global $wpdb;
$muster = array( '_transient_wpaeg_%', '_transient_timeout_wpaeg_%' );
foreach ( $muster as $m ) {
	$zeilen = $wpdb->get_results(
		$wpdb->prepare( "SELECT option_name, LENGTH(option_value) AS groesse FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $m )
	);
	foreach ( (array) $zeilen as $z ) {
		$daten['optionen'][] = array(
			'name'     => $z->option_name,
			'groesse'  => (int) $z->groesse,
			'vorhanden' => true,
		);
	}
}
// Doppelte entfernen, Reihenfolge festlegen.
$rein = array();
foreach ( $daten['optionen'] as $o ) {
	$rein[ $o['name'] ] = $o;
}
ksort( $rein );
$daten['optionen'] = array_values( $rein );

/* --- Haken aus dem Quelltext ------------------------------------------- */

foreach ( array_merge( glob( $wurzel . 'includes/*.php' ), array( $wurzel . 'wp-agency-edit.php' ) ) as $datei ) {
	$inhalt = (string) file_get_contents( $datei );
	if ( preg_match_all( '/add_(action|filter)\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(array\([^)]*\)|[a-zA-Z0-9_\\\\:>$\-]+\s*::\s*[a-zA-Z0-9_]+|[a-zA-Z0-9_]+)/', $inhalt, $t, PREG_SET_ORDER ) ) {
		foreach ( $t as $e ) {
			$r2 = trim( preg_replace( '/\s+/', ' ', $e[3] ) );
			// array( $this, 'menue' ) -> $this->menue()
			if ( preg_match( '/array\(\s*\$this\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $r2, $mm ) ) {
				$r2 = '$this->' . $mm[1] . '()';
			} elseif ( preg_match( '/array\(\s*([A-Za-z0-9_]+)::class\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $r2, $mm ) ) {
				$r2 = $mm[1] . '::' . $mm[2] . '()';
			} elseif ( preg_match( '/array\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $r2, $mm ) ) {
				$r2 = $mm[1] . '::' . $mm[2] . '()';
			}
			$daten['haken'][] = array(
				'art'      => 'action' === $e[1] ? 'Aktion' : 'Filter',
				'haken'    => $e[2],
				'rueckruf' => $r2,
				'datei'    => ltrim( str_replace( $wurzel, '', $datei ), '/' ),
			);
		}
	}
}

/* --- Verwendete Anfrageparameter --------------------------------------- */

$params = array();
foreach ( array_merge( glob( $wurzel . 'includes/*.php' ), array( $wurzel . 'wp-agency-edit.php' ) ) as $datei ) {
	$inhalt = (string) file_get_contents( $datei );
	if ( preg_match_all( '/get_param\(\s*[\'"]([^\'"]+)[\'"]/', $inhalt, $m ) ) {
		foreach ( $m[1] as $p ) {
			$params[ $p ] = ( $params[ $p ] ?? 0 ) + 1;
		}
	}
}
$daten['parameter'] = $params;

/* --- Dateien und Zeilen ------------------------------------------------ */

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $wurzel, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	$pfad = (string) $f->getPathname();
	if ( false !== strpos( $pfad, '/.git/' ) || false !== strpos( $pfad, '/docs/' ) ) {
		continue;
	}
	$zeilen = 0;
	if ( preg_match( '/\.(php|js|css|md)$/', $pfad ) ) {
		$zeilen = count( file( $pfad ) );
	}
	$daten['dateien'][] = array(
		'pfad'   => ltrim( str_replace( $wurzel, '', $pfad ), '/' ),
		'bytes'  => $f->getSize(),
		'zeilen' => $zeilen,
	);
}
usort( $daten['dateien'], static fn( $a, $b ) => strcmp( $a['pfad'], $b['pfad'] ) );

echo wp_json_encode( $daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
