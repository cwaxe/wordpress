/**
 * LSV Wetter am Platz – Live-Aktualisierung jede Minute
 * (Wetter-Block, Einzelwerte, Pistenschild)
 */
( function () {
	'use strict';

	var cfg = window.lsvWetter;
	if ( ! cfg || ! window.fetch ) {
		return;
	}

	function holen( basis, params ) {
		var url = new URL( basis, window.location.href );
		Object.keys( params ).forEach( function ( k ) {
			url.searchParams.set( k, params[ k ] );
		} );
		url.searchParams.set( '_', Math.floor( Date.now() / 60000 ) );
		return fetch( url.toString(), { credentials: 'omit' } ).then( function ( r ) {
			return r.ok ? r.json() : null;
		} );
	}

	function ersetzen( el, html ) {
		var tmp = document.createElement( 'div' );
		tmp.innerHTML = html;
		if ( tmp.firstElementChild ) {
			el.replaceWith( tmp.firstElementChild );
		}
	}

	function bloecke() {
		document.querySelectorAll( '.lsv-wetter' ).forEach( function ( el ) {
			holen( cfg.rest, {
				kompakt: el.dataset.kompakt || 'nein',
				titel: el.dataset.titel || '',
				link: el.dataset.link || 'ja'
			} ).then( function ( j ) {
				if ( j && j.html ) {
					ersetzen( el, j.html );
				}
			} ).catch( function () {} );
		} );
	}

	function werte() {
		var spans = document.querySelectorAll( '.lsv-wert[data-feld]' );
		if ( ! spans.length || ! cfg.werte ) {
			return;
		}
		holen( cfg.werte, {} ).then( function ( j ) {
			if ( ! j || ! j.werte ) {
				return;
			}
			spans.forEach( function ( s ) {
				var w = j.werte[ s.dataset.feld ];
				var txt;
				if ( ! w ) {
					txt = s.dataset.leer || '–';
				} else if ( s.dataset.einheit === 'nein' || ! w[ 1 ] ) {
					txt = w[ 0 ];
				} else {
					txt = w[ 0 ] + ( w[ 1 ] === '°' ? '' : ' ' ) + w[ 1 ];
				}
				if ( s.textContent !== txt ) {
					s.textContent = txt;
				}
			} );
		} ).catch( function () {} );
	}

	function schilder() {
		if ( ! cfg.schild ) {
			return;
		}
		document.querySelectorAll( '.lsv-schild-wrap' ).forEach( function ( el ) {
			holen( cfg.schild, {
				wind: el.dataset.wind || 'nein',
				groesse: el.dataset.groesse || 'normal'
			} ).then( function ( j ) {
				if ( j && j.html ) {
					ersetzen( el, j.html );
				}
			} ).catch( function () {} );
		} );
	}

	function alles() {
		if ( document.hidden ) {
			return;
		}
		bloecke();
		werte();
		schilder();
	}

	setInterval( alles, 60000 );
	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden ) {
			alles();
		}
	} );
}() );
