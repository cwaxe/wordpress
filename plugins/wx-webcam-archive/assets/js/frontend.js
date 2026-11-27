/*! WX Webcam Archive – Frontend */
(function () {
	'use strict';

	/* ------------------------------------------------------------------ *
	 * Helfer
	 * ------------------------------------------------------------------ */

	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
	function parseYmd(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
	function monthKey(y, m) { return y + '-' + pad(m + 1); }
	function toMin(t) { var p = t.split(':'); return (+p[0]) * 60 + (+p[1]); }

	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		if (text != null) { e.textContent = text; }
		return e;
	}
	function btn(cls, text, label) {
		var b = el('button', 'wca-btn ' + cls, text);
		b.type = 'button';
		if (label) { b.setAttribute('aria-label', label); b.title = label; }
		return b;
	}

	function api(cfg, path, params) {
		var url = cfg.rest + path, qs = [];
		params = params || {};
		Object.keys(params).forEach(function (k) {
			qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
		});
		if (qs.length) { url += (url.indexOf('?') === -1 ? '?' : '&') + qs.join('&'); }
		var headers = {};
		if (cfg.nonce) { headers['X-WP-Nonce'] = cfg.nonce; }
		return fetch(url, { headers: headers, credentials: 'same-origin' }).then(function (r) {
			if (!r.ok) { var e = new Error('HTTP ' + r.status); e.status = r.status; throw e; }
			return r.json();
		});
	}

	/* ------------------------------------------------------------------ *
	 * Archiv: Kalender + Viewer
	 * ------------------------------------------------------------------ */

	function Archive(root) {
		this.root = root;
		this.cfg = JSON.parse(root.getAttribute('data-wca') || '{}');
		this.t = this.cfg.i18n || {};
		this.locale = this.cfg.locale || 'de-DE';
		this.monthCache = {};
		this.dayCache = {};
		this.images = [];
		this.index = -1;
		this.date = null;
		this.minutes = null;
		this.timer = null;
		this.cams = this.cfg.cameras || [];
		var hc = this.readHashCam();
		this.cam = (hc && this.hasCam(hc)) ? hc : (this.cams[0] ? this.cams[0].id : '');
		try {
			this.fmtMonth = new Intl.DateTimeFormat(this.locale, { month: 'long', year: 'numeric' });
			this.fmtDay = new Intl.DateTimeFormat(this.locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
			this.fmtWd = new Intl.DateTimeFormat(this.locale, { weekday: 'short' });
		} catch (e) {
			this.fmtMonth = new Intl.DateTimeFormat('de-DE', { month: 'long', year: 'numeric' });
			this.fmtDay = new Intl.DateTimeFormat('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
			this.fmtWd = new Intl.DateTimeFormat('de-DE', { weekday: 'short' });
		}
		this.build();
		this.bind();
		this.start();
	}

	Archive.prototype.build = function () {
		var t = this.t, r = this.root, self = this;
		r.innerHTML = '';
		r.setAttribute('tabindex', '-1');

		function card(cls, title) {
			var c = el('section', 'wca-card ' + cls);
			if (title !== undefined) {
				var h = el('div', 'wca-card-head');
				var ht = el('h3', 'wca-card-title', title);
				h.appendChild(ht);
				c.appendChild(h);
				c._head = h;
				c._title = ht;
			}
			return c;
		}
		function tile(label) {
			var c = el('div', 'wca-tile');
			c.appendChild(el('div', 'wca-tile-label', label));
			c._value = el('div', 'wca-tile-value');
			c._sub = el('div', 'wca-tile-sub');
			c.appendChild(c._value);
			c.appendChild(c._sub);
			return c;
		}

		/* --- Kopfzeile: Titel, Kamera-Umschalter, Stand ----------------- */
		var header = el('header', 'wca-header');
		var hl = el('div', 'wca-header-main');
		this.headTitle = el('h2', 'wca-header-title');
		hl.appendChild(this.headTitle);
		if (this.cams.length > 1) {
			this.tabs = el('div', 'wca-tabs');
			this.tabs.setAttribute('role', 'tablist');
			this.tabs.setAttribute('aria-label', t.camera);
			this.cams.forEach(function (c) {
				var b = btn('wca-tab', c.name);
				b.setAttribute('role', 'tab');
				b.setAttribute('data-cam', c.id);
				b.setAttribute('aria-selected', c.id === self.cam ? 'true' : 'false');
				self.tabs.appendChild(b);
			});
			hl.appendChild(this.tabs);
		}
		var hr = el('div', 'wca-header-stamp');
		this.stamp = el('div', 'wca-stamp');
		this.stampAgo = el('div', 'wca-stamp-ago');
		hr.appendChild(this.stamp);
		hr.appendChild(this.stampAgo);
		header.appendChild(hl);
		header.appendChild(hr);

		/* --- Bild-Karte --------------------------------------------------- */
		var viewer = card('wca-viewer', '');
		this.dayTitle = viewer._title;
		var dnav = el('div', 'wca-daynav');
		this.btnPrevDay = btn('wca-day-prev', '‹', t.prevDay);
		this.btnNextDay = btn('wca-day-next', '›', t.nextDay);
		dnav.appendChild(this.btnPrevDay);
		dnav.appendChild(this.btnNextDay);
		viewer._head.appendChild(dnav);

		this.stage = el('div', 'wca-stage');
		this.img = el('img', 'wca-img');
		this.img.alt = '';
		this.img.decoding = 'async';
		this.btnPrev = btn('wca-nav wca-nav-prev', '‹', t.prev);
		this.btnNext = btn('wca-nav wca-nav-next', '›', t.next);
		this.loader = el('div', 'wca-loader');
		this.message = el('div', 'wca-message wca-hidden');
		this.badge = el('div', 'wca-badge');
		this.stage.appendChild(this.img);
		this.stage.appendChild(this.badge);
		this.stage.appendChild(this.btnPrev);
		this.stage.appendChild(this.btnNext);
		this.stage.appendChild(this.loader);
		this.stage.appendChild(this.message);

		var bar = el('div', 'wca-bar');
		this.btnPlay = btn('wca-play', '▶', t.play);
		this.slider = el('input', 'wca-slider');
		this.slider.type = 'range';
		this.slider.min = 0;
		this.slider.max = 0;
		this.slider.value = 0;
		this.slider.setAttribute('aria-label', 'Zeit');
		this.timeLabel = el('strong', 'wca-time');
		bar.appendChild(this.btnPlay);
		bar.appendChild(this.slider);
		bar.appendChild(this.timeLabel);

		viewer.appendChild(this.stage);
		viewer.appendChild(bar);

		/* --- Kalender-Karte ----------------------------------------------- */
		var cal = card('wca-cal');
		var head = el('div', 'wca-cal-head');
		this.btnPrevMonth = btn('wca-cal-prev', '‹', t.prevMon);
		this.calTitle = el('h3', 'wca-card-title wca-cal-title');
		this.btnNextMonth = btn('wca-cal-next', '›', t.nextMon);
		head.appendChild(this.btnPrevMonth);
		head.appendChild(this.calTitle);
		head.appendChild(this.btnNextMonth);
		this.grid = el('div', 'wca-cal-grid');
		this.grid.setAttribute('role', 'grid');
		var foot = el('div', 'wca-cal-foot');
		var legend = el('div', 'wca-legend');
		legend.innerHTML = '<span class="wca-lg wca-lg-has"></span>' + t.images + ' <span class="wca-lg wca-lg-sel"></span>' + t.selected;
		this.btnLatest = btn('wca-btn-text', t.latest);
		foot.appendChild(legend);
		foot.appendChild(this.btnLatest);
		cal.appendChild(head);
		cal.appendChild(this.grid);
		cal.appendChild(foot);

		/* --- Info-Kacheln ------------------------------------------------- */
		var tiles = el('div', 'wca-tiles');
		this.tCount = tile(t.tileCount);
		this.tRange = tile(t.tileRange);
		this.tShown = tile(t.tileShown);
		this.countLabel = el('span', 'wca-count');
		this.link = el('a', 'wca-link', t.original);
		this.link.target = '_blank';
		this.link.rel = 'noopener';
		this.tShown._sub.appendChild(this.countLabel);
		this.tShown._sub.appendChild(document.createTextNode(' · '));
		this.tShown._sub.appendChild(this.link);
		tiles.appendChild(this.tCount);
		tiles.appendChild(this.tRange);
		tiles.appendChild(this.tShown);

		/* --- Tagesverlauf ------------------------------------------------- */
		var stripCard = card('wca-strip-card', t.timeline);
		this.strip = el('div', 'wca-strip');
		stripCard.appendChild(this.strip);

		r.appendChild(header);
		r.appendChild(viewer);
		r.appendChild(cal);
		r.appendChild(tiles);
		r.appendChild(stripCard);
		r.classList.add('wca-ready');
		this.updateHeader();
		setInterval(function () { self.updateHeader(); }, 60000);
	};

	// Titel (Kameraname) und "Stand"-Anzeige wie auf der Wetterseite
	Archive.prototype.updateHeader = function () {
		this.headTitle.textContent = this.cfg.title || (this.cams.length > 1 ? this.t.archive : this.camName()) || this.t.archive;
		if (!this.lastStamp) {
			this.stamp.textContent = '';
			this.stampAgo.textContent = '';
			return;
		}
		var s = this.lastStamp, p = s.slice(0, 10).split('-'), h = s.slice(11, 13), mi = s.slice(14, 16);
		var d = new Date(+p[0], +p[1] - 1, +p[2], +h, +mi);
		var today = ymd(new Date()) === s.slice(0, 10);
		this.stamp.textContent = this.t.stand + ' ' + (today ? '' : pad(+p[2]) + '.' + p[1] + '. ') + h + ':' + mi + ' ' + this.t.clock;
		var min = Math.round((Date.now() - d.getTime()) / 60000);
		var ago;
		if (min < 1) { ago = this.t.justNow; }
		else if (min < 60) { ago = this.t.ago + ' ' + min + ' min'; }
		else if (min < 48 * 60) { ago = this.t.ago + ' ' + Math.round(min / 60) + ' h'; }
		else { ago = this.t.ago + ' ' + Math.round(min / 1440) + ' ' + this.t.days; }
		this.stampAgo.textContent = ago;
	};

	// Kacheln "Aufnahmen" und "Zeitraum" für den gewählten Tag
	Archive.prototype.updateTiles = function () {
		var n = this.images.length, t = this.t;
		this.tCount._value.textContent = n ? String(n) : '–';
		this.tCount._sub.textContent = n === 1 ? t.image + ' ' + t.thisDay : t.images + ' ' + t.thisDay;
		if (n) {
			this.tRange._value.textContent = this.images[0].time.slice(0, 5) + ' – ' + this.images[n - 1].time.slice(0, 5);
			this.tRange._sub.textContent = t.firstLast;
		} else {
			this.tRange._value.textContent = '–';
			this.tRange._sub.textContent = '';
			this.tShown._value.textContent = '–';
		}
	};

	Archive.prototype.bind = function () {
		var self = this;

		if (this.tabs) {
			this.tabs.addEventListener('click', function (e) {
				var b = e.target.closest('[data-cam]');
				if (b) { self.switchCamera(b.getAttribute('data-cam')); }
			});
		}

		this.btnPrevMonth.addEventListener('click', function () { self.shiftMonth(-1); });
		this.btnNextMonth.addEventListener('click', function () { self.shiftMonth(1); });
		this.btnLatest.addEventListener('click', function () { self.goLatest(); });
		this.btnPrevDay.addEventListener('click', function () { self.shiftDay(-1); });
		this.btnNextDay.addEventListener('click', function () { self.shiftDay(1); });

		this.grid.addEventListener('click', function (e) {
			var c = e.target.closest('[data-date]');
			if (c) { self.selectDate(c.getAttribute('data-date')); }
		});

		this.btnPrev.addEventListener('click', function () { self.stop(); self.show(self.index - 1); });
		this.btnNext.addEventListener('click', function () { self.stop(); self.show(self.index + 1); });
		this.btnPlay.addEventListener('click', function () { self.timer ? self.stop() : self.play(); });

		this.slider.addEventListener('input', function () { self.stop(); self.show(+self.slider.value, true); });
		this.slider.addEventListener('change', function () { self.show(+self.slider.value); });

		this.strip.addEventListener('click', function (e) {
			var b = e.target.closest('[data-i]');
			if (b) { self.stop(); self.show(+b.getAttribute('data-i')); }
		});

		this.img.addEventListener('load', function () { self.loading(false); });
		this.img.addEventListener('error', function () { self.loading(false); });
		this.img.addEventListener('click', function () { self.fullscreen(); });

		this.root.addEventListener('keydown', function (e) {
			if (e.target === self.slider) { return; }
			if (e.key === 'ArrowLeft') { e.preventDefault(); self.stop(); self.show(self.index - 1); }
			else if (e.key === 'ArrowRight') { e.preventDefault(); self.stop(); self.show(self.index + 1); }
			else if (e.key === ' ' && e.target.tagName !== 'BUTTON') { e.preventDefault(); self.timer ? self.stop() : self.play(); }
		});

		// Wischen auf Touch-Geräten
		var x0 = null;
		this.stage.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
		this.stage.addEventListener('touchend', function (e) {
			if (x0 === null) { return; }
			var dx = e.changedTouches[0].clientX - x0;
			x0 = null;
			if (Math.abs(dx) > 40) { self.stop(); self.show(self.index + (dx < 0 ? 1 : -1)); }
		});

		window.addEventListener('hashchange', function () {
			var c = self.readHashCam();
			if (c && c !== self.cam && self.hasCam(c)) { self.switchCamera(c); return; }
			var d = self.readHash();
			if (d && d !== self.date && self.inRange(d)) { self.openDate(d); }
		});
	};

	// API-Aufruf für die aktuell gewählte Kamera
	Archive.prototype.req = function (path, params) {
		params = params || {};
		if (this.cam) { params.kamera = this.cam; }
		return api(this.cfg, path, params);
	};

	Archive.prototype.hasCam = function (id) {
		return this.cams.some(function (c) { return c.id === id; });
	};

	Archive.prototype.camName = function () {
		for (var i = 0; i < this.cams.length; i++) { if (this.cams[i].id === this.cam) { return this.cams[i].name; } }
		return '';
	};

	Archive.prototype.switchCamera = function (id) {
		if (id === this.cam || !this.hasCam(id)) { return; }
		this.stop();
		this.cam = id;
		this.monthCache = {};
		this.dayCache = {};
		this.range = null;
		this.root.classList.remove('wca-is-empty');
		this.hideMessage();
		if (this.tabs) {
			Array.prototype.forEach.call(this.tabs.children, function (b) {
				b.setAttribute('aria-selected', b.getAttribute('data-cam') === id ? 'true' : 'false');
			});
		}
		// Gleichen Tag und gleiche Uhrzeit beibehalten, sofern vorhanden – ideal zum Vergleichen.
		this.start(this.date);
	};

	/* --- Start ---------------------------------------------------------- */

	Archive.prototype.start = function (keepDate) {
		var self = this, cam = this.cam;
		this.loading(true);
		this.req('range').then(function (r) {
			if (cam !== self.cam) { return; }
			if (!r.first) {
				self.lastStamp = null;
				self.updateHeader();
				self.root.classList.add('wca-is-empty');
				self.loading(false);
				self.images = [];
				self.renderStrip();
				self.img.removeAttribute('src');
				self.dayTitle.textContent = self.camName();
				self.timeLabel.textContent = '';
				self.countLabel.textContent = '';
				self.grid.innerHTML = '';
				self.calTitle.textContent = '';
				self.showMessage(self.t.empty);
				self.setHash(null);
				return;
			}
			self.range = { first: r.first.slice(0, 10), last: r.last.slice(0, 10) };
			self.lastStamp = r.last;
			self.updateHeader();
			var want = keepDate || self.readHash() || self.cfg.initialDate;
			self.openDate(want && self.inRange(want) ? want : self.range.last, !keepDate);
		}).catch(function (e) { self.fail(e); });
	};

	Archive.prototype.inRange = function (d) {
		return this.range && d >= this.range.first && d <= this.range.last;
	};

	Archive.prototype.fail = function (e) {
		this.loading(false);
		this.showMessage(e && (e.status === 401 || e.status === 403) ? this.t.login : this.t.error);
	};

	Archive.prototype.openDate = function (date, toLast) {
		var self = this, d = parseYmd(date);
		return this.loadMonth(d.getFullYear(), d.getMonth()).then(function () {
			return self.selectDate(date, toLast);
		});
	};

	Archive.prototype.goLatest = function () {
		var self = this;
		this.req('range').then(function (r) {
			if (!r.last) { return; }
			self.range = { first: r.first.slice(0, 10), last: r.last.slice(0, 10) };
			self.lastStamp = r.last;
			self.updateHeader();
			delete self.dayCache[self.range.last];
			delete self.monthCache[self.range.last.slice(0, 7)];
			self.openDate(self.range.last, true);
		}).catch(function (e) { self.fail(e); });
	};

	/* --- Kalender ------------------------------------------------------- */

	Archive.prototype.loadMonth = function (y, m) {
		var self = this, key = monthKey(y, m), cam = this.cam;
		this.year = y;
		this.month = m;
		var p = this.monthCache[key]
			? Promise.resolve(this.monthCache[key])
			: this.req('days', { month: key }).then(function (r) {
				if (cam === self.cam) { self.monthCache[key] = r.days || {}; }
				return r.days;
			});
		return p.then(function () {
			if (cam === self.cam && self.year === y && self.month === m) { self.renderCalendar(); }
		});
	};

	Archive.prototype.shiftMonth = function (dir) {
		var d = new Date(this.year, this.month + dir, 1);
		this.loadMonth(d.getFullYear(), d.getMonth()).catch(this.fail.bind(this));
	};

	Archive.prototype.renderCalendar = function () {
		var t = this.t, key = monthKey(this.year, this.month);
		var days = this.monthCache[key] || {};
		var ws = this.cfg.weekStart === 0 ? 0 : 1;
		var frag = document.createDocumentFragment();
		var i;

		this.calTitle.textContent = this.fmtMonth.format(new Date(this.year, this.month, 1));

		for (i = 0; i < 7; i++) {
			// 7.1.2024 war ein Sonntag
			frag.appendChild(el('div', 'wca-wd', this.fmtWd.format(new Date(2024, 0, 7 + ws + i))));
		}

		var offset = (new Date(this.year, this.month, 1).getDay() - ws + 7) % 7;
		for (i = 0; i < offset; i++) { frag.appendChild(el('div', 'wca-day wca-day-empty')); }

		var n = new Date(this.year, this.month + 1, 0).getDate();
		var today = ymd(new Date());
		var max = 0;
		Object.keys(days).forEach(function (k) { if (days[k] > max) { max = days[k]; } });

		for (var day = 1; day <= n; day++) {
			var ds = key + '-' + pad(day);
			var c = days[ds] || 0;
			var cell;
			if (c) {
				cell = btn('wca-day wca-has', String(day), this.fmtDay.format(parseYmd(ds)) + ': ' + c + ' ' + (c === 1 ? t.image : t.images));
				cell.setAttribute('data-date', ds);
				cell.style.setProperty('--wca-fill', (0.35 + 0.65 * c / max).toFixed(2));
				if (ds === this.date) { cell.classList.add('wca-selected'); cell.setAttribute('aria-pressed', 'true'); }
			} else {
				cell = el('div', 'wca-day', String(day));
			}
			if (ds === today) { cell.classList.add('wca-today'); }
			frag.appendChild(cell);
		}

		this.grid.innerHTML = '';
		this.grid.appendChild(frag);

		if (this.range) {
			this.btnPrevMonth.disabled = key <= this.range.first.slice(0, 7);
			this.btnNextMonth.disabled = key >= this.range.last.slice(0, 7);
		}
	};

	/* --- Tag ------------------------------------------------------------ */

	Archive.prototype.selectDate = function (date, toLast) {
		var self = this, cam = this.cam;
		this.stop();
		this.date = date;
		this.renderCalendar();
		this.setHash(date);
		this.dayTitle.textContent = this.fmtDay.format(parseYmd(date));
		this.updateDayButtons();
		this.loading(true);

		var cached = this.dayCache[date];
		var p = cached ? Promise.resolve(cached) : this.req('images', { date: date }).then(function (r) {
			// Den jüngsten Tag nicht cachen – dort kommen laufend neue Bilder dazu.
			if (cam === self.cam && self.range && date !== self.range.last) { self.dayCache[date] = r.images; }
			return r.images;
		});

		return p.then(function (imgs) {
			if (self.date !== date || self.cam !== cam) { return; }
			self.images = imgs || [];
			self.renderStrip();
			if (!self.images.length) {
				self.loading(false);
				self.img.removeAttribute('src');
				self.timeLabel.textContent = '';
				self.countLabel.textContent = '';
				self.link.removeAttribute('href');
				self.showMessage(self.t.noImages);
				return;
			}
			self.hideMessage();
			var idx = self.images.length - 1;
			if (!toLast && self.minutes !== null) { idx = self.nearest(self.minutes); }
			self.show(idx);
		}).catch(function (e) { self.fail(e); });
	};

	// Gleiche Uhrzeit wie am zuvor angesehenen Tag – praktisch zum Vergleichen.
	Archive.prototype.nearest = function (min) {
		var best = 0, diff = Infinity;
		for (var i = 0; i < this.images.length; i++) {
			var d = Math.abs(toMin(this.images[i].time) - min);
			if (d < diff) { diff = d; best = i; }
		}
		return best;
	};

	Archive.prototype.updateDayButtons = function () {
		if (!this.range) { return; }
		this.btnPrevDay.disabled = this.date <= this.range.first;
		this.btnNextDay.disabled = this.date >= this.range.last;
	};

	// Zum nächsten/vorherigen Tag mit Bildern springen (auch über Monatsgrenzen).
	Archive.prototype.shiftDay = function (dir) {
		var self = this;
		var d0 = parseYmd(this.date);
		var y = d0.getFullYear(), m = d0.getMonth();

		function search(y, m, after) {
			var key = monthKey(y, m);
			if (key < self.range.first.slice(0, 7) || key > self.range.last.slice(0, 7)) { return Promise.resolve(null); }
			var load = self.monthCache[key] ? Promise.resolve() : self.req('days', { month: key }).then(function (r) { self.monthCache[key] = r.days || {}; });
			return load.then(function () {
				var list = Object.keys(self.monthCache[key]).sort();
				if (dir < 0) { list.reverse(); }
				for (var i = 0; i < list.length; i++) {
					if (!after || (dir > 0 ? list[i] > after : list[i] < after)) { return list[i]; }
				}
				var n = new Date(y, m + dir, 1);
				return search(n.getFullYear(), n.getMonth(), null);
			});
		}

		search(y, m, this.date).then(function (found) {
			if (found) { self.openDate(found); }
		}).catch(this.fail.bind(this));
	};

	/* --- Bilder ----------------------------------------------------------- */

	Archive.prototype.renderStrip = function () {
		var frag = document.createDocumentFragment();
		this.images.forEach(function (im, i) {
			var b = el('button', 'wca-thumb');
			b.type = 'button';
			b.setAttribute('data-i', i);
			b.title = im.time.slice(0, 5);
			var img = el('img');
			img.loading = 'lazy';
			img.decoding = 'async';
			img.src = im.thumb;
			img.alt = im.time.slice(0, 5);
			b.appendChild(img);
			b.appendChild(el('span', '', im.time.slice(0, 5)));
			frag.appendChild(b);
		});
		this.strip.innerHTML = '';
		this.strip.appendChild(frag);
		this.updateTiles();
		this.slider.max = Math.max(0, this.images.length - 1);
		this.slider.disabled = this.images.length < 2;
		this.btnPlay.disabled = this.images.length < 2;
	};

	Archive.prototype.show = function (idx, quick) {
		if (!this.images.length) { return; }
		idx = Math.max(0, Math.min(this.images.length - 1, idx));
		var im = this.images[idx];
		var prevActive = this.strip.children[this.index];
		this.index = idx;
		this.minutes = toMin(im.time);

		var src = quick ? im.thumb : im.full;
		if (this.img.getAttribute('src') !== src) {
			if (!quick && !this.timer) { this.loading(true); }
			this.img.src = src;
		}
		if (im.w && im.h) { this.img.style.aspectRatio = im.w + ' / ' + im.h; }
		this.img.alt = this.dayTitle.textContent + ', ' + im.time.slice(0, 5) + ' ' + this.t.clock;

		this.slider.value = idx;
		this.timeLabel.textContent = im.time.slice(0, 5) + ' ' + this.t.clock;
		this.tShown._value.textContent = im.time.slice(0, 5);
		this.badge.textContent = im.time.slice(0, 5);
		this.countLabel.textContent = (idx + 1) + ' / ' + this.images.length;
		this.link.href = im.full;
		this.btnPrev.disabled = idx === 0;
		this.btnNext.disabled = idx === this.images.length - 1;

		if (prevActive) { prevActive.classList.remove('wca-active'); }
		var active = this.strip.children[idx];
		if (active) {
			active.classList.add('wca-active');
			this.strip.scrollLeft = active.offsetLeft - this.strip.clientWidth / 2 + active.clientWidth / 2;
		}

		if (!quick) { this.preload(idx + 1); this.preload(idx - 1); }
	};

	Archive.prototype.preload = function (i) {
		if (i >= 0 && i < this.images.length) { (new Image()).src = this.images[i].full; }
	};

	/* --- Zeitraffer ------------------------------------------------------- */

	Archive.prototype.play = function () {
		var self = this;
		if (this.images.length < 2) { return; }
		if (this.index >= this.images.length - 1) { this.show(0); }
		this.btnPlay.textContent = '❚❚';
		this.btnPlay.setAttribute('aria-label', this.t.pause);
		this.btnPlay.title = this.t.pause;
		this.root.classList.add('wca-playing');
		this.timer = setInterval(function () {
			if (self.index >= self.images.length - 1) { self.stop(); return; }
			self.show(self.index + 1);
		}, Math.round(1000 / (this.cfg.fps || 4)));
	};

	Archive.prototype.stop = function () {
		if (!this.timer) { return; }
		clearInterval(this.timer);
		this.timer = null;
		this.btnPlay.textContent = '▶';
		this.btnPlay.setAttribute('aria-label', this.t.play);
		this.btnPlay.title = this.t.play;
		this.root.classList.remove('wca-playing');
	};

	/* --- Sonstiges -------------------------------------------------------- */

	Archive.prototype.fullscreen = function () {
		var s = this.stage;
		if (document.fullscreenElement) { document.exitFullscreen(); return; }
		if (s.requestFullscreen) { s.requestFullscreen().catch(function () {}); }
		else if (this.link.href) { window.open(this.link.href, '_blank', 'noopener'); }
	};

	Archive.prototype.loading = function (on) { this.root.classList.toggle('wca-loading', !!on); };
	Archive.prototype.showMessage = function (txt) { this.message.textContent = txt; this.message.classList.remove('wca-hidden'); };
	Archive.prototype.hideMessage = function () { this.message.classList.add('wca-hidden'); };

	Archive.prototype.readHash = function () {
		var m = /(?:^|[#&])wca=(\d{4}-\d{2}-\d{2})/.exec(window.location.hash);
		return m ? m[1] : null;
	};
	Archive.prototype.readHashCam = function () {
		var m = /(?:^|[#&])kamera=([a-z0-9_-]+)/.exec(window.location.hash);
		return m ? m[1] : null;
	};
	Archive.prototype.setHash = function (d) {
		if (!window.history || !history.replaceState) { return; }
		var parts = [];
		if (d) { parts.push('wca=' + d); }
		if (this.cams.length > 1 && this.cam) { parts.push('kamera=' + this.cam); }
		history.replaceState(null, '', parts.length ? '#' + parts.join('&') : window.location.pathname + window.location.search);
	};

	/* ------------------------------------------------------------------ *
	 * [webcam_aktuell] – automatische Aktualisierung
	 * ------------------------------------------------------------------ */

	function Latest(root) {
		var cfg = JSON.parse(root.getAttribute('data-wca') || '{}');
		var img = root.querySelector('img');
		var cap = root.querySelector('.wca-latest-time');
		if (!img || !cfg.refresh) { return; }
		var fmt;
		try { fmt = new Intl.DateTimeFormat(cfg.locale || 'de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
		catch (e) { fmt = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }

		var link = root.querySelector('a');
		var busy = false;

		function update() {
			if (document.hidden || busy) { return; }
			busy = true;
			api(cfg, 'latest', cfg.camera ? { kamera: cfg.camera } : {}).then(function (r) {
				busy = false;
				var im = r.image;
				if (!im || img.getAttribute('src') === im.full) { return; }
				var pre = new Image();
				pre.onload = function () {
					img.src = im.full;
					img.removeAttribute('srcset');
					if (im.w && im.h) { img.width = im.w; img.height = im.h; }
					// Link auf das Bild selbst mitziehen; Links ins Archiv auf den Tag des Bildes setzen.
					if (link && cfg.linkImage) { link.href = im.full; }
					else if (link && cfg.linkArchive) { link.href = cfg.linkArchive + '#wca=' + im.date + '&kamera=' + cfg.camera; }
					var p = im.date.split('-'), t = im.time.split(':');
					if (cap) { cap.textContent = fmt.format(new Date(+p[0], +p[1] - 1, +p[2], +t[0], +t[1])) + ' Uhr'; }
				};
				pre.src = im.full;
			}).catch(function () { busy = false; });
		}

		// Sofort nachsehen: Kommt die Seite aus einem Seiten-Cache, ist das Bild im HTML evtl. veraltet.
		update();
		setInterval(update, cfg.refresh * 1000);
		// Nach dem Zurückwechseln in den Tab nicht bis zum nächsten Intervall warten.
		document.addEventListener('visibilitychange', update);
	}

	/* ------------------------------------------------------------------ */

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('.wca-archive:not(.wca-ready)'), function (r) { new Archive(r); });
		Array.prototype.forEach.call(document.querySelectorAll('.wca-latest[data-wca]'), function (r) { new Latest(r); });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
