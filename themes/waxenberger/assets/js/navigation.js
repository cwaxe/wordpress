( function () {
	var nav = document.getElementById( 'site-navigation' );
	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/* Einblenden des Inhalts (wie scrollReveal auf der alten Seite):
	   erst starten, wenn die Seite wirklich sichtbar ist – nicht schon beim Vorladen im Hintergrund */
	( function () {
		var root = document.documentElement;
		if ( ! root.classList.contains( 'wx-reveal-on' ) ) {
			return;
		}
		var done = false;
		var reveal = function () {
			if ( done ) {
				return;
			}
			done = true;
			/* zwei Frames warten, damit der Ausgangszustand sicher gezeichnet ist */
			requestAnimationFrame( function () {
				requestAnimationFrame( function () {
					root.classList.add( 'wx-revealed' );
				} );
			} );
		};
		var tryReveal = function () {
			if ( document.prerendering || document.visibilityState !== 'visible' ) {
				return;
			}
			reveal();
		};
		document.addEventListener( 'prerenderingchange', tryReveal );
		document.addEventListener( 'visibilitychange', tryReveal );
		tryReveal();
		/* Sicherheitsnetz: spätestens nach 4 Sekunden ist alles sichtbar */
		setTimeout( function () { root.classList.add( 'wx-revealed' ); }, 4000 );
	} )();

	/* Mobiles Menü auf- und zuklappen */
	if ( nav ) {
		var button = nav.querySelector( '.menu-toggle' );
		if ( button ) {
			button.addEventListener( 'click', function () {
				var open = nav.classList.toggle( 'is-open' );
				button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}
	}

	/* Menü bleibt beim Scrollen oben stehen (wie stickUp auf der alten Seite) */
	if ( nav ) {
		var placeholder = document.createElement( 'div' );
		placeholder.style.display = 'none';
		nav.parentNode.insertBefore( placeholder, nav );

		var navTop = 0;
		var measure = function () {
			if ( nav.classList.contains( 'is-stuck' ) ) {
				return;
			}
			var adminBar = document.getElementById( 'wpadminbar' );
			var offset = adminBar && window.innerWidth > 782 ? adminBar.offsetHeight : 0;
			navTop = nav.getBoundingClientRect().top + window.scrollY - offset;
		};
		var update = function () {
			var desktop = window.innerWidth >= 768;
			var stuck = desktop && window.scrollY > navTop;
			if ( stuck && ! nav.classList.contains( 'is-stuck' ) ) {
				placeholder.style.height = nav.offsetHeight + 'px';
				placeholder.style.display = 'block';
				nav.classList.add( 'is-stuck' );
			} else if ( ! stuck && nav.classList.contains( 'is-stuck' ) ) {
				nav.classList.remove( 'is-stuck' );
				placeholder.style.display = 'none';
			}
		};
		measure();
		window.addEventListener( 'scroll', update, { passive: true } );
		window.addEventListener( 'resize', function () {
			nav.classList.remove( 'is-stuck' );
			placeholder.style.display = 'none';
			measure();
			update();
		} );
		window.addEventListener( 'load', function () { measure(); update(); } );
		update();
	}

	/* "Nach oben"-Pfeil: ab 200 px einblenden, weich nach oben scrollen */
	var toTop = document.querySelector( '.back-to-top' );
	if ( toTop ) {
		var hideTimer;
		var onScroll = function () {
			var show = window.scrollY > 200;
			clearTimeout( hideTimer );
			if ( show ) {
				toTop.classList.add( 'is-shown' );
				requestAnimationFrame( function () { toTop.classList.add( 'is-visible' ); } );
			} else if ( toTop.classList.contains( 'is-shown' ) ) {
				toTop.classList.remove( 'is-visible' );
				hideTimer = setTimeout( function () { toTop.classList.remove( 'is-shown' ); }, 400 );
			}
		};
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll();

		toTop.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( reduceMotion ) {
				window.scrollTo( 0, 0 );
				return;
			}
			var start = window.scrollY;
			var t0 = null;
			var duration = 600; /* jQuery "slow" */
			var step = function ( ts ) {
				if ( t0 === null ) { t0 = ts; }
				var p = Math.min( ( ts - t0 ) / duration, 1 );
				var ease = 0.5 - Math.cos( p * Math.PI ) / 2; /* jQuery "swing" */
				window.scrollTo( 0, start * ( 1 - ease ) );
				if ( p < 1 ) { requestAnimationFrame( step ); }
			};
			requestAnimationFrame( step );
		} );
	}
} )();
