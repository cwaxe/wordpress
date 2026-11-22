/* Übernahme aus The Events Calendar in Schritten (Kalender → Einstellungen). */
( function () {
	'use strict';

	var knopf = document.getElementById( 'ek-import-start' );
	if ( ! knopf || ! window.ekImport ) {
		return;
	}
	var cfg = window.ekImport;
	var t = cfg.texte;
	var balken = document.getElementById( 'ek-import-balken' );
	var status = document.getElementById( 'ek-import-status' );
	var gesamt = 0;

	function fehler( text ) {
		status.textContent = t.fehler + ' ' + text;
		knopf.disabled = false;
	}

	function schritt( name, ab ) {
		var daten = new FormData();
		daten.append( 'action', 'ek_tec_import' );
		daten.append( 'nonce', cfg.nonce );
		daten.append( 'schritt', name );
		daten.append( 'ab', String( ab ) );

		fetch( cfg.ajax, { method: 'POST', body: daten, credentials: 'same-origin' } )
			.then( function ( r ) {
				if ( ! r.ok ) {
					throw new Error( 'HTTP ' + r.status );
				}
				return r.json();
			} )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					fehler( res && res.data ? res.data : '?' );
					return;
				}
				var d = res.data;
				gesamt = d.gesamt || gesamt;
				if ( gesamt ) {
					balken.value = Math.round( ( d.erledigt / gesamt ) * 100 );
				}
				status.textContent = d.nachricht || '';
				if ( d.weiter ) {
					schritt( d.weiter, d.ab || 0 );
					return;
				}
				balken.value = 100;
				status.textContent = t.fertig.replace( '%d', d.erledigt );
				knopf.disabled = false;
				document.getElementById( 'ek-import-danach' ).hidden = false;
			} )
			.catch( function ( e ) {
				fehler( e.message );
			} );
	}

	knopf.addEventListener( 'click', function () {
		if ( ! window.confirm( t.frage ) ) {
			return;
		}
		knopf.disabled = true;
		document.getElementById( 'ek-import-fortschritt' ).hidden = false;
		balken.value = 0;
		status.textContent = t.laeuft;
		schritt( 'begriffe', 0 );
	} );
}() );
