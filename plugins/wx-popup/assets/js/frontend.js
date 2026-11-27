/**
 * WX Popup – öffnet die Pop-ups nach der eingestellten Zeit.
 * Merkt sich „schon gesehen“ im localStorage / sessionStorage, ohne Cookies.
 */
(function () {
	'use strict';

	var speicher = function (art) { try { return window[art]; } catch (e) { return null; } };
	var lies = function (art, k) { var s = speicher(art); try { return s ? s.getItem(k) : null; } catch (e) { return null; } };
	var schreib = function (art, k, v) { var s = speicher(art); try { if (s) { s.setItem(k, v); } } catch (e) { /* privater Modus */ } };

	var offen = null, vorher = null, warteschlange = [];

	function schluessel(d) { return 'wxpu-' + d.id + '-' + d.version; }

	function darf(d) {
		var jetzt = Date.now();
		if (d.from && jetzt < d.from) { return false; }
		if (d.until && jetzt > d.until) { return false; }
		if (d.repeat === 'sitzung') { return !lies('sessionStorage', schluessel(d)); }
		if (d.repeat === 'tage') {
			var zuletzt = parseInt(lies('localStorage', schluessel(d)), 10);
			return !zuletzt || jetzt - zuletzt > d.days * 864e5;
		}
		return true; // immer, vorschau
	}

	function merken(d) {
		if (d.repeat === 'sitzung') { schreib('sessionStorage', schluessel(d), '1'); }
		if (d.repeat === 'tage') { schreib('localStorage', schluessel(d), String(Date.now())); }
	}

	function oeffnen(el) {
		if (offen) { if (warteschlange.indexOf(el) < 0 && offen !== el) { warteschlange.push(el); } return; }
		offen = el;
		vorher = document.activeElement;
		el.hidden = false;
		document.documentElement.classList.add('wxpu-offen');
		requestAnimationFrame(function () { el.classList.add('ist-offen'); });
		el.querySelector('.wxpu-fenster').focus();
		try { merken(JSON.parse(el.getAttribute('data-wxpu'))); } catch (e) { /* egal */ }
	}

	function schliessen() {
		if (!offen) { return; }
		var el = offen;
		el.classList.remove('ist-offen');
		setTimeout(function () { el.hidden = true; }, 200);
		offen = null;
		document.documentElement.classList.remove('wxpu-offen');
		if (vorher && vorher.focus) { vorher.focus(); }
		if (warteschlange.length) { setTimeout(function () { oeffnen(warteschlange.shift()); }, 400); }
	}

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (t.closest('[data-wxpu-schliessen]')) { schliessen(); return; }
		if (t.hasAttribute('data-wxpu-schliessen-aussen') && offen) {
			var d = JSON.parse(offen.getAttribute('data-wxpu'));
			if (d.outside) { schliessen(); }
			return;
		}
		// Links auf #popup-123 öffnen das Pop-up von Hand.
		var a = t.closest('a[href^="#popup-"]');
		if (a) {
			var ziel = document.getElementById(a.getAttribute('href').slice(1));
			if (ziel && ziel.classList.contains('wxpu')) { e.preventDefault(); oeffnen(ziel); }
		}
	});

	document.addEventListener('keydown', function (e) {
		if (!offen) { return; }
		if (e.key === 'Escape') { schliessen(); return; }
		if (e.key === 'Tab') { // Fokus im Pop-up halten
			var f = offen.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
			if (!f.length) { e.preventDefault(); return; }
			var erst = f[0], letzt = f[f.length - 1];
			if (e.shiftKey && document.activeElement === erst) { e.preventDefault(); letzt.focus(); }
			else if (!e.shiftKey && document.activeElement === letzt) { e.preventDefault(); erst.focus(); }
		}
	});

	function start() {
		document.querySelectorAll('.wxpu[data-wxpu]').forEach(function (el) {
			var d;
			try { d = JSON.parse(el.getAttribute('data-wxpu')); } catch (e) { return; }
			if (!darf(d)) { return; }
			setTimeout(function () { oeffnen(el); }, Math.max(0, d.delay) * 1000);
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
