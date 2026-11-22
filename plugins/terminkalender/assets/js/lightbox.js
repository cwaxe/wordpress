/**
 * Terminkalender – kleine Lightbox für Bilder (wird nur geladen, wenn WX Lightbox nicht aktiv ist).
 * Öffnet Links mit der Klasse „tk-zoom“; Bilder mit gleichem data-lightbox bilden eine Galerie.
 */
( function () {
	'use strict';

	var t = window.tkLightboxL10n || { close: 'Schließen', prev: 'Vorheriges Bild', next: 'Nächstes Bild' };
	var dlg, img, cap, btnPrev, btnNext;
	var list = [];
	var idx = 0;
	var touchX = null;

	function icon( path ) {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + path + '"/></svg>';
	}

	function ensure() {
		if ( dlg ) {
			return;
		}
		dlg = document.createElement( 'dialog' );
		dlg.className = 'tk-lb';
		dlg.innerHTML =
			'<button type="button" class="tk-lb__btn tk-lb__close" aria-label="' + t.close + '">' + icon( 'M6 6l12 12M18 6L6 18' ) + '</button>' +
			'<button type="button" class="tk-lb__btn tk-lb__prev" aria-label="' + t.prev + '">' + icon( 'M15 18l-6-6 6-6' ) + '</button>' +
			'<figure class="tk-lb__fig"><img class="tk-lb__img" alt=""><figcaption class="tk-lb__cap"></figcaption></figure>' +
			'<button type="button" class="tk-lb__btn tk-lb__next" aria-label="' + t.next + '">' + icon( 'M9 18l6-6-6-6' ) + '</button>';
		document.body.appendChild( dlg );

		img = dlg.querySelector( '.tk-lb__img' );
		cap = dlg.querySelector( '.tk-lb__cap' );
		btnPrev = dlg.querySelector( '.tk-lb__prev' );
		btnNext = dlg.querySelector( '.tk-lb__next' );

		dlg.querySelector( '.tk-lb__close' ).addEventListener( 'click', close );
		btnPrev.addEventListener( 'click', function () {
			step( -1 );
		} );
		btnNext.addEventListener( 'click', function () {
			step( 1 );
		} );
		dlg.addEventListener( 'click', function ( e ) {
			if ( e.target === dlg || e.target.classList.contains( 'tk-lb__fig' ) ) {
				close();
			}
		} );
		dlg.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft' ) {
				step( -1 );
			} else if ( e.key === 'ArrowRight' ) {
				step( 1 );
			}
		} );
		dlg.addEventListener( 'touchstart', function ( e ) {
			touchX = e.touches[ 0 ].clientX;
		}, { passive: true } );
		dlg.addEventListener( 'touchend', function ( e ) {
			if ( touchX === null ) {
				return;
			}
			var dx = e.changedTouches[ 0 ].clientX - touchX;
			touchX = null;
			if ( Math.abs( dx ) > 50 ) {
				step( dx < 0 ? 1 : -1 );
			}
		} );
	}

	function show( i ) {
		idx = ( i + list.length ) % list.length;
		var a = list[ idx ];
		var inner = a.querySelector( 'img' );
		var fig = a.closest( 'figure' );
		var caption = fig ? fig.querySelector( 'figcaption' ) : null;
		img.src = a.getAttribute( 'href' );
		img.alt = inner ? inner.getAttribute( 'alt' ) || '' : '';
		cap.textContent = caption ? caption.textContent : '';
		cap.hidden = ! cap.textContent;
		btnPrev.hidden = btnNext.hidden = list.length < 2;
	}

	function step( n ) {
		if ( list.length > 1 ) {
			show( idx + n );
		}
	}

	function close() {
		if ( dlg && dlg.open ) {
			dlg.close();
		}
		if ( img ) {
			img.removeAttribute( 'src' );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest ? e.target.closest( 'a.tk-zoom' ) : null;
		if ( ! a || e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1 ) {
			return;
		}
		e.preventDefault();
		ensure();

		var group = a.getAttribute( 'data-lightbox' ) || '';
		list = Array.prototype.filter.call( document.querySelectorAll( 'a.tk-zoom' ), function ( x ) {
			// Bilder aus dem (geschlossenen) Detailfenster des Kalenders nicht mit anderen mischen.
			return ( x.getAttribute( 'data-lightbox' ) || '' ) === group && ( x === a || ! x.closest( 'dialog' ) );
		} );
		show( Math.max( 0, list.indexOf( a ) ) );

		if ( typeof dlg.showModal === 'function' ) {
			dlg.showModal();
		} else {
			dlg.setAttribute( 'open', '' );
		}
	} );
}() );
