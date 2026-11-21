/*! WX Lightbox 1.1.0 | GPL-2.0-or-later */
(function () {
	'use strict';

	var cfg = window.sblSettings || { auto: true, group: true, caption: true };
	var t = cfg.i18n || {};
	var IMG_EXT = /\.(jpe?g|png|gif|webp|avif|bmp|svg)$/i;
	var GROUP_SEL = '[data-lightbox-group], .wp-block-gallery, .gallery, .tiled-gallery';
	var CONTEXT_SEL = '.entry-content, .wp-block-post-content, .post-content, article, main';
	var DURATION = 250;

	var ICONS = {
		close: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		prev: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7"/></svg>',
		next: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7"/></svg>'
	};

	var ui = null;
	var items = [];
	var index = 0;
	var loadToken = 0;
	var lastFocus = null;
	var closeTimer = null;
	var touch = null;

	/* ---------- Hilfsfunktionen ---------- */

	function label(key, fallback) {
		return t[key] || fallback;
	}

	function format(str, a, b) {
		return String(str).replace('%1$s', a).replace('%2$s', b);
	}

	function reducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function cssEscape(value) {
		return window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&');
	}

	function isCandidate(a) {
		if (!a || a.tagName !== 'A' || !a.href) return false;
		if (a.closest('.no-lightbox, [data-lightbox="off"]')) return false;
		if (a.hasAttribute('data-lightbox') || a.classList.contains('lightbox')) return true;
		if (!cfg.auto) return false;
		return IMG_EXT.test(a.pathname || '');
	}

	function itemFrom(a) {
		var img = a.querySelector('img');
		var alt = img ? img.getAttribute('alt') || '' : '';
		var caption = a.getAttribute('data-caption') || '';

		if (!caption) {
			var fig = a.closest('figure');
			var fc = fig ? fig.querySelector(':scope > figcaption') : null;
			if (!fc) {
				var wrap = a.closest('.gallery-item, .wp-caption');
				fc = wrap ? wrap.querySelector('.wp-caption-text, .gallery-caption') : null;
			}
			caption = (fc && fc.textContent.trim()) || a.getAttribute('title') || alt;
		}

		return { src: a.href, alt: alt || caption, caption: String(caption).trim() };
	}

	function collect(a) {
		var links;
		var name = a.getAttribute('data-lightbox');

		if (name && name !== 'off') {
			links = document.querySelectorAll('a[data-lightbox="' + cssEscape(name) + '"]');
		} else if (cfg.group) {
			var root = a.closest(GROUP_SEL) || a.closest(CONTEXT_SEL);
			links = root ? root.querySelectorAll('a[href]') : [a];
		} else {
			links = [a];
		}

		var list = [];
		var seen = {};
		var start = 0;

		Array.prototype.forEach.call(links, function (link) {
			if (!isCandidate(link)) return;
			var src = link.href;
			if (seen[src] === undefined) {
				seen[src] = list.length;
				list.push(itemFrom(link));
			}
			if (link === a) start = seen[src];
		});

		if (!list.length) list.push(itemFrom(a));
		return { list: list, start: start };
	}

	/* ---------- Aufbau ---------- */

	function button(cls, text, icon) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'sbl-btn ' + cls;
		b.setAttribute('aria-label', text);
		b.title = text;
		b.innerHTML = icon;
		return b;
	}

	function build() {
		var overlay = document.createElement('div');
		overlay.className = 'sbl-overlay';
		overlay.hidden = true;
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', label('dialog', 'Bildansicht'));

		var stage = document.createElement('div');
		stage.className = 'sbl-stage';

		var figure = document.createElement('figure');
		figure.className = 'sbl-figure';

		var img = document.createElement('img');
		img.className = 'sbl-image';
		img.alt = '';
		img.decoding = 'async';
		img.draggable = false;

		var caption = document.createElement('figcaption');
		caption.className = 'sbl-caption';

		var spinner = document.createElement('div');
		spinner.className = 'sbl-spinner';
		spinner.setAttribute('aria-hidden', 'true');

		var counter = document.createElement('div');
		counter.className = 'sbl-counter';
		counter.setAttribute('aria-live', 'polite');

		var prev = button('sbl-prev', label('prev', 'Vorheriges Bild'), ICONS.prev);
		var next = button('sbl-next', label('next', 'Nächstes Bild'), ICONS.next);
		var close = button('sbl-close', label('close', 'Schließen'), ICONS.close);

		figure.appendChild(img);
		figure.appendChild(caption);
		stage.appendChild(figure);

		[stage, spinner, counter, prev, next, close].forEach(function (el) {
			overlay.appendChild(el);
		});

		prev.addEventListener('click', function () { step(-1); });
		next.addEventListener('click', function () { step(1); });
		close.addEventListener('click', closeBox);

		// Klick auf den verschwommenen Hintergrund schließt.
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay || e.target === stage || e.target === figure) closeBox();
		});

		// Swipe: links/rechts blättern, nach unten schließen.
		overlay.addEventListener('touchstart', function (e) {
			touch = e.touches.length === 1 ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null;
		}, { passive: true });

		overlay.addEventListener('touchend', function (e) {
			if (!touch) return;
			var dx = e.changedTouches[0].clientX - touch.x;
			var dy = e.changedTouches[0].clientY - touch.y;
			touch = null;
			if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5 && items.length > 1) {
				step(dx < 0 ? 1 : -1);
			} else if (dy > 90 && dy > Math.abs(dx) * 1.5) {
				closeBox();
			}
		}, { passive: true });

		document.body.appendChild(overlay);

		ui = {
			overlay: overlay, img: img, caption: caption, counter: counter,
			prev: prev, next: next, close: close, open: false
		};
	}

	/* ---------- Steuerung ---------- */

	function lockScroll() {
		var root = document.documentElement;
		if (root.classList.contains('sbl-lock')) return;
		var scrollbar = window.innerWidth - root.clientWidth;
		root.style.setProperty('--sbl-scrollbar', Math.max(0, scrollbar) + 'px');
		root.classList.add('sbl-lock', 'sbl-active');
	}

	function unlockScroll() {
		var root = document.documentElement;
		root.classList.remove('sbl-lock', 'sbl-active');
		root.style.removeProperty('--sbl-scrollbar');
	}

	function preload(src) {
		var i = new Image();
		i.decoding = 'async';
		i.src = src;
	}

	function show(i) {
		var n = items.length;
		index = ((i % n) + n) % n;
		var item = items[index];
		var token = ++loadToken;

		ui.overlay.classList.add('sbl-loading');
		ui.img.classList.remove('sbl-ready');
		ui.counter.textContent = n > 1 ? format(label('counter', '%1$s / %2$s'), index + 1, n) : '';

		var loader = new Image();
		loader.onload = loader.onerror = function () {
			if (token !== loadToken) return;
			ui.img.src = item.src;
			ui.img.alt = item.alt;
			var showCaption = !!(cfg.caption && item.caption);
			ui.caption.textContent = showCaption ? item.caption : '';
			ui.caption.hidden = !showCaption;
			ui.overlay.classList.remove('sbl-loading');
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					if (token === loadToken) ui.img.classList.add('sbl-ready');
				});
			});
		};
		loader.src = item.src;

		if (n > 1) {
			preload(items[(index + 1) % n].src);
			preload(items[(index - 1 + n) % n].src);
		}
	}

	function step(dir) {
		if (items.length > 1) show(index + dir);
	}

	function open(list, start) {
		if (!list || !list.length) return;
		if (!ui) build();
		clearTimeout(closeTimer);

		items = list;
		if (!ui.open) lastFocus = document.activeElement;
		ui.open = true;

		var multi = items.length > 1;
		ui.prev.hidden = !multi;
		ui.next.hidden = !multi;
		ui.counter.hidden = !multi;

		lockScroll();
		ui.overlay.hidden = false;
		void ui.overlay.offsetWidth; // Reflow erzwingen, damit die Einblendung animiert.
		ui.overlay.classList.add('sbl-open');

		document.addEventListener('keydown', onKey, true);
		show(start || 0);
		ui.close.focus({ preventScroll: true });
	}

	function closeBox() {
		if (!ui || !ui.open) return;
		ui.open = false;
		loadToken++;
		ui.overlay.classList.remove('sbl-open');
		document.removeEventListener('keydown', onKey, true);

		clearTimeout(closeTimer);
		closeTimer = setTimeout(function () {
			ui.overlay.hidden = true;
			ui.img.removeAttribute('src');
			ui.img.classList.remove('sbl-ready');
			unlockScroll();
			if (lastFocus && typeof lastFocus.focus === 'function') {
				lastFocus.focus({ preventScroll: true });
			}
		}, reducedMotion() ? 0 : DURATION);
	}

	function trapFocus(e) {
		var focusables = [ui.prev, ui.next, ui.close].filter(function (b) { return !b.hidden; });
		var i = focusables.indexOf(document.activeElement);
		e.preventDefault();
		var nextIndex = e.shiftKey
			? (i <= 0 ? focusables.length - 1 : i - 1)
			: (i === -1 || i === focusables.length - 1 ? 0 : i + 1);
		focusables[nextIndex].focus();
	}

	function onKey(e) {
		switch (e.key) {
			case 'Escape':
			case 'Esc':
				e.preventDefault();
				closeBox();
				break;
			case 'ArrowLeft':
				if (items.length > 1) { e.preventDefault(); step(-1); }
				break;
			case 'ArrowRight':
				if (items.length > 1) { e.preventDefault(); step(1); }
				break;
			case 'Tab':
				trapFocus(e);
				break;
		}
	}

	/* ---------- Initialisierung ---------- */

	document.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		var a = e.target && e.target.closest ? e.target.closest('a') : null;
		if (!isCandidate(a)) return;
		if (ui && ui.overlay.contains(a)) return;
		e.preventDefault();
		var group = collect(a);
		open(group.list, group.start);
	});

	function markTriggers() {
		Array.prototype.forEach.call(document.querySelectorAll('a[href]'), function (a) {
			if (isCandidate(a)) a.classList.add('sbl-trigger');
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', markTriggers);
	} else {
		markTriggers();
	}

	// Öffentliche API, z. B. SoftBlurLightbox.open(['bild1.jpg', 'bild2.jpg'], 0)
	window.SoftBlurLightbox = {
		open: function (sources, start) {
			var list = (Array.isArray(sources) ? sources : [sources]).map(function (s) {
				return typeof s === 'string'
					? { src: s, alt: '', caption: '' }
					: { src: s.src, alt: s.alt || s.caption || '', caption: s.caption || '' };
			});
			open(list, start || 0);
		},
		close: closeBox
	};
})();
