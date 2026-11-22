/**
 * Veranstaltungskalender – Monats- und Listenansicht.
 * Die Termine kommen als JSON vom Server (TECM_Data), alles andere passiert hier.
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------ *
	 * Hilfsfunktionen
	 * ------------------------------------------------------------------ */

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}
	function ymd( d ) {
		return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
	}
	function parseYmd( s ) {
		var p = s.split( '-' );
		return new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] );
	}
	function addDays( d, n ) {
		var x = new Date( d.getFullYear(), d.getMonth(), d.getDate() );
		x.setDate( x.getDate() + n );
		return x;
	}
	function firstOfMonth( d ) {
		return new Date( d.getFullYear(), d.getMonth(), 1 );
	}
	function addMonths( d, n ) {
		return new Date( d.getFullYear(), d.getMonth() + n, 1 );
	}
	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}
	function fmt( tpl, value ) {
		return String( tpl ).replace( /%[sd]/, value );
	}
	function datePart( s ) {
		return s.slice( 0, 10 );
	}
	function timePart( s ) {
		return s.slice( 11, 16 );
	}

	var ICONS = {
		prev: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6"/></svg>',
		next: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6"/></svg>',
		search: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
		clock: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
		pin: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>',
		person: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>',
		close: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		check: '<svg class="tk-chip__check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12l5 5L20 7"/></svg>',
		chevron: '<svg class="tk-row__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6"/></svg>'
	};

	var STORAGE_KEY = 'tkKalenderKategorien';
	var COMPACT_WIDTH = 720;

	/* ------------------------------------------------------------------ *
	 * Kalender
	 * ------------------------------------------------------------------ */

	function Calendar( root ) {
		this.root = root;
		this.cfg = JSON.parse( root.querySelector( '.tk-config' ).textContent );
		this.t = this.cfg.i18n;
		this.cats = {};
		this.catOrder = [];
		this.cache = {};
		this.pending = {};
		this.byId = {};

		var self = this;
		this.cfg.categories.forEach( function ( c ) {
			self.cats[ c.id ] = c;
			self.catOrder.push( c.id );
		} );

		if ( this.cfg.initial ) {
			this.store( this.cfg.initial.start, this.cfg.initial.end, this.cfg.initial.events );
		}

		var params = new URLSearchParams( window.location.search );
		var today = parseYmd( this.cfg.today );

		this.today = today;
		var a = params.get( 'ansicht' );
		this.view = { liste: 'list', woche: 'week', monat: 'month' }[ a ] || this.cfg.view;
		this.month = firstOfMonth( today );
		var m = params.get( 'monat' );
		if ( m && /^\d{4}-\d{2}$/.test( m ) ) {
			this.month = parseYmd( m + '-01' );
		}
		this.weekStart = this.startOfWeek( today );
		var w = params.get( 'woche' );
		if ( w && /^\d{4}-\d{2}-\d{2}$/.test( w ) ) {
			this.weekStart = this.startOfWeek( parseYmd( w ) );
		}
		this.selected = null;
		this.query = '';
		this.showPast = false;
		this.active = this.loadActive();

		// ?kategorie=slug (z. B. nach Weiterleitung einer Kategorie-Seite).
		var catSlug = params.get( 'kategorie' );
		if ( catSlug ) {
			this.cfg.categories.forEach( function ( c ) {
				if ( c.slug === catSlug ) {
					self.active = [ c.id ];
				}
			} );
		}

		this.build();
		this.setupHover();
		this.observe();
		this.render();
	}

	Calendar.prototype = {

		/* ---------- Zustand ---------- */

		loadActive: function () {
			try {
				var raw = window.sessionStorage.getItem( STORAGE_KEY );
				var arr = raw ? JSON.parse( raw ) : [];
				return Array.isArray( arr ) ? arr.filter( function ( id ) {
					return typeof id === 'number';
				} ) : [];
			} catch ( e ) {
				return [];
			}
		},

		saveActive: function () {
			try {
				window.sessionStorage.setItem( STORAGE_KEY, JSON.stringify( this.active ) );
			} catch ( e ) {}
		},

		updateUrl: function () {
			if ( ! window.history || ! window.history.replaceState ) {
				return;
			}
			var url = new URL( window.location.href );
			url.searchParams.set( 'ansicht', { list: 'liste', week: 'woche' }[ this.view ] || 'monat' );
			if ( this.view === 'week' ) {
				url.searchParams.set( 'woche', ymd( this.weekStart ) );
				url.searchParams.delete( 'monat' );
			} else {
				url.searchParams.set( 'monat', this.month.getFullYear() + '-' + pad( this.month.getMonth() + 1 ) );
				url.searchParams.delete( 'woche' );
			}
			url.searchParams.delete( 'kategorie' );
			window.history.replaceState( null, '', url.toString() );
		},

		/* ---------- Daten ---------- */

		startOfWeek: function ( d ) {
			return addDays( d, -( ( d.getDay() - this.cfg.startOfWeek + 7 ) % 7 ) );
		},

		/** Zeitraum der aktuellen Ansicht. */
		currentRange: function () {
			if ( this.view === 'week' ) {
				return { start: ymd( this.weekStart ), end: ymd( addDays( this.weekStart, 6 ) ) };
			}
			return this.gridRange( this.month );
		},

		/** Bereits geladene Termine, die den Zeitraum vollständig abdecken. */
		cachedFor: function ( range ) {
			var keys = Object.keys( this.cache );
			for ( var i = 0; i < keys.length; i++ ) {
				var p = keys[ i ].split( '|' );
				if ( p[ 0 ] <= range.start && p[ 1 ] >= range.end ) {
					return this.cache[ keys[ i ] ];
				}
			}
			return null;
		},

		gridRange: function ( month ) {
			var sow = this.cfg.startOfWeek;
			var first = firstOfMonth( month );
			var offset = ( first.getDay() - sow + 7 ) % 7;
			var start = addDays( first, -offset );
			var last = new Date( month.getFullYear(), month.getMonth() + 1, 0 );
			var endOffset = ( sow + 6 - last.getDay() + 7 ) % 7;
			var end = addDays( last, endOffset );
			return { start: ymd( start ), end: ymd( end ) };
		},

		store: function ( start, end, events ) {
			var self = this;
			this.cache[ start + '|' + end ] = events;
			events.forEach( function ( ev ) {
				self.byId[ ev.id ] = ev;
			} );
		},

		fetchRange: function ( range ) {
			var key = range.start + '|' + range.end;
			var self = this;

			var have = this.cachedFor( range );
			if ( have ) {
				return Promise.resolve( have );
			}
			if ( this.pending[ key ] ) {
				return this.pending[ key ];
			}

			var url = this.cfg.rest + ( this.cfg.rest.indexOf( '?' ) === -1 ? '?' : '&' ) +
				'start=' + range.start + '&end=' + range.end;

			this.pending[ key ] = fetch( url, { credentials: 'same-origin', headers: { Accept: 'application/json' } } )
				.then( function ( r ) {
					if ( ! r.ok ) {
						throw new Error( 'HTTP ' + r.status );
					}
					return r.json();
				} )
				.then( function ( data ) {
					var events = Array.isArray( data.events ) ? data.events : [];
					self.store( range.start, range.end, events );
					delete self.pending[ key ];
					return events;
				} )
				.catch( function ( err ) {
					delete self.pending[ key ];
					throw err;
				} );

			return this.pending[ key ];
		},

		/** Letzter Kalendertag eines Termins (Ende 00:00 zählt zum Vortag). */
		endDay: function ( ev ) {
			var end = datePart( ev.e );
			var start = datePart( ev.s );
			if ( ! ev.ad && end > start && timePart( ev.e ) === '00:00' ) {
				end = ymd( addDays( parseYmd( end ), -1 ) );
			}
			return end < start ? start : end;
		},

		isMultiDay: function ( ev ) {
			return this.endDay( ev ) !== datePart( ev.s );
		},

		visible: function ( ev ) {
			var limit = this.cfg.limit;
			var i;

			if ( limit.length ) {
				var ok = false;
				for ( i = 0; i < ev.k.length; i++ ) {
					if ( limit.indexOf( ev.k[ i ] ) !== -1 ) {
						ok = true;
						break;
					}
				}
				if ( ! ok ) {
					return false;
				}
			}

			if ( this.active.length ) {
				var match = false;
				for ( i = 0; i < ev.k.length; i++ ) {
					if ( this.active.indexOf( ev.k[ i ] ) !== -1 ) {
						match = true;
						break;
					}
				}
				if ( ! match ) {
					return false;
				}
			}

			if ( this.query ) {
				var hay = ( ev.t + ' ' + ev.v + ' ' + ev.c + ' ' + ev.o ).toLowerCase();
				var words = this.query.toLowerCase().split( /\s+/ );
				for ( i = 0; i < words.length; i++ ) {
					if ( words[ i ] && hay.indexOf( words[ i ] ) === -1 ) {
						return false;
					}
				}
			}

			return true;
		},

		/** Sichtbare Termine je Tag im Zeitraum. */
		byDay: function ( events, start, end ) {
			var self = this;
			var map = {};

			events.forEach( function ( ev ) {
				if ( ! self.visible( ev ) ) {
					return;
				}
				var from = datePart( ev.s ) < start ? start : datePart( ev.s );
				var to = self.endDay( ev ) > end ? end : self.endDay( ev );
				var d = parseYmd( from );
				var guard = 0;
				while ( ymd( d ) <= to && guard < 400 ) {
					var k = ymd( d );
					( map[ k ] = map[ k ] || [] ).push( ev );
					d = addDays( d, 1 );
					guard++;
				}
			} );

			Object.keys( map ).forEach( function ( k ) {
				map[ k ].sort( function ( a, b ) {
					var aw = a.ad || self.isMultiDay( a ) ? 0 : 1;
					var bw = b.ad || self.isMultiDay( b ) ? 0 : 1;
					if ( aw !== bw ) {
						return aw - bw;
					}
					return a.s < b.s ? -1 : ( a.s > b.s ? 1 : 0 );
				} );
			} );

			return map;
		},

		catColor: function ( ev ) {
			for ( var i = 0; i < ev.k.length; i++ ) {
				if ( this.cats[ ev.k[ i ] ] ) {
					return this.cats[ ev.k[ i ] ].color;
				}
			}
			return '';
		},

		colorStyle: function ( ev ) {
			var c = this.catColor( ev );
			return c ? ' style="--c:' + esc( c ) + '"' : '';
		},

		/* ---------- Texte ---------- */

		weekTitle: function ( s ) {
			var e = addDays( s, 6 );
			var m = this.t.months;
			if ( s.getMonth() === e.getMonth() ) {
				return s.getDate() + '.–' + e.getDate() + '. ' + m[ e.getMonth() ] + ' ' + e.getFullYear();
			}
			if ( s.getFullYear() === e.getFullYear() ) {
				return s.getDate() + '. ' + m[ s.getMonth() ] + ' – ' + e.getDate() + '. ' + m[ e.getMonth() ] + ' ' + e.getFullYear();
			}
			return s.getDate() + '. ' + m[ s.getMonth() ] + ' ' + s.getFullYear() + ' – ' + e.getDate() + '. ' + m[ e.getMonth() ] + ' ' + e.getFullYear();
		},

		monthTitle: function ( d ) {
			return this.t.months[ d.getMonth() ] + ' ' + d.getFullYear();
		},

		dayTitle: function ( d, withYear ) {
			return this.t.weekdays[ d.getDay() ] + ', ' + d.getDate() + '. ' + this.t.months[ d.getMonth() ] +
				( withYear ? ' ' + d.getFullYear() : '' );
		},

		shortDate: function ( d ) {
			return d.getDate() + '. ' + this.t.months[ d.getMonth() ].slice( 0, 3 ) + '.';
		},

		timeText: function ( ev, forDay ) {
			var multi = this.isMultiDay( ev );
			if ( ev.ad ) {
				return this.t.allDay;
			}
			if ( multi && forDay && forDay !== datePart( ev.s ) ) {
				return forDay === this.endDay( ev ) ? fmt( this.t.until, timePart( ev.e ) ) : this.t.allDay;
			}
			var txt = timePart( ev.s );
			if ( this.cfg.showEnd && ! multi && timePart( ev.e ) !== timePart( ev.s ) ) {
				txt += ' – ' + timePart( ev.e );
			}
			return txt;
		},

		countText: function ( n ) {
			return n === 1 ? this.t.events1 : fmt( this.t.eventsN, n );
		},

		/* ---------- Grundgerüst ---------- */

		build: function () {
			var t = this.t;
			var self = this;

			var chips = '';
			var shown = this.cfg.categories.filter( function ( c ) {
				return c.count > 0 && ( ! self.cfg.limit.length || self.cfg.limit.indexOf( c.id ) !== -1 );
			} );
			if ( shown.length > 1 ) {
				chips = '<div class="tk-chips" role="group" aria-label="' + esc( t.filter ) + '">' +
					'<button type="button" class="tk-chip tk-chip--all" data-cat="">' + esc( t.all ) + '</button>' +
					shown.map( function ( c ) {
						return '<button type="button" class="tk-chip" data-cat="' + c.id + '" style="--c:' + esc( c.color ) + '">' +
							'<span class="tk-chip__dot" aria-hidden="true"></span>' + esc( c.name ) + ICONS.check + '</button>';
					} ).join( '' ) +
					'</div>';
			}

			this.root.innerHTML =
				'<div class="tk-bar">' +
					'<div class="tk-nav">' +
						'<button type="button" class="tk-iconbtn" data-act="prev" aria-label="' + esc( t.prev ) + '">' + ICONS.prev + '</button>' +
						'<button type="button" class="tk-iconbtn" data-act="next" aria-label="' + esc( t.next ) + '">' + ICONS.next + '</button>' +
						'<button type="button" class="tk-btn" data-act="today">' + esc( t.today ) + '</button>' +
						'<h2 class="tk-title" aria-live="polite"></h2>' +
					'</div>' +
					'<div class="tk-tools">' +
						'<label class="tk-search">' +
							'<span class="tk-sr">' + esc( t.search ) + '</span>' + ICONS.search +
							'<input type="search" class="tk-search__input" placeholder="' + esc( t.search ) + '" autocomplete="off">' +
						'</label>' +
						'<div class="tk-seg" role="group" aria-label="' + esc( t.view ) + '">' +
							'<button type="button" class="tk-seg__btn" data-view="month">' + esc( t.month ) + '</button>' +
							'<button type="button" class="tk-seg__btn" data-view="week">' + esc( t.week ) + '</button>' +
							'<button type="button" class="tk-seg__btn" data-view="list">' + esc( t.list ) + '</button>' +
						'</div>' +
					'</div>' +
				'</div>' +
				chips +
				'<div class="tk-body" aria-live="polite"></div>' +
				( this.cfg.subscribe ? '<p class="tk-foot"><a class="tk-link" href="' + esc( this.cfg.subscribe ) + '">' + esc( t.subscribe ) + '</a></p>' : '' ) +
				'<dialog class="tk-dialog" aria-labelledby="' + this.root.id + '-dlg-title"></dialog>';

			// Konfiguration wieder einhängen (für eventuelle Neuinitialisierung nicht nötig).
			this.body = this.root.querySelector( '.tk-body' );
			this.titleEl = this.root.querySelector( '.tk-title' );
			this.dialog = this.root.querySelector( '.tk-dialog' );

			this.root.addEventListener( 'click', this.onClick.bind( this ) );

			var input = this.root.querySelector( '.tk-search__input' );
			var timer = null;
			input.addEventListener( 'input', function () {
				clearTimeout( timer );
				timer = setTimeout( function () {
					self.query = input.value.trim();
					self.render();
				}, 150 );
			} );

			this.dialog.addEventListener( 'click', function ( e ) {
				// Klick auf den abgedunkelten Hintergrund schließt.
				if ( e.target === self.dialog ) {
					self.dialog.close();
				}
			} );
		},

		observe: function () {
			var self = this;
			var apply = function () {
				var compact = self.root.clientWidth < COMPACT_WIDTH;
				if ( compact !== self.compact ) {
					self.compact = compact;
					self.root.classList.toggle( 'is-compact', compact );
					if ( self.ready ) {
						self.render();
					}
				}
			};
			apply();
			if ( window.ResizeObserver ) {
				new ResizeObserver( apply ).observe( this.root );
			} else {
				window.addEventListener( 'resize', apply );
			}
		},

		/* ---------- Ereignisse ---------- */

		onClick: function ( e ) {
			// Bild im Detailfenster: Fenster schließen, die Lightbox übernimmt.
			var zoom = e.target.closest( '.tk-zoom' );
			if ( zoom && this.dialog.contains( zoom ) ) {
				this.dialog.close();
				return;
			}

			var el = e.target.closest( '[data-act],[data-view],[data-cat],[data-ev],[data-day],[data-more]' );
			if ( ! el || ! this.root.contains( el ) ) {
				return;
			}

			if ( el.hasAttribute( 'data-ev' ) ) {
				// Strg/Cmd-Klick auf Links normal öffnen lassen.
				if ( e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1 ) {
					return;
				}
				e.preventDefault();
				this.openEvent( el.getAttribute( 'data-ev' ) );
				return;
			}

			if ( el.hasAttribute( 'data-view' ) ) {
				var nv = el.getAttribute( 'data-view' );
				if ( nv === 'week' && this.view !== 'week' ) {
					// Woche mit heute, wenn heute im angezeigten Monat liegt, sonst die erste Woche des Monats.
					var inMonth = this.today.getMonth() === this.month.getMonth() && this.today.getFullYear() === this.month.getFullYear();
					this.weekStart = this.startOfWeek( inMonth ? this.today : this.month );
				} else if ( nv !== 'week' && this.view === 'week' ) {
					this.month = firstOfMonth( addDays( this.weekStart, 3 ) );
				}
				this.view = nv;
				this.showPast = false;
				this.render();
				return;
			}

			if ( el.hasAttribute( 'data-cat' ) ) {
				var v = el.getAttribute( 'data-cat' );
				if ( v === '' ) {
					this.active = [];
				} else {
					var id = +v;
					var idx = this.active.indexOf( id );
					if ( idx === -1 ) {
						this.active.push( id );
					} else {
						this.active.splice( idx, 1 );
					}
				}
				this.saveActive();
				this.render();
				return;
			}

			if ( el.hasAttribute( 'data-day' ) ) {
				var day = el.getAttribute( 'data-day' );
				if ( this.compact && this.view === 'month' ) {
					this.selected = day;
					this.render();
				} else {
					this.openDay( day );
				}
				return;
			}

			if ( el.hasAttribute( 'data-more' ) ) {
				this.openDay( el.getAttribute( 'data-more' ) );
				return;
			}

			switch ( el.getAttribute( 'data-act' ) ) {
				case 'prev':
					if ( this.view === 'week' ) {
						this.weekStart = addDays( this.weekStart, -7 );
						this.render();
						break;
					}
					this.month = addMonths( this.month, -1 );
					this.selected = null;
					this.showPast = false;
					this.render();
					break;
				case 'next':
				case 'next-month':
					if ( this.view === 'week' ) {
						this.weekStart = addDays( this.weekStart, 7 );
						this.render();
						break;
					}
					this.month = addMonths( this.month, 1 );
					this.selected = null;
					this.showPast = false;
					this.render();
					break;
				case 'today':
					this.weekStart = this.startOfWeek( this.today );
					this.month = firstOfMonth( this.today );
					this.selected = this.cfg.today;
					this.showPast = false;
					this.render();
					break;
				case 'reset':
					this.active = [];
					this.query = '';
					this.root.querySelector( '.tk-search__input' ).value = '';
					this.saveActive();
					this.render();
					break;
				case 'past':
					this.showPast = ! this.showPast;
					this.render();
					break;
				case 'retry':
					this.render();
					break;
				case 'close':
					this.dialog.close();
					break;
			}
		},

		/* ---------- Darstellung ---------- */

		render: function () {
			var self = this;
			var range = this.currentRange();
			var have = this.cachedFor( range );
			var token = this.view + range.start;

			this.ready = true;
			this.token = token;
			this.hideHover();
			this.titleEl.textContent = this.view === 'week' ? this.weekTitle( this.weekStart ) : this.monthTitle( this.month );
			this.updateNavLabels();
			this.updateControls();
			this.updateUrl();

			if ( have ) {
				this.draw( have, range );
				this.prefetch();
				return;
			}

			this.root.classList.add( 'is-loading' );
			this.body.setAttribute( 'aria-busy', 'true' );

			this.fetchRange( range ).then( function ( events ) {
				if ( token !== self.token ) {
					return; // Inzwischen weitergeblättert.
				}
				self.root.classList.remove( 'is-loading' );
				self.body.removeAttribute( 'aria-busy' );
				self.draw( events, range );
				self.prefetch();
			} ).catch( function () {
				if ( token !== self.token ) {
					return;
				}
				self.root.classList.remove( 'is-loading' );
				self.body.removeAttribute( 'aria-busy' );
				self.body.innerHTML = '<div class="tk-empty"><p>' + esc( self.t.error ) + '</p>' +
					'<button type="button" class="tk-btn" data-act="retry">' + esc( self.t.retry ) + '</button></div>';
			} );
		},

		prefetch: function () {
			var self = this;
			[ -1, 1 ].forEach( function ( n ) {
				var r;
				if ( self.view === 'week' ) {
					var ws = addDays( self.weekStart, 7 * n );
					r = { start: ymd( ws ), end: ymd( addDays( ws, 6 ) ) };
				} else {
					r = self.gridRange( addMonths( self.month, n ) );
				}
				self.fetchRange( r ).catch( function () {} );
			} );
		},

		updateNavLabels: function () {
			var week = this.view === 'week';
			var prev = this.root.querySelector( '[data-act=prev]' );
			var next = this.root.querySelector( '[data-act=next]' );
			prev.setAttribute( 'aria-label', week ? this.t.prevWeek : this.t.prev );
			next.setAttribute( 'aria-label', week ? this.t.nextWeek : this.t.next );
		},

		updateControls: function () {
			var self = this;
			this.root.querySelectorAll( '.tk-seg__btn' ).forEach( function ( b ) {
				var on = b.getAttribute( 'data-view' ) === self.view;
				b.classList.toggle( 'is-active', on );
				b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
			this.root.querySelectorAll( '.tk-chip' ).forEach( function ( c ) {
				var v = c.getAttribute( 'data-cat' );
				var on = v === '' ? self.active.length === 0 : self.active.indexOf( +v ) !== -1;
				c.classList.toggle( 'is-active', on );
				c.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
			this.root.classList.toggle( 'is-list', this.view === 'list' );
			this.root.classList.toggle( 'is-week', this.view === 'week' );
		},

		draw: function ( events, range ) {
			this.current = { events: events, range: range };
			if ( this.view === 'week' ) {
				this.body.innerHTML = this.compact ? this.renderWeekList( events, range ) : this.renderWeek( events, range );
			} else if ( this.view === 'list' ) {
				this.body.innerHTML = this.renderList( events );
			} else if ( this.compact ) {
				this.body.innerHTML = this.renderCompactMonth( events, range );
			} else {
				this.body.innerHTML = this.renderMonth( events, range );
			}
		},

		weekdayHead: function ( abbr ) {
			var html = '';
			for ( var i = 0; i < 7; i++ ) {
				var wd = ( this.cfg.startOfWeek + i ) % 7;
				var we = wd === 0 || wd === 6;
				html += '<div class="tk-wd' + ( we ? ' is-weekend' : '' ) + '" aria-hidden="true">' +
					esc( abbr ? this.t.weekdaysAbbr[ wd ] : this.t.weekdaysAbbr[ wd ] ) + '</div>';
			}
			return '<div class="tk-month__head">' + html + '</div>';
		},

		dayClasses: function ( d, base ) {
			var c = base;
			var k = ymd( d );
			if ( this.view !== 'week' && d.getMonth() !== this.month.getMonth() ) {
				c += ' is-out';
			}
			if ( k === this.cfg.today ) {
				c += ' is-today';
			}
			if ( k < this.cfg.today ) {
				c += ' is-past';
			}
			if ( d.getDay() === 0 || d.getDay() === 6 ) {
				c += ' is-weekend';
			}
			return c;
		},

		renderMonth: function ( events, range ) {
			var self = this;
			var map = this.byDay( events, range.start, range.end );
			var max = this.cfg.maxPerDay;
			var cells = '';
			var d = parseYmd( range.start );
			var end = parseYmd( range.end );
			var any = false;

			while ( d <= end ) {
				var k = ymd( d );
				var list = map[ k ] || [];
				var label = this.dayTitle( d ) + ', ' + ( list.length ? this.countText( list.length ) : this.t.noEvents );
				var num = d.getDate() === 1 && d.getMonth() !== this.month.getMonth()
					? d.getDate() + '<small>' + esc( this.t.months[ d.getMonth() ].slice( 0, 3 ) ) + '</small>'
					: String( d.getDate() );

				if ( list.length && d.getMonth() === this.month.getMonth() ) {
					any = true;
				}

				var items = list.slice( 0, max ).map( function ( ev ) {
					return self.renderMonthItem( ev, k );
				} ).join( '' );

				var more = list.length > max
					? '<button type="button" class="tk-more" data-more="' + k + '">' + esc( fmt( this.t.more, list.length - max ) ) + '</button>'
					: '';

				cells += '<div class="' + this.dayClasses( d, 'tk-day' ) + '">' +
					'<button type="button" class="tk-day__num" data-day="' + k + '" aria-label="' + esc( label ) + '"' +
					( k === this.cfg.today ? ' aria-current="date"' : '' ) + '>' + num + '</button>' +
					( items ? '<div class="tk-day__items">' + items + '</div>' : '' ) +
					more +
					'</div>';

				d = addDays( d, 1 );
			}

			var notice = ! any && ( this.active.length || this.query ) ? this.noMatchNotice() : '';

			return notice + '<div class="tk-month">' + this.weekdayHead() + '<div class="tk-month__grid">' + cells + '</div></div>';
		},

		renderMonthItem: function ( ev, day ) {
			var multi = this.isMultiDay( ev );
			var style = this.colorStyle( ev );

			if ( ev.ad || multi ) {
				var cls = 'tk-pill';
				var isStart = datePart( ev.s ) === day;
				var isEnd = this.endDay( ev ) === day;
				if ( ! isStart ) {
					cls += ' is-cont-left';
				}
				if ( ! isEnd ) {
					cls += ' is-cont-right';
				}
				var time = ! ev.ad && isStart ? '<b>' + esc( timePart( ev.s ) ) + '</b> ' : '';
				return '<a class="' + cls + '" href="' + esc( ev.u ) + '" data-ev="' + ev.id + '"' + style + ' title="' + esc( ev.t ) + '">' +
					time + esc( ev.t ) + '</a>';
			}

			return '<a class="tk-ev' + ( ev.f ? ' is-featured' : '' ) + '" href="' + esc( ev.u ) + '" data-ev="' + ev.id + '"' + style + '>' +
				'<span class="tk-ev__dot" aria-hidden="true"></span>' +
				'<span class="tk-ev__text"><span class="tk-ev__time">' + esc( this.timeText( ev, day ) ) + '</span>' +
				'<span class="tk-ev__title">' + esc( ev.t ) + '</span></span></a>';
		},

		renderCompactMonth: function ( events, range ) {
			var self = this;
			var map = this.byDay( events, range.start, range.end );
			var monthStart = ymd( this.month );
			var monthEnd = ymd( new Date( this.month.getFullYear(), this.month.getMonth() + 1, 0 ) );

			if ( ! this.selected || this.selected < monthStart || this.selected > monthEnd ) {
				if ( this.cfg.today >= monthStart && this.cfg.today <= monthEnd ) {
					this.selected = this.cfg.today;
				} else {
					var firstWith = Object.keys( map ).sort().filter( function ( k ) {
						return k >= monthStart && k <= monthEnd;
					} )[ 0 ];
					this.selected = firstWith || monthStart;
				}
			}

			var cells = '';
			var d = parseYmd( range.start );
			var end = parseYmd( range.end );
			while ( d <= end ) {
				var k = ymd( d );
				var list = map[ k ] || [];
				var dots = list.slice( 0, 3 ).map( function ( ev ) {
					return '<i' + self.colorStyle( ev ) + '></i>';
				} ).join( '' );
				var label = this.dayTitle( d ) + ', ' + ( list.length ? this.countText( list.length ) : this.t.noEvents );
				var sel = k === this.selected;

				cells += '<button type="button" class="' + this.dayClasses( d, 'tk-cday' ) + ( sel ? ' is-selected' : '' ) + '"' +
					' data-day="' + k + '" aria-label="' + esc( label ) + '" aria-pressed="' + ( sel ? 'true' : 'false' ) + '"' +
					( k === this.cfg.today ? ' aria-current="date"' : '' ) + '>' +
					'<span class="tk-cday__num">' + d.getDate() + '</span>' +
					'<span class="tk-cday__dots" aria-hidden="true">' + dots + '</span>' +
					'</button>';
				d = addDays( d, 1 );
			}

			var selDate = parseYmd( this.selected );
			var dayEvents = map[ this.selected ] || [];
			var agenda = '<div class="tk-agenda">' +
				'<h3 class="tk-dayhead">' + esc( this.dayTitle( selDate ) ) + '</h3>' +
				( dayEvents.length
					? '<div class="tk-rows">' + dayEvents.map( function ( ev ) {
						return self.renderRow( ev, self.selected );
					} ).join( '' ) + '</div>'
					: '<p class="tk-muted">' + esc( this.t.noDay ) + '</p>' ) +
				'</div>';

			return '<div class="tk-cmonth">' + this.weekdayHead() + '<div class="tk-cmonth__grid">' + cells + '</div></div>' + agenda;
		},

		renderWeek: function ( events, range ) {
			var self = this;
			var map = this.byDay( events, range.start, range.end );
			var d = parseYmd( range.start );
			var cols = '';
			var any = false;

			for ( var i = 0; i < 7; i++ ) {
				var k = ymd( d );
				var list = map[ k ] || [];
				if ( list.length ) {
					any = true;
				}
				var label = this.dayTitle( d ) + ', ' + ( list.length ? this.countText( list.length ) : this.t.noEvents );
				var items = list.map( function ( ev ) {
					return self.renderWeekItem( ev, k );
				} ).join( '' );

				cols += '<div class="' + this.dayClasses( d, 'tk-week__col' ) + '">' +
					'<button type="button" class="tk-week__head" data-day="' + k + '" aria-label="' + esc( label ) + '"' +
					( k === this.cfg.today ? ' aria-current="date"' : '' ) + '>' +
					'<span class="tk-week__wd">' + esc( this.t.weekdaysAbbr[ d.getDay() ] ) + '</span>' +
					'<span class="tk-week__num">' + d.getDate() + '</span>' +
					'</button>' +
					'<div class="tk-week__items">' + items + '</div>' +
					'</div>';
				d = addDays( d, 1 );
			}

			var notice = '';
			if ( ! any ) {
				notice = this.active.length || this.query
					? this.noMatchNotice()
					: '<div class="tk-empty"><p>' + esc( this.t.noWeek ) + '</p></div>';
			}

			return notice + '<div class="tk-week">' + cols + '</div>';
		},

		renderWeekItem: function ( ev, day ) {
			var allday = ev.ad || this.isMultiDay( ev );
			return '<a class="tk-wev' + ( allday ? ' is-allday' : '' ) + ( ev.f ? ' is-featured' : '' ) + '" href="' + esc( ev.u ) + '" data-ev="' + esc( ev.id ) + '"' + this.colorStyle( ev ) + '>' +
				( ev.m ? '<span class="tk-wev__img"><img src="' + esc( ev.m ) + '" alt="" loading="lazy"></span>' : '' ) +
				'<span class="tk-wev__time">' + esc( this.timeText( ev, day ) ) + '</span>' +
				'<span class="tk-wev__title">' + esc( ev.t ) + '</span>' +
				( ev.v ? '<span class="tk-wev__place">' + esc( ev.v ) + '</span>' : '' ) +
				'</a>';
		},

		/** Woche auf schmalen Bildschirmen: Tage untereinander. */
		renderWeekList: function ( events, range ) {
			var self = this;
			var map = this.byDay( events, range.start, range.end );
			var d = parseYmd( range.start );
			var html = '';
			var any = false;

			for ( var i = 0; i < 7; i++ ) {
				var k = ymd( d );
				var list = map[ k ] || [];
				if ( list.length ) {
					any = true;
				}
				html += '<section class="tk-group' + ( k < this.cfg.today ? ' is-past' : '' ) + '">' +
					'<h3 class="tk-dayhead">' +
						'<span class="tk-dayhead__wd">' + esc( this.t.weekdays[ d.getDay() ] ) + '</span> ' +
						esc( d.getDate() + '. ' + this.t.months[ d.getMonth() ] ) +
						( k === this.cfg.today ? ' <span class="tk-badge">' + esc( this.t.today ) + '</span>' : '' ) +
					'</h3>' +
					( list.length
						? '<div class="tk-rows">' + list.map( function ( ev ) {
							return self.renderRow( ev, k );
						} ).join( '' ) + '</div>'
						: '<p class="tk-muted tk-none">' + esc( this.t.noEvents ) + '</p>' ) +
					'</section>';
				d = addDays( d, 1 );
			}

			if ( ! any && ( this.active.length || this.query ) ) {
				return this.noMatchNotice();
			}

			return '<div class="tk-list tk-list--week">' + html + '</div>';
		},

		renderRow: function ( ev, day ) {
			var self = this;
			var sub = '';
			if ( this.isMultiDay( ev ) ) {
				sub = '<small>' + esc( fmt( this.t.until, this.shortDate( parseYmd( this.endDay( ev ) ) ) ) ) + '</small>';
			}
			var place = [ ev.v, ev.c ].filter( Boolean ).join( ' · ' );
			var tags = ev.k.filter( function ( id ) {
				return self.cats[ id ];
			} ).map( function ( id ) {
				var c = self.cats[ id ];
				return '<span class="tk-tag" style="--c:' + esc( c.color ) + '">' + esc( c.name ) + '</span>';
			} ).join( '' );

			return '<a class="tk-row' + ( ev.f ? ' is-featured' : '' ) + ( ev.m ? ' has-img' : '' ) + '" href="' + esc( ev.u ) + '" data-ev="' + esc( ev.id ) + '"' + this.colorStyle( ev ) + '>' +
				'<span class="tk-row__time">' + esc( this.timeText( ev, day ) ) + sub + '</span>' +
				'<span class="tk-row__main">' +
					'<span class="tk-row__title">' + esc( ev.t ) + '</span>' +
					( place ? '<span class="tk-row__meta">' + ICONS.pin + '<span>' + esc( place ) + '</span></span>' : '' ) +
				'</span>' +
				( tags ? '<span class="tk-row__tags">' + tags + '</span>' : '<span class="tk-row__tags"></span>' ) +
				( ev.m ? '<span class="tk-row__img"><img src="' + esc( ev.m ) + '" alt="" loading="lazy"></span>' : '' ) +
				ICONS.chevron +
				'</a>';
		},

		renderList: function ( events ) {
			var self = this;
			var monthStart = ymd( this.month );
			var monthEnd = ymd( new Date( this.month.getFullYear(), this.month.getMonth() + 1, 0 ) );
			var isCurrent = this.cfg.today > monthStart && this.cfg.today <= monthEnd;
			var from = isCurrent && ! this.showPast ? this.cfg.today : monthStart;

			// Jeder Termin genau einmal: am ersten sichtbaren Tag.
			var groups = {};
			var pastCount = 0;
			events.forEach( function ( ev ) {
				if ( ! self.visible( ev ) ) {
					return;
				}
				var s = datePart( ev.s );
				var e = self.endDay( ev );
				if ( e < monthStart || s > monthEnd ) {
					return;
				}
				if ( e < from ) {
					pastCount++;
					return;
				}
				var day = s < from ? from : s;
				( groups[ day ] = groups[ day ] || [] ).push( ev );
			} );

			var days = Object.keys( groups ).sort();
			var html = '';

			if ( isCurrent && ( pastCount > 0 || this.showPast ) ) {
				html += '<button type="button" class="tk-btn tk-btn--ghost tk-pastbtn" data-act="past">' +
					esc( this.showPast ? this.t.hidePast : this.t.showPast ) + '</button>';
			}

			if ( ! days.length ) {
				if ( this.active.length || this.query ) {
					return html + this.noMatchNotice();
				}
				return html + '<div class="tk-empty"><p>' + esc( fmt( this.t.noMonth, this.monthTitle( this.month ) ) ) + '</p>' +
					'<button type="button" class="tk-btn" data-act="next-month">' + esc( this.t.nextMonth ) + '</button></div>';
			}

			days.forEach( function ( day ) {
				var list = groups[ day ].sort( function ( a, b ) {
					var aw = a.ad ? 0 : 1;
					var bw = b.ad ? 0 : 1;
					if ( aw !== bw ) {
						return aw - bw;
					}
					return a.s < b.s ? -1 : ( a.s > b.s ? 1 : 0 );
				} );
				var d = parseYmd( day );
				html += '<section class="tk-group' + ( day < self.cfg.today ? ' is-past' : '' ) + '">' +
					'<h3 class="tk-dayhead">' +
						'<span class="tk-dayhead__wd">' + esc( self.t.weekdays[ d.getDay() ] ) + '</span> ' +
						esc( d.getDate() + '. ' + self.t.months[ d.getMonth() ] ) +
						( day === self.cfg.today ? ' <span class="tk-badge">' + esc( self.t.today ) + '</span>' : '' ) +
					'</h3>' +
					'<div class="tk-rows">' + list.map( function ( ev ) {
						return self.renderRow( ev, day );
					} ).join( '' ) + '</div>' +
					'</section>';
			} );

			return '<div class="tk-list">' + html + '</div>';
		},

		noMatchNotice: function () {
			return '<div class="tk-empty" role="status"><p>' + esc( this.t.noMatch ) + '</p>' +
				'<button type="button" class="tk-btn" data-act="reset">' + esc( this.t.reset ) + '</button></div>';
		},

		/* ---------- Dialog ---------- */

		showDialog: function ( html ) {
			this.dialog.innerHTML = '<div class="tk-dlg">' +
				'<button type="button" class="tk-dlg__close" data-act="close" aria-label="' + esc( this.t.close ) + '">' + ICONS.close + '</button>' +
				html + '</div>';
			if ( typeof this.dialog.showModal === 'function' ) {
				if ( ! this.dialog.open ) {
					this.dialog.showModal();
				}
			} else {
				this.dialog.setAttribute( 'open', '' );
			}
			var focus = this.dialog.querySelector( '.tk-dlg__title' );
			if ( focus ) {
				focus.focus( { preventScroll: true } );
			}
		},

		whenText: function ( ev, withYear ) {
			var start = parseYmd( datePart( ev.s ) );
			var endDay = parseYmd( this.endDay( ev ) );

			if ( this.isMultiDay( ev ) ) {
				return this.dayTitle( start, withYear ) + ( ev.ad ? '' : ', ' + timePart( ev.s ) + ' ' + this.t.oclock ) +
					' – ' + this.dayTitle( endDay, withYear ) + ( ev.ad ? '' : ', ' + timePart( ev.e ) + ' ' + this.t.oclock );
			}
			if ( ev.ad ) {
				return this.dayTitle( start, withYear ) + ' · ' + this.t.allDay;
			}
			return this.dayTitle( start, withYear ) + ' · ' + timePart( ev.s ) +
				( timePart( ev.e ) !== timePart( ev.s ) ? ' – ' + timePart( ev.e ) : '' ) + ' ' + this.t.oclock;
		},

		/* ---------- Vorschau beim Darüberfahren (nur Maus) ---------- */

		setupHover: function () {
			var self = this;
			var canHover = window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;
			if ( ! canHover ) {
				return;
			}

			this.hover = document.createElement( 'div' );
			this.hover.className = 'tk-hover';
			this.hover.setAttribute( 'aria-hidden', 'true' ); // Gleiche Infos gibt es per Klick im Dialog.
			this.root.appendChild( this.hover );
			this.hoverEl = null;
			this.hoverTimer = null;

			this.root.addEventListener( 'mouseover', function ( e ) {
				var el = e.target.closest ? e.target.closest( '.tk-month [data-ev], .tk-week [data-ev]' ) : null;
				if ( ! el || el === self.hoverEl ) {
					return;
				}
				self.hoverEl = el;
				clearTimeout( self.hoverTimer );
				self.hoverTimer = setTimeout( function () {
					self.showHover( el );
				}, 200 );
			} );

			this.root.addEventListener( 'mouseout', function ( e ) {
				if ( ! self.hoverEl ) {
					return;
				}
				if ( e.relatedTarget && self.hoverEl.contains( e.relatedTarget ) ) {
					return;
				}
				self.hideHover();
			} );

			window.addEventListener( 'scroll', function () {
				self.hideHover();
			}, { passive: true } );
		},

		showHover: function ( el ) {
			var ev = this.byId[ el.getAttribute( 'data-ev' ) ];
			if ( ! ev || ! this.hover || ( this.dialog && this.dialog.open ) ) {
				return;
			}
			var self = this;

			var cats = ev.k.filter( function ( k ) {
				return self.cats[ k ];
			} ).map( function ( k ) {
				var c = self.cats[ k ];
				return '<span class="tk-dlg__cat" style="--c:' + esc( c.color ) + '"><span class="tk-chip__dot"></span>' + esc( c.name ) + '</span>';
			} ).join( '' );
			var place = [ ev.v, ev.c ].filter( Boolean ).join( ', ' );

			this.hover.innerHTML =
				( ev.m ? '<div class="tk-hover__img"><img src="' + esc( ev.m ) + '" alt=""></div>' : '' ) +
				( cats ? '<div class="tk-dlg__cats">' + cats + '</div>' : '' ) +
				'<div class="tk-hover__title">' + esc( ev.t ) + '</div>' +
				'<ul class="tk-dlg__meta">' +
					'<li>' + ICONS.clock + '<span>' + esc( this.whenText( ev, false ) ) + '</span></li>' +
					( place ? '<li>' + ICONS.pin + '<span>' + esc( place ) + '</span></li>' : '' ) +
					( ev.o ? '<li>' + ICONS.person + '<span>' + esc( ev.o ) + '</span></li>' : '' ) +
				'</ul>' +
				( ev.x ? '<p class="tk-hover__text">' + esc( ev.x ) + '</p>' : '' );

			// Neben dem Termin platzieren, rechts oder – wenn kein Platz – links.
			var r = el.getBoundingClientRect();
			var root = this.root.getBoundingClientRect();
			var w = 320;
			var gap = 10;
			var left = r.right - root.left + gap;
			if ( left + w > root.width ) {
				left = r.left - root.left - gap - w;
			}
			left = Math.max( 0, Math.min( left, root.width - w ) );

			this.hover.style.left = left + 'px';
			this.hover.style.top = '0px';
			this.hover.classList.add( 'is-visible' );

			var h = this.hover.offsetHeight;
			var top = r.top - root.top - 8;
			// Nicht unten aus dem Fenster ragen lassen.
			var maxTop = window.innerHeight - root.top - h - 12;
			top = Math.max( -root.top + 12, Math.min( top, maxTop ) );
			top = Math.max( 0, top );
			this.hover.style.top = top + 'px';
		},

		hideHover: function () {
			clearTimeout( this.hoverTimer );
			this.hoverEl = null;
			if ( this.hover ) {
				this.hover.classList.remove( 'is-visible' );
			}
		},

		openEvent: function ( id ) {
			var ev = this.byId[ id ];
			if ( ! ev ) {
				return;
			}
			var self = this;
			var when = this.whenText( ev, true );

			var cats = ev.k.filter( function ( k ) {
				return self.cats[ k ];
			} ).map( function ( k ) {
				var c = self.cats[ k ];
				return '<span class="tk-dlg__cat" style="--c:' + esc( c.color ) + '"><span class="tk-chip__dot" aria-hidden="true"></span>' + esc( c.name ) + '</span>';
			} ).join( '' );

			if ( ev.r && this.t.series ) {
				cats += '<span class="tk-dlg__cat tk-dlg__cat--series">' + esc( this.t.series ) + '</span>';
			}

			var place = [ ev.v, ev.c ].filter( Boolean ).join( ', ' );

			this.hideHover();
			this.showDialog(
				( ev.m ? '<a class="tk-dlg__img tk-zoom" href="' + esc( ev.M || ev.m ) + '" data-lightbox="tk-termin" aria-label="' + esc( this.t.zoom ) + '"><img src="' + esc( ev.m ) + '" alt=""></a>' : '' ) +
				( cats ? '<div class="tk-dlg__cats">' + cats + '</div>' : '' ) +
				'<h3 class="tk-dlg__title" id="' + this.root.id + '-dlg-title" tabindex="-1">' + esc( ev.t ) + '</h3>' +
				'<ul class="tk-dlg__meta">' +
					'<li>' + ICONS.clock + '<span>' + esc( when ) + '</span></li>' +
					( place ? '<li>' + ICONS.pin + '<span>' + esc( place ) + '</span></li>' : '' ) +
					( ev.o ? '<li>' + ICONS.person + '<span>' + esc( ev.o ) + '</span></li>' : '' ) +
				'</ul>' +
				( ev.x ? '<p class="tk-dlg__text">' + esc( ev.x ) + '</p>' : '' ) +
				'<div class="tk-dlg__actions">' +
					'<a class="tk-btn tk-btn--primary" href="' + esc( ev.u ) + '">' + esc( this.t.details ) + '</a>' +
					( ev.i ? '<a class="tk-btn" href="' + esc( ev.i ) + '" rel="nofollow">' + esc( this.t.addCal ) + '</a>' : '' ) +
				'</div>'
			);
		},

		openDay: function ( day ) {
			var self = this;
			var range = this.current ? this.current.range : this.currentRange();
			var events = this.current ? this.current.events : ( this.cachedFor( range ) || [] );
			var list = this.byDay( events, range.start, range.end )[ day ] || [];
			var d = parseYmd( day );

			this.showDialog(
				'<h3 class="tk-dlg__title" id="' + this.root.id + '-dlg-title" tabindex="-1">' + esc( this.dayTitle( d, true ) ) + '</h3>' +
				( list.length
					? '<div class="tk-rows tk-rows--dialog">' + list.map( function ( ev ) {
						return self.renderRow( ev, day );
					} ).join( '' ) + '</div>'
					: '<p class="tk-muted">' + esc( this.t.noDay ) + '</p>' )
			);
		}
	};

	/* ------------------------------------------------------------------ *
	 * Start
	 * ------------------------------------------------------------------ */

	function boot() {
		document.querySelectorAll( '.tk-cal' ).forEach( function ( root ) {
			if ( root.getAttribute( 'data-ready' ) ) {
				return;
			}
			root.setAttribute( 'data-ready', '1' );
			try {
				root.tkCalendar = new Calendar( root );
			} catch ( e ) {
				if ( window.console ) {
					window.console.error( 'Veranstaltungskalender:', e );
				}
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
