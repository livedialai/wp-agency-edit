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
	var einrichten, anmelden, neu1, neu2, setzen, einrichtenMeldung, wechseln, aendern, alt, neu3, neu4, aendernKnopf, aendernMeldung;

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

	/**
	 * Ersteinrichtung zeigen: Agentur-Passwort festlegen.
	 *
	 * @param {string} text Meldung.
	 */
	function zeigeEinrichten( text ) {
		sperre.hidden = false;
		arbeit.hidden = true;
		aus.hidden = true;
		neu.hidden = true;
		uhr.hidden = true;
		einrichten.hidden = false;
		anmelden.hidden = true;
		einrichtenMeldung.textContent = text || '';
		neu1.value = '';
		neu2.value = '';
		neu1.focus();
	}

	/** Sperrbildschirm zeigen. */
	function zeigeSperre( text ) {
		sperre.hidden = false;
		einrichten.hidden = true;
		anmelden.hidden = false;
		aendern.hidden = true;
		aendernMeldung.textContent = '';
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
				if ( d && d.eingerichtet === false ) {
					zeigeEinrichten( '' );
				} else if ( d && d.entsperrt ) {
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
		einrichten = el( 'wpaeg-einrichten' );
		anmelden = el( 'wpaeg-anmelden' );
		neu1 = el( 'wpaeg-neu1' );
		neu2 = el( 'wpaeg-neu2' );
		setzen = el( 'wpaeg-setzen' );
		einrichtenMeldung = el( 'wpaeg-einrichten-meldung' );
		wechseln = el( 'wpaeg-wechseln' );
		aendern = el( 'wpaeg-aendern' );
		alt = el( 'wpaeg-alt' );
		neu3 = el( 'wpaeg-neu3' );
		neu4 = el( 'wpaeg-neu4' );
		aendernKnopf = el( 'wpaeg-aendern-knopf' );
		aendernMeldung = el( 'wpaeg-aendern-meldung' );
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

		// Ersteinrichtung: Passwort festlegen
		setzen.addEventListener( 'click', function () {
			setzen.disabled = true;
			ruf( '/setup', { neu: neu1.value, wiederholung: neu2.value } )
				.then( function ( d ) {
					setzen.disabled = false;
					if ( d && d.eingerichtet ) {
						zeigeSperre( 'Passwort gesetzt. Bitte jetzt eingeben.' );
					} else {
						einrichtenMeldung.textContent = ( d && d.fehler ) ? d.fehler : 'Festlegen fehlgeschlagen.';
					}
				} )
				.catch( function () {
					setzen.disabled = false;
					einrichtenMeldung.textContent = 'Keine Verbindung zur Zentrale.';
				} );
		} );
		neu2.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				setzen.click();
			}
		} );

		// Passwort ändern
		wechseln.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			aendern.hidden = ! aendern.hidden;
			aendernMeldung.textContent = '';
			if ( ! aendern.hidden ) {
				alt.value = neu3.value = neu4.value = '';
				alt.focus();
			}
		} );
		aendernKnopf.addEventListener( 'click', function () {
			aendernKnopf.disabled = true;
			ruf( '/passwort', { bisher: alt.value, neu: neu3.value, wiederholung: neu4.value } )
				.then( function ( d ) {
					aendernKnopf.disabled = false;
					if ( d && d.geaendert ) {
						zeigeSperre( 'Passwort geändert. Bitte mit dem neuen Passwort entsperren.' );
					} else {
						aendernMeldung.textContent = ( d && d.fehler ) ? d.fehler : 'Ändern fehlgeschlagen.';
					}
				} )
				.catch( function () {
					aendernKnopf.disabled = false;
					aendernMeldung.textContent = 'Keine Verbindung zur Zentrale.';
				} );
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
