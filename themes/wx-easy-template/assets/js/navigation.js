/**
 * WX Easy Template – Navigation
 * - gleitender Indikator unter den Hauptpunkten
 * - Untermenüs per Tastatur/Touch öffnen
 * - mobiles Burger-Menü
 */
( function () {
	'use strict';

	var nav = document.getElementById( 'hauptmenue' );
	if ( ! nav ) {
		return;
	}

	var menu   = nav.querySelector( '.menu' );
	var toggle = document.querySelector( '.menu-toggle' );
	var mq     = window.matchMedia( '(max-width: 900px)' );
	var l10n   = window.dunkelNav || { expand: 'Untermenü öffnen', collapse: 'Untermenü schließen' };

	if ( ! menu ) {
		return;
	}

	/* ---------- Gleitender Indikator ---------- */
	var indicator = document.createElement( 'span' );
	indicator.className = 'nav-indicator';
	indicator.setAttribute( 'aria-hidden', 'true' );
	nav.appendChild( indicator );

	var topItems = Array.prototype.filter.call( menu.children, function ( el ) {
		return el.tagName === 'LI';
	} );

	function moveIndicator( li ) {
		if ( ! li || mq.matches ) {
			indicator.style.opacity = '0';
			return;
		}
		var link = li.querySelector( ':scope > a' );
		var pad  = 16;
		indicator.style.width     = Math.max( 0, link.offsetWidth - pad * 2 ) + 'px';
		indicator.style.transform = 'translateX(' + ( menu.offsetLeft + li.offsetLeft + pad ) + 'px)';
		indicator.style.opacity   = '1';
	}

	function currentItem() {
		return menu.querySelector( ':scope > .current-menu-item, :scope > .current-menu-ancestor, :scope > .current_page_item, :scope > .current_page_ancestor' );
	}

	function resetIndicator() {
		moveIndicator( currentItem() );
	}

	topItems.forEach( function ( li ) {
		li.addEventListener( 'mouseenter', function () { moveIndicator( li ); } );
		li.addEventListener( 'focusin', function () { moveIndicator( li ); } );
	} );
	menu.addEventListener( 'mouseleave', resetIndicator );
	menu.addEventListener( 'focusout', function ( e ) {
		if ( ! menu.contains( e.relatedTarget ) ) {
			resetIndicator();
		}
	} );

	// Ohne Übergang initial positionieren.
	indicator.style.transition = 'none';
	resetIndicator();
	requestAnimationFrame( function () {
		indicator.style.transition = '';
	} );
	window.addEventListener( 'resize', resetIndicator );
	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( resetIndicator );
	}

	/* ---------- Untermenü-Buttons ---------- */
	var parents = nav.querySelectorAll( '.menu-item-has-children, .page_item_has_children' );

	function setOpen( li, open ) {
		li.classList.toggle( 'is-open', open );
		var btn = li.querySelector( ':scope > .submenu-toggle' );
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			btn.setAttribute( 'aria-label', open ? l10n.collapse : l10n.expand );
		}
		if ( ! open ) {
			li.querySelectorAll( '.is-open' ).forEach( function ( child ) {
				setOpen( child, false );
			} );
		}
	}

	Array.prototype.forEach.call( parents, function ( li ) {
		var link = li.querySelector( ':scope > a' );
		var btn  = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'submenu-toggle';
		btn.setAttribute( 'aria-expanded', 'false' );
		btn.setAttribute( 'aria-label', l10n.expand );
		link.insertAdjacentElement( 'afterend', btn );

		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var open = ! li.classList.contains( 'is-open' );
			// Geschwister schließen
			Array.prototype.forEach.call( li.parentNode.children, function ( sib ) {
				if ( sib !== li && sib.classList ) {
					setOpen( sib, false );
				}
			} );
			setOpen( li, open );
		} );

		// Desktop-Touch: erster Tipp öffnet, zweiter folgt dem Link.
		link.addEventListener( 'touchend', function ( e ) {
			if ( mq.matches ) {
				return;
			}
			if ( ! li.classList.contains( 'is-open' ) ) {
				e.preventDefault();
				Array.prototype.forEach.call( li.parentNode.children, function ( sib ) {
					if ( sib !== li && sib.classList ) {
						setOpen( sib, false );
					}
				} );
				setOpen( li, true );
			}
		} );
	} );

	// Klick außerhalb schließt Untermenüs.
	document.addEventListener( 'click', function ( e ) {
		if ( ! nav.contains( e.target ) ) {
			nav.querySelectorAll( '.is-open' ).forEach( function ( li ) {
				setOpen( li, false );
			} );
		}
	} );

	// Escape schließt.
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}
		var openItems = nav.querySelectorAll( '.is-open' );
		if ( openItems.length ) {
			openItems.forEach( function ( li ) { setOpen( li, false ); } );
		} else if ( toggle && nav.classList.contains( 'toggled' ) ) {
			setMobile( false );
			toggle.focus();
		}
		if ( nav.contains( document.activeElement ) && ! mq.matches ) {
			document.activeElement.blur();
		}
	} );

	/* ---------- Mobiles Menü ---------- */
	function setMobile( open ) {
		nav.classList.toggle( 'toggled', open );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}

	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			setMobile( ! nav.classList.contains( 'toggled' ) );
		} );
	}

	mq.addEventListener( 'change', function () {
		if ( toggle ) {
			setMobile( false );
		}
		nav.querySelectorAll( '.is-open' ).forEach( function ( li ) { setOpen( li, false ); } );
		resetIndicator();
	} );
} )();
