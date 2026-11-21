/**
 * LSV Flugplatz – Navigation
 * Mobiles Menü und Untermenüs (Touch, Tastatur, Klick außerhalb)
 */
( function () {
	'use strict';

	var knopf = document.querySelector( '.menue-knopf' );
	var nav = document.getElementById( 'hauptmenue' );
	if ( ! nav ) {
		return;
	}

	function schliesseAlleUnter( ausser ) {
		nav.querySelectorAll( '.lsv-sub-toggle[aria-expanded="true"]' ).forEach( function ( b ) {
			if ( b !== ausser && ! b.closest( 'li' ).contains( ausser ) ) {
				b.setAttribute( 'aria-expanded', 'false' );
				b.closest( 'li' ).classList.remove( 'offen' );
			}
		} );
	}

	// Hauptmenü auf dem Handy
	if ( knopf ) {
		knopf.addEventListener( 'click', function () {
			var offen = knopf.getAttribute( 'aria-expanded' ) === 'true';
			knopf.setAttribute( 'aria-expanded', offen ? 'false' : 'true' );
			document.body.classList.toggle( 'menue-offen', ! offen );
		} );
	}

	// Untermenüs
	nav.querySelectorAll( '.lsv-sub-toggle' ).forEach( function ( b ) {
		b.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var li = b.closest( 'li' );
			var offen = b.getAttribute( 'aria-expanded' ) === 'true';
			schliesseAlleUnter( b );
			b.setAttribute( 'aria-expanded', offen ? 'false' : 'true' );
			b.setAttribute( 'aria-label', offen ? 'Untermenü öffnen' : 'Untermenü schließen' );
			li.classList.toggle( 'offen', ! offen );
		} );
	} );

	// Klick außerhalb schließt Untermenüs
	document.addEventListener( 'click', function ( e ) {
		if ( ! nav.contains( e.target ) ) {
			schliesseAlleUnter( null );
		}
	} );

	// Escape schließt alles
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}
		schliesseAlleUnter( null );
		if ( knopf && document.body.classList.contains( 'menue-offen' ) ) {
			knopf.setAttribute( 'aria-expanded', 'false' );
			document.body.classList.remove( 'menue-offen' );
			knopf.focus();
		}
	} );

	// Beim Wechsel auf Desktop mobiles Menü zurücksetzen
	window.addEventListener( 'resize', function () {
		if ( window.innerWidth > 900 && document.body.classList.contains( 'menue-offen' ) ) {
			document.body.classList.remove( 'menue-offen' );
			if ( knopf ) {
				knopf.setAttribute( 'aria-expanded', 'false' );
			}
		}
	} );
}() );
