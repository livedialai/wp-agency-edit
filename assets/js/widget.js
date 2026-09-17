/**
 * Chatfenster der Agentur.
 *
 * Gesperrt, bis die Bedienperson ihre Website gewählt und das Agentur-Passwort
 * eingegeben hat. Danach läuft eine Uhr mit: nach der eingestellten Ruhezeit
 * fällt die Sitzung von selbst zu. Website wechseln sperrt sofort wieder.
 */
( function () {
	'use strict';

	var knopf, fenster, sperre, arbeit, verlauf, form, eingabe, auswahl, neu, auf, passwort, meldung, name, uhr, aus;

	function el( id ) {
		return document.getElementById( id );
	}

	/**
	 * Nachricht anhängen.
	 *
	 * @param {string} text Inhalt.
	 * @param {string} art  'ich', 'agent', 'fehler', 'warte'.
	 * @return {HTMLElement}
	 */
	function zeige( text, art ) {
		var div = document.createElement( 'div' );
		div.className = 'wpaeg-nachricht wpaeg-' + art;
		div.textContent = text;
		verlauf.appendChild( div );
		verlauf.scrollTop = verlauf.scrollHeight;
		return div;
	}

	/**
	 * Anfrage an die Zentrale.
	 *
	 * @param {string} pfad    Pfad.
	 * @param {Object} koerper Körper oder null für GET.
	 * @return {Promise}
	 */
	function ruf( pfad, koerper ) {
		var get = ( koerper === null || koerper === undefined );
		return fetch( WPAEG.rest + pfad + ( get && koerper === null ? '' : '' ), {
			method: get ? 'GET' : 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': WPAEG.nonce, 'Content-Type': 'application/json' },
			body: get ? undefined : JSON.stringify( koerper )
		} ).then( function ( a ) {
			return a.json();
		} );
	}

	/** Wie heißt die gerade gewählte Website? */
	function gewaehlt() {
		return auswahl.options[ auswahl.selectedIndex ].text;
	}

	/** Sperrbildschirm zeigen. */
	function zeigeSperre( text ) {
		sperre.hidden = false;
		arbeit.hidden = true;
		aus.hidden = true;
		neu.hidden = true;
		uhr.hidden = true;
		meldung.textContent = text || '';
		name.textContent = gewaehlt();
		passwort.value = '';
		passwort.focus();
	}

	/**
	 * Arbeitsbereich zeigen und die Uhr starten.
	 *
	 * @param {number} restzeit Sekunden bis zur Sperre.
	 */
	function zeigeArbeit( restzeit ) {
		sperre.hidden = true;
		arbeit.hidden = false;
		aus.hidden = false;
		neu.hidden = false;
		meldung.textContent = '';
		uhr.hidden = false;
		var rest = restzeit;
		uhr.textContent = '';
		if ( window.wpaegTicker ) {
			clearInterval( window.wpaegTicker );
		}
		function malen() {
			if ( rest <= 0 ) {
				clearInterval( window.wpaegTicker );
				window.wpaegTicker = null;
				zeigeSperre( 'Die Sitzung ist nach der Ruhezeit zugefallen. Bitte erneut entsperren.' );
				return;
			}
			var m = Math.floor( rest / 60 );
			var s = rest % 60;
			uhr.textContent = '⏱ ' + m + ':' + ( s < 10 ? '0' : '' ) + s;
			rest--;
		}
		malen();
		window.wpaegTicker = setInterval( malen, 1000 );
		if ( ! verlauf.childElementCount ) {
			zeige( 'Bereit. Was soll auf „' + gewaehlt() + '" geändert werden?', 'agent' );
		}
		eingabe.focus();
	}

	/** Zustand beim Öffnen und nach jedem Seitenwechsel. */
	function zustand() {
		name.textContent = gewaehlt();
		ruf( '/status?site=' + encodeURIComponent( auswahl.value ), null )
			.then( function ( d ) {
				if ( d && d.entsperrt ) {
					zeigeArbeit( d.restzeit );
				} else {
					zeigeSperre( d && d.restversuche < 5 ? 'Bitte entsperren — noch ' + d.restversuche + ' Versuche.' : '' );
				}
			} )
			.catch( function () {
				zeigeSperre( 'Keine Verbindung zur Zentrale.' );
			} );
	}

	function start() {
		knopf = el( 'wpaeg-knopf' );
		fenster = el( 'wpaeg-fenster' );
		sperre = el( 'wpaeg-sperre' );
		arbeit = el( 'wpaeg-arbeit' );
		verlauf = el( 'wpaeg-verlauf' );
		form = el( 'wpaeg-form' );
		eingabe = el( 'wpaeg-eingabe' );
		auswahl = el( 'wpaeg-site' );
		neu = el( 'wpaeg-neu' );
		auf = el( 'wpaeg-auf' );
		passwort = el( 'wpaeg-passwort' );
		meldung = el( 'wpaeg-meldung' );
		name = el( 'wpaeg-name' );
		uhr = el( 'wpaeg-uhr' );
		aus = el( 'wpaeg-aus' );
		if ( ! knopf || ! fenster ) {
			return;
		}

		knopf.addEventListener( 'click', function () {
			var zuklappen = ! fenster.hasAttribute( 'hidden' );
			if ( zuklappen ) {
				fenster.setAttribute( 'hidden', '' );
			} else {
				fenster.removeAttribute( 'hidden' );
				zustand();
			}
			knopf.setAttribute( 'aria-expanded', zuklappen ? 'false' : 'true' );
		} );

		// Entsperren
		auf.addEventListener( 'click', function () {
			var p = passwort.value;
			if ( ! p ) {
				meldung.textContent = 'Bitte das Agentur-Passwort eingeben.';
				return;
			}
			auf.disabled = true;
			ruf( '/unlock', { site: auswahl.value, passwort: p } )
				.then( function ( d ) {
					auf.disabled = false;
					if ( d && d.entsperrt ) {
						verlauf.innerHTML = '';
						zeigeArbeit( d.restzeit );
					} else {
						passwort.value = '';
						meldung.textContent = ( d && d.fehler ) ? d.fehler : 'Entsperren fehlgeschlagen.';
						passwort.focus();
					}
				} )
				.catch( function () {
					auf.disabled = false;
					meldung.textContent = 'Keine Verbindung zur Zentrale.';
				} );
		} );
		passwort.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				auf.click();
			}
		} );

		// Abmelden
		aus.addEventListener( 'click', function () {
			ruf( '/lock', {} ).then( function () {
				verlauf.innerHTML = '';
				zeigeSperre( 'Abgemeldet.' );
			} );
		} );

		// Website wechseln sperrt sofort
		auswahl.addEventListener( 'change', function () {
			verlauf.innerHTML = '';
			ruf( '/lock', {} ).then( function () {
				zeigeSperre( 'Website gewechselt — bitte erneut entsperren.' );
			} );
		} );

		// Neues Gespräch
		neu.addEventListener( 'click', function () {
			ruf( '/reset', { site: auswahl.value } );
			verlauf.innerHTML = '';
			zeige( 'Neues Gespräch. Was soll auf „' + gewaehlt() + '" geändert werden?', 'agent' );
		} );

		// Senden
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var text = eingabe.value.trim();
			if ( ! text ) {
				return;
			}
			zeige( text, 'ich' );
			eingabe.value = '';
			var warte = zeige( '… arbeitet auf ' + gewaehlt(), 'warte' );
			ruf( '/chat', { site: auswahl.value, nachricht: text } )
				.then( function ( d ) {
					warte.remove();
					if ( d && d.gesperrt ) {
						zeigeSperre( 'Die Sitzung war zugefallen. Bitte erneut entsperren.' );
					} else if ( d && d.fehler ) {
						zeige( d.fehler, 'fehler' );
					} else if ( d && d.antwort ) {
						zeige( d.antwort, 'agent' );
					} else {
						zeige( 'Unerwartete Antwort.', 'fehler' );
					}
				} )
				.catch( function ( f ) {
					warte.remove();
					zeige( 'Verbindung fehlgeschlagen: ' + f.message, 'fehler' );
				} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
