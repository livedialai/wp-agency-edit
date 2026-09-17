/**
 * Chatfenster der Agentur.
 *
 * Ein Fenster, eine Website-Auswahl, ein Verlauf. Der schwere Teil läuft auf
 * dem Server: die Zentrale holt die Fähigkeiten der gewählten Website und
 * führt die Werkzeugaufrufe dort aus.
 */
( function () {
	'use strict';

	var knopf, fenster, form, eingabe, verlauf, auswahl, neu;

	function el( id ) {
		return document.getElementById( id );
	}

	/**
	 * Eine Nachricht anhängen.
	 *
	 * @param {string} text  Inhalt.
	 * @param {string} art   'ich', 'agent' oder 'fehler'.
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
	 * @param {string} pfad  Pfad.
	 * @param {Object} daten Körper.
	 * @return {Promise}
	 */
	function ruf( pfad, daten ) {
		return fetch( WPAEG.rest + pfad, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': WPAEG.nonce
			},
			body: JSON.stringify( daten )
		} ).then( function ( a ) {
			return a.json();
		} );
	}

	function start() {
		knopf = el( 'wpaeg-knopf' );
		fenster = el( 'wpaeg-fenster' );
		form = el( 'wpaeg-form' );
		eingabe = el( 'wpaeg-eingabe' );
		verlauf = el( 'wpaeg-verlauf' );
		auswahl = el( 'wpaeg-site' );
		neu = el( 'wpaeg-neu' );
		if ( ! knopf || ! fenster ) {
			return;
		}

		knopf.addEventListener( 'click', function () {
			var zu = fenster.hasAttribute( 'hidden' );
			if ( zu ) {
				fenster.removeAttribute( 'hidden' );
				eingabe.focus();
			} else {
				fenster.setAttribute( 'hidden', '' );
			}
			knopf.setAttribute( 'aria-expanded', zu ? 'true' : 'false' );
		} );

		if ( neu ) {
			neu.addEventListener( 'click', function () {
				ruf( '/reset', { site: auswahl.value } );
				verlauf.innerHTML = '';
				zeige( 'Neues Gespräch. Was soll auf ' + auswahl.options[ auswahl.selectedIndex ].text + ' geändert werden?', 'agent' );
			} );
		}

		if ( auswahl ) {
			auswahl.addEventListener( 'change', function () {
				verlauf.innerHTML = '';
				zeige( 'Website gewechselt: ' + auswahl.options[ auswahl.selectedIndex ].text, 'agent' );
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var text = eingabe.value.trim();
			if ( ! text ) {
				return;
			}
			zeige( text, 'ich' );
			eingabe.value = '';
			var warte = zeige( '… arbeitet auf ' + auswahl.options[ auswahl.selectedIndex ].text, 'warte' );

			ruf( '/chat', { site: auswahl.value, nachricht: text } )
				.then( function ( d ) {
					warte.remove();
					if ( d && d.fehler ) {
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
