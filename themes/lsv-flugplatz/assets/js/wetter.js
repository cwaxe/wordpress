/**
 * LSV Flugplatz – Wetter-Block jede Minute aktualisieren
 */
( function () {
	'use strict';

	if ( ! window.lsvWetter || ! window.fetch ) {
		return;
	}

	function aktualisieren() {
		if ( document.hidden ) {
			return;
		}
		document.querySelectorAll( '.lsv-wetter' ).forEach( function ( el ) {
			var url = new URL( window.lsvWetter.rest, window.location.href );
			url.searchParams.set( 'kompakt', el.dataset.kompakt || 'nein' );
			url.searchParams.set( 'titel', el.dataset.titel || '' );
			url.searchParams.set( 'link', el.dataset.link || 'ja' );
			url.searchParams.set( '_', Math.floor( Date.now() / 60000 ) );

			fetch( url.toString(), { credentials: 'omit' } )
				.then( function ( r ) { return r.ok ? r.json() : null; } )
				.then( function ( j ) {
					if ( ! j || ! j.html ) {
						return;
					}
					var tmp = document.createElement( 'div' );
					tmp.innerHTML = j.html;
					var neu = tmp.firstElementChild;
					if ( neu ) {
						el.replaceWith( neu );
					}
				} )
				.catch( function () {} );
		} );
	}

	setInterval( aktualisieren, 60000 );
	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden ) {
			aktualisieren();
		}
	} );
}() );
