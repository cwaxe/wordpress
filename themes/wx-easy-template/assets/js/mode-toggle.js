/**
 * WX Easy Template – Hell/Dunkel-Umschalter
 *
 * Ohne gespeicherte Wahl folgt das Design dem Standard aus dem Customizer
 * (bei „Automatisch“ der Geräteeinstellung – auch live, wenn sie sich ändert).
 * Ein Klick wechselt das Design. Landet man dabei wieder beim Standard,
 * wird die gespeicherte Wahl gelöscht, sodass wieder automatisch gewechselt wird.
 */
( function () {
	'use strict';

	var KEY  = 'dunkel-mode';
	var root = document.documentElement;
	var cfg  = window.dunkelMode || { fallback: 'auto', toLight: 'Zum hellen Design wechseln', toDark: 'Zum dunklen Design wechseln' };
	var mq   = window.matchMedia ? window.matchMedia( '(prefers-color-scheme: light)' ) : null;
	var btn  = document.querySelector( '.mode-toggle' );

	function systemMode() {
		return mq && mq.matches ? 'light' : 'dark';
	}

	function baseMode() {
		return cfg.fallback === 'auto' ? systemMode() : cfg.fallback;
	}

	function stored() {
		if ( ! btn ) {
			return null; // Umschalter ausgeblendet → gespeicherte Wahl ignorieren
		}
		try {
			var v = localStorage.getItem( KEY );
			return v === 'light' || v === 'dark' ? v : null;
		} catch ( e ) {
			return null;
		}
	}

	function save( mode ) {
		try {
			if ( mode === baseMode() ) {
				localStorage.removeItem( KEY );
			} else {
				localStorage.setItem( KEY, mode );
			}
		} catch ( e ) {}
	}

	function apply( mode ) {
		root.setAttribute( 'data-mode', mode );
		if ( btn ) {
			btn.setAttribute( 'aria-label', mode === 'light' ? cfg.toDark : cfg.toLight );
			btn.setAttribute( 'title', mode === 'light' ? cfg.toDark : cfg.toLight );
		}
	}

	apply( stored() || baseMode() );

	if ( btn ) {
		btn.addEventListener( 'click', function () {
			var next = root.getAttribute( 'data-mode' ) === 'light' ? 'dark' : 'light';
			save( next );
			apply( next );
		} );
	}

	// Geräteeinstellung ändert sich → folgen, solange keine eigene Wahl gespeichert ist.
	if ( mq ) {
		var onChange = function () {
			if ( ! stored() ) {
				apply( baseMode() );
			}
		};
		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', onChange );
		} else if ( mq.addListener ) {
			mq.addListener( onChange );
		}
	}

	// Wahl in anderen Tabs übernehmen.
	window.addEventListener( 'storage', function ( e ) {
		if ( e.key === KEY ) {
			apply( stored() || baseMode() );
		}
	} );
} )();
