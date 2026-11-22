/* Terminkalender – Backend */
( function ( $ ) {
	'use strict';

	var cfg = window.tkAdmin || { weekdays: [], i18n: {} };
	var t = cfg.i18n || {};

	function fmt( tpl ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( tpl || '' ).replace( /%(\d\$)?[sd]/g, function ( m, pos ) {
			if ( pos ) {
				return args[ parseInt( pos, 10 ) - 1 ];
			}
			return args[ i++ ];
		} );
	}

	$( function () {
		/* ---------- Farbwähler (Kategorien) ---------- */
		if ( $.fn.wpColorPicker ) {
			$( '.tk-color' ).wpColorPicker();
		}

		/* ---------- Eingabemaske ---------- */
		var $start = $( '#tk-start-date' );
		if ( $start.length ) {
			var $end = $( '#tk-end-date' );
			var $allday = $( '#tk-allday' );
			var $repeat = $( '#tk-repeat' );
			var $sTime = $( '#tk-start-time' );
			var $eTime = $( '#tk-end-time' );

			var toggleAllday = function () {
				$( '.tk-time' ).prop( 'hidden', $allday.is( ':checked' ) );
			};

			var updateRepeat = function () {
				var v = $start.val();
				if ( ! v ) {
					return;
				}
				var p = v.split( '-' );
				var d = new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] );
				var wd = cfg.weekdays[ d.getDay() ] || '';
				var day = d.getDate();
				var nth = Math.ceil( day / 7 );
				var daysIn = new Date( d.getFullYear(), d.getMonth() + 1, 0 ).getDate();
				var isLast = day + 7 > daysIn;

				$repeat.find( 'option[value=weekly]' ).text( t.weekly );
				$repeat.find( 'option[value=weekly2]' ).text( t.weekly2 );
				$repeat.find( 'option[value=monthly_day]' ).text( fmt( t.monthlyDay, day ) );
				$repeat.find( 'option[value=monthly_nth]' ).text( fmt( t.monthlyNth, nth, wd ) ).prop( 'hidden', nth > 4 );
				$repeat.find( 'option[value=monthly_last]' ).text( fmt( t.monthlyLast, wd ) ).prop( 'hidden', ! isLast );

				if ( $repeat.find( 'option:selected' ).prop( 'hidden' ) ) {
					$repeat.val( isLast ? 'monthly_last' : 'monthly_nth' );
				}
			};

			var toggleSeries = function () {
				var v = $repeat.val();
				var on = !! v;
				$( '.tk-series' ).prop( 'hidden', ! on );
				$( '.tk-days' ).prop( 'hidden', v !== 'weekly' && v !== 'weekly2' );
				$( '#tk-until' ).prop( 'required', on );
			};

			// Wochentag des Beginns ist bei wöchentlichen Serien immer dabei.
			var lockStartDay = function () {
				var v = $start.val();
				var $boxes = $( '.tk-days input[type=checkbox]' );
				$boxes.prop( 'disabled', false ).closest( '.tk-day' ).removeClass( 'is-start' ).removeAttr( 'data-hint' );
				if ( ! v ) {
					return;
				}
				var p = v.split( '-' );
				var wd = new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] ).getDay();
				$boxes.filter( '[value="' + wd + '"]' )
					.prop( 'checked', true )
					.prop( 'disabled', true )
					.closest( '.tk-day' ).addClass( 'is-start' ).attr( 'data-hint', t.startDay || '' )
					.attr( 'title', t.startDay || '' );
			};

			$start.on( 'change', function () {
				if ( ! $end.val() || $end.val() < $start.val() ) {
					$end.val( $start.val() );
				}
				updateRepeat();
				lockStartDay();
			} );

			$sTime.on( 'change', function () {
				// Ende automatisch 2 Stunden später, wenn es davor liegt.
				if ( $sTime.val() && ( ! $eTime.val() || ( $end.val() === $start.val() && $eTime.val() <= $sTime.val() ) ) ) {
					var h = Math.min( 23, parseInt( $sTime.val().slice( 0, 2 ), 10 ) + 2 );
					$eTime.val( ( h < 10 ? '0' : '' ) + h + $sTime.val().slice( 2 ) );
				}
			} );

			$allday.on( 'change', toggleAllday );
			$repeat.on( 'change', toggleSeries );

			$( '.tk-picker' ).on( 'change', function () {
				var box = document.getElementById( $( this ).data( 'new' ) );
				if ( box ) {
					box.hidden = $( this ).val() !== 'new';
					if ( ! box.hidden ) {
						$( box ).find( 'input' ).first().trigger( 'focus' );
					}
				}
			} );

			toggleAllday();
			updateRepeat();
			toggleSeries();
			lockStartDay();
		}

		/* ---------- Import ---------- */
		var $import = $( '#tk-import-start' );
		if ( $import.length ) {
			var $bar = $( '#tk-import-bar' );
			var $status = $( '#tk-import-status' );
			var total = 0;

			var step = function ( stage, offset ) {
				$.post( cfg.ajax, {
					action: 'tk_import',
					nonce: cfg.nonce,
					stage: stage,
					offset: offset
				} ).done( function ( res ) {
					if ( ! res || ! res.success ) {
						$status.text( t.importError + ' ' + ( res && res.data ? res.data : '?' ) );
						$import.prop( 'disabled', false );
						return;
					}
					var d = res.data;
					total = d.total || total;
					if ( total ) {
						$bar.val( Math.round( ( d.done / total ) * 100 ) );
					}
					$status.text( d.message || '' );
					if ( d.next ) {
						step( d.next, d.offset || 0 );
					} else {
						$bar.val( 100 );
						$status.text( fmt( t.importDone, d.done ) );
						$import.prop( 'disabled', false );
						$( '#tk-import-after' ).prop( 'hidden', false );
					}
				} ).fail( function ( xhr ) {
					$status.text( t.importError + ' HTTP ' + xhr.status );
					$import.prop( 'disabled', false );
				} );
			};

			$import.on( 'click', function () {
				if ( ! window.confirm( t.confirm ) ) {
					return;
				}
				$import.prop( 'disabled', true );
				$( '#tk-import-progress' ).prop( 'hidden', false );
				$bar.val( 0 );
				$status.text( t.importing );
				step( 'terms', 0 );
			} );
		}
	} );
}( jQuery ) );
