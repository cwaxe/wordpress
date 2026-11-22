/**
 * WX Easy Calendar – Vorschau beim Darüberfahren, Detailfenster und Lightbox.
 * Der Kalender funktioniert auch ohne dieses Skript: dann öffnen Links die Terminseite.
 */
( function () {
	'use strict';

	var cfg = window.ekKalender || { lightbox: true, texte: {} };
	var t = cfg.texte || {};

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function daten( el ) {
		try {
			return JSON.parse( el.getAttribute( 'data-ek' ) );
		} catch ( e ) {
			return null;
		}
	}

	/** Hintergrund- und Schriftfarbe des Themes rund um den Kalender ermitteln. */
	function farben( el ) {
		var vg = window.getComputedStyle( el ).color;
		var hg = '';
		var n = el;
		while ( n && n.nodeType === 1 ) {
			var c = window.getComputedStyle( n ).backgroundColor;
			if ( c && c !== 'transparent' && ! /rgba\(.*,\s*0\)$/.test( c ) ) {
				hg = c;
				break;
			}
			n = n.parentElement;
		}
		return { hg: hg || '#fff', vg: vg || '#111' };
	}

	function farbAttr( f, box ) {
		box.style.setProperty( '--ek-hg', f.hg );
		box.style.setProperty( '--ek-vg', f.vg );
	}

	function kategorien( k ) {
		return ( k || [] ).map( function ( p ) {
			var stil = p[ 1 ] ? ' style="--ek-farbe:' + esc( p[ 1 ] ) + '"' : '';
			return '<span class="ek-badge"' + stil + '>' + esc( p[ 0 ] ) + '</span>';
		} ).join( ' ' );
	}

	function meta( d ) {
		return '<ul class="ek-meta">' +
			'<li><span class="ek-meta-label">Wann:</span>' + esc( d.w ) + '</li>' +
			( d.o ? '<li><span class="ek-meta-label">Wo:</span>' + esc( d.o ) + '</li>' : '' ) +
			( d.v ? '<li><span class="ek-meta-label">Veranstalter:</span>' + esc( d.v ) + '</li>' : '' ) +
			'</ul>';
	}

	/* ---------------- Vorschau (nur mit Maus) ---------------- */

	var vorschau = null;
	var vorschauEl = null;
	var timer = null;

	function vorschauZeigen( el ) {
		var d = daten( el );
		if ( ! d || ( dialog && dialog.open ) ) {
			return;
		}
		if ( ! vorschau ) {
			vorschau = document.createElement( 'div' );
			vorschau.className = 'ek-vorschau';
			vorschau.setAttribute( 'aria-hidden', 'true' );
			document.body.appendChild( vorschau );
		}
		farbAttr( farben( el.closest( '.ek-kalender, .ek-liste' ) || el ), vorschau );
		vorschau.innerHTML =
			( d.b ? '<span class="ek-vorschau-bild"><img src="' + esc( d.b ) + '" alt=""></span>' : '' ) +
			kategorien( d.k ) +
			'<span class="ek-vorschau-titel">' + esc( d.t ) + '</span>' +
			meta( d ) +
			( d.x ? '<p class="ek-text">' + esc( d.x ) + '</p>' : '' );

		var r = el.getBoundingClientRect();
		var b = vorschau.offsetWidth || 300;
		var links = r.right + 10;
		if ( links + b > window.innerWidth - 12 ) {
			links = r.left - b - 10;
		}
		links = Math.max( 12, Math.min( links, window.innerWidth - b - 12 ) );
		vorschau.style.left = ( links + window.scrollX ) + 'px';
		vorschau.style.top = '0px';
		vorschau.classList.add( 'ek-sichtbar' );
		var h = vorschau.offsetHeight;
		var oben = Math.max( 12, Math.min( r.top - 8, window.innerHeight - h - 12 ) );
		vorschau.style.top = ( oben + window.scrollY ) + 'px';
	}

	function vorschauWeg() {
		clearTimeout( timer );
		vorschauEl = null;
		if ( vorschau ) {
			vorschau.classList.remove( 'ek-sichtbar' );
		}
	}

	if ( window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches ) {
		document.addEventListener( 'mouseover', function ( e ) {
			var el = e.target.closest ? e.target.closest( '.ek-kalender .ek-termin[data-ek], .ek-kalender .ek-karte[data-ek]' ) : null;
			if ( ! el || el === vorschauEl ) {
				return;
			}
			vorschauEl = el;
			clearTimeout( timer );
			timer = setTimeout( function () {
				vorschauZeigen( el );
			}, 200 );
		} );
		document.addEventListener( 'mouseout', function ( e ) {
			if ( vorschauEl && ! ( e.relatedTarget && vorschauEl.contains( e.relatedTarget ) ) ) {
				vorschauWeg();
			}
		} );
		window.addEventListener( 'scroll', vorschauWeg, { passive: true } );
	}

	/* ---------------- Detailfenster ---------------- */

	var dialog = null;

	function dialogOeffnen( d, quelle ) {
		if ( ! window.HTMLDialogElement ) {
			return false;
		}
		vorschauWeg();
		if ( ! dialog ) {
			dialog = document.createElement( 'dialog' );
			dialog.className = 'ek-dialog';
			document.body.appendChild( dialog );
			dialog.addEventListener( 'click', function ( e ) {
				if ( e.target === dialog || e.target.closest( '.ek-dialog-zu' ) ) {
					dialog.close();
				} else if ( e.target.closest( '.ek-zoom' ) ) {
					dialog.close(); // die Lightbox übernimmt
				}
			} );
		}
		farbAttr( farben( quelle.closest( '.ek-kalender, .ek-liste' ) || quelle ), dialog );
		dialog.innerHTML = '<div class="ek-dialog-innen">' +
			'<button type="button" class="ek-dialog-zu" aria-label="' + esc( t.schliessen || 'Schließen' ) + '">✕</button>' +
			( d.b ? '<a class="ek-dialog-bild ek-zoom" href="' + esc( d.B || d.b ) + '" data-lightbox="ek-termin" aria-label="' + esc( t.vergroessern || '' ) + '"><img src="' + esc( d.b ) + '" alt=""></a>' : '' ) +
			kategorien( d.k ) +
			( d.s ? ' <span class="ek-leise">' + esc( d.s ) + '</span>' : '' ) +
			'<h3 class="ek-dialog-titel" tabindex="-1">' + esc( d.t ) + '</h3>' +
			meta( d ) +
			( d.x ? '<p class="ek-text">' + esc( d.x ) + '</p>' : '' ) +
			'<p class="ek-aktionen"><a class="ek-knopf" href="' + esc( d.u ) + '">' + esc( t.details || 'Details ansehen' ) + '</a>' +
			( d.i ? '<a class="ek-knopf ek-knopf-leise" href="' + esc( d.i ) + '" rel="nofollow">' + esc( t.eintragen || 'In Kalender eintragen' ) + '</a>' : '' ) +
			'</p></div>';
		dialog.showModal();
		var titel = dialog.querySelector( '.ek-dialog-titel' );
		if ( titel ) {
			titel.focus( { preventScroll: true } );
		}
		return true;
	}

	document.addEventListener( 'click', function ( e ) {
		if ( e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0 ) {
			return;
		}
		var link = e.target.closest ? e.target.closest( 'a' ) : null;
		var el = link ? link.closest( '[data-ek]' ) : null;
		if ( ! el || link.classList.contains( 'ek-zoom' ) ) {
			return;
		}
		var d = daten( el );
		if ( d && dialogOeffnen( d, el ) ) {
			e.preventDefault();
		}
	} );

	/* ---------------- Lightbox (wenn WX Lightbox nicht aktiv ist) ---------------- */

	if ( ! cfg.lightbox ) {
		return;
	}

	var lb, lbBild, lbText, lbZurueck, lbWeiter;
	var liste = [];
	var pos = 0;

	function lbBauen() {
		if ( lb ) {
			return;
		}
		lb = document.createElement( 'dialog' );
		lb.className = 'ek-lb';
		lb.innerHTML =
			'<button type="button" class="ek-lb-zu" aria-label="' + esc( t.schliessen || 'Schließen' ) + '">✕</button>' +
			'<button type="button" class="ek-lb-zurueck" aria-label="' + esc( t.zurueck || '' ) + '">‹</button>' +
			'<figure><img alt=""><figcaption></figcaption></figure>' +
			'<button type="button" class="ek-lb-weiter" aria-label="' + esc( t.weiter || '' ) + '">›</button>';
		document.body.appendChild( lb );
		lbBild = lb.querySelector( 'img' );
		lbText = lb.querySelector( 'figcaption' );
		lbZurueck = lb.querySelector( '.ek-lb-zurueck' );
		lbWeiter = lb.querySelector( '.ek-lb-weiter' );

		lb.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.ek-lb-zu' ) || e.target === lb || e.target.tagName === 'FIGURE' ) {
				lb.close();
			} else if ( e.target.closest( '.ek-lb-zurueck' ) ) {
				lbZeigen( pos - 1 );
			} else if ( e.target.closest( '.ek-lb-weiter' ) ) {
				lbZeigen( pos + 1 );
			}
		} );
		lb.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowLeft' ) {
				lbZeigen( pos - 1 );
			} else if ( e.key === 'ArrowRight' ) {
				lbZeigen( pos + 1 );
			}
		} );
		var x = null;
		lb.addEventListener( 'touchstart', function ( e ) {
			x = e.touches[ 0 ].clientX;
		}, { passive: true } );
		lb.addEventListener( 'touchend', function ( e ) {
			if ( x !== null && Math.abs( e.changedTouches[ 0 ].clientX - x ) > 50 ) {
				lbZeigen( pos + ( e.changedTouches[ 0 ].clientX < x ? 1 : -1 ) );
			}
			x = null;
		} );
	}

	function lbZeigen( i ) {
		pos = ( i + liste.length ) % liste.length;
		var a = liste[ pos ];
		var img = a.querySelector( 'img' );
		var fig = a.closest( 'figure' );
		var cap = fig ? fig.querySelector( 'figcaption' ) : null;
		lbBild.src = a.getAttribute( 'href' );
		lbBild.alt = img ? img.getAttribute( 'alt' ) || '' : '';
		lbText.textContent = cap ? cap.textContent : '';
		lbText.hidden = ! lbText.textContent;
		lbZurueck.hidden = lbWeiter.hidden = liste.length < 2;
	}

	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest ? e.target.closest( 'a.ek-zoom' ) : null;
		if ( ! a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0 || ! window.HTMLDialogElement ) {
			return;
		}
		e.preventDefault();
		lbBauen();
		var gruppe = a.getAttribute( 'data-lightbox' ) || '';
		liste = Array.prototype.filter.call( document.querySelectorAll( 'a.ek-zoom' ), function ( x ) {
			return ( x.getAttribute( 'data-lightbox' ) || '' ) === gruppe && ( x === a || ! x.closest( 'dialog' ) );
		} );
		lbZeigen( Math.max( 0, liste.indexOf( a ) ) );
		lb.showModal();
	} );
}() );
