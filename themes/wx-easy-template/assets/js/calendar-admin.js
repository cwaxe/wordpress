/* WX Easy Calendar – Eingabemaske im Backend */
( function ( $ ) {
	'use strict';

	var cfg = window.ekAdmin || { wochentage: [], texte: {} };
	var t = cfg.texte || {};

	function fmt( tpl ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( tpl || '' ).replace( /%(\d\$)?[sd]/g, function ( m, pos ) {
			return pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
		} );
	}

	$( function () {
		var $start = $( '#ek_start' );
		if ( ! $start.length ) {
			return;
		}
		var $ende = $( '#ek_ende' );
		var $ganz = $( '#ek_ganztags' );
		var $wdh = $( '#ek_wiederholen' );
		var $zeit = $( '#ek_zeit' );
		var $zeitEnde = $( '#ek_zeit_ende' );

		function datum() {
			var v = $start.val();
			if ( ! v ) {
				return null;
			}
			var p = v.split( '-' );
			return new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] );
		}

		function ganztags() {
			$( '.ek-uhrzeit' ).prop( 'hidden', $ganz.is( ':checked' ) );
		}

		function serie() {
			var v = $wdh.val();
			$( '.ek-serie' ).prop( 'hidden', ! v );
			$( '.ek-tage' ).prop( 'hidden', v !== 'weekly' && v !== 'weekly2' );
			$( '#ek_bis' ).prop( 'required', !! v );
		}

		function texte() {
			var d = datum();
			if ( ! d ) {
				return;
			}
			var wt = cfg.wochentage[ d.getDay() ] || '';
			var tag = d.getDate();
			var nth = Math.ceil( tag / 7 );
			var tageImMonat = new Date( d.getFullYear(), d.getMonth() + 1, 0 ).getDate();
			var letzter = tag + 7 > tageImMonat;
			$wdh.find( 'option[value=monthly_day]' ).text( fmt( t.monatTag, tag ) );
			$wdh.find( 'option[value=monthly_nth]' ).text( fmt( t.monatNter, nth, wt ) ).prop( 'hidden', nth > 4 );
			$wdh.find( 'option[value=monthly_last]' ).text( fmt( t.monatLetzter, wt ) ).prop( 'hidden', ! letzter );
			if ( $wdh.find( 'option:selected' ).prop( 'hidden' ) ) {
				$wdh.val( letzter ? 'monthly_last' : 'monthly_nth' );
			}
		}

		// Wochentag des Beginns ist bei wöchentlichen Serien immer dabei.
		function starttag() {
			var $boxen = $( '.ek-tage input[type=checkbox]' );
			$boxen.prop( 'disabled', false ).closest( '.ek-tag-wahl' ).removeClass( 'ist-start' ).removeAttr( 'title' );
			var d = datum();
			if ( ! d ) {
				return;
			}
			$boxen.filter( '[value="' + d.getDay() + '"]' )
				.prop( 'checked', true ).prop( 'disabled', true )
				.closest( '.ek-tag-wahl' ).addClass( 'ist-start' ).attr( 'title', t.starttag || '' );
		}

		$start.on( 'change', function () {
			if ( ! $ende.val() || $ende.val() < $start.val() ) {
				$ende.val( $start.val() );
			}
			texte();
			starttag();
		} );

		$zeit.on( 'change', function () {
			if ( $zeit.val() && ( ! $zeitEnde.val() || ( $ende.val() === $start.val() && $zeitEnde.val() <= $zeit.val() ) ) ) {
				var h = Math.min( 23, parseInt( $zeit.val().slice( 0, 2 ), 10 ) + 2 );
				$zeitEnde.val( ( h < 10 ? '0' : '' ) + h + $zeit.val().slice( 2 ) );
			}
		} );

		$ganz.on( 'change', ganztags );
		$wdh.on( 'change', serie );

		$( '.ek-auswahl' ).on( 'change', function () {
			var wert = $( this ).val();
			var self = this;
			[ [ 'neu', $( self ).data( 'neu' ) ], [ 'frei', $( self ).data( 'frei' ) ] ].forEach( function ( paar ) {
				var box = paar[ 1 ] ? document.getElementById( paar[ 1 ] ) : null;
				if ( box ) {
					var vorher = box.hidden;
					box.hidden = wert !== paar[ 0 ];
					if ( ! box.hidden && vorher ) {
						$( box ).find( 'input' ).first().trigger( 'focus' );
					}
				}
			} );
		} );

		ganztags();
		texte();
		serie();
		starttag();
	} );
}( jQuery ) );
