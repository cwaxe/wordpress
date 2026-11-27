/**
 * WX Schwerpunkt – Rechner im Frontend.
 * Daten kommen als JSON aus data-wxsp (siehe class-wxsp-frontend.php).
 *
 * Moment = Gewicht × Arm, Schwerpunkt = Summe Momente / Masse,
 * %MAC = (Schwerpunkt − MAC-Vorderkante) × 100 / MAC, Kraftstoff = Liter × kg/l.
 */
(function () {
	'use strict';

	var fmt = function (n, s) { return n.toLocaleString('de-DE', { minimumFractionDigits: s, maximumFractionDigits: s }); };
	var num = function (v) { return Math.max(0, parseFloat(String(v).replace(',', '.')) || 0); };

	function Rechner(root) {
		var A;
		try { A = JSON.parse(root.getAttribute('data-wxsp')); } catch (e) { return; }

		var felder = Array.prototype.slice.call(root.querySelectorAll('input[data-posten]'));
		var tankFeld = root.querySelector('input[data-tank]');
		var alle = felder.concat(tankFeld ? [tankFeld] : []);

		var out = function (k, t) { var e = root.querySelector('[data-out="' + k + '"]'); if (e) { e.textContent = t; } };
		var box = function (k, ok) {
			var b = root.querySelector('[data-box="' + k + '"]');
			if (!b) { return; }
			b.classList.toggle('ist-gut', ok === true);
			b.classList.toggle('ist-schlecht', ok === false);
		};
		var pctMac = function (cg) { return (cg - A.le) * 100 / A.mac; };
		var imBereich = function (x) { return x >= A.vorn && x <= A.hinten; };
		var hinweise = function (liste, art) {
			var ul = root.querySelector('[data-out="hinweise"]');
			ul.innerHTML = '';
			liste.forEach(function (t) {
				var li = document.createElement('li');
				li.textContent = t;
				if (art) { li.className = art; }
				ul.appendChild(li);
			});
		};
		/**
		 * Zulässige Kraftstoffmenge (Liter) für die Beladung ohne Kraftstoff (m0 kg, Moment M0).
		 * Grenzen: MTOM, nutzbarer Tankinhalt und beide Schwerpunktgrenzen. Der Schwerpunkt
		 * mit f kg Kraftstoff ist (M0 + f·a) / (m0 + f); daraus folgt je Grenze X:
		 * f·(a − X) ≥ X·m0 − M0 (vordere Grenze) bzw. ≤ (hintere Grenze).
		 */
		var spritBereich = function (m0, M0) {
			var a = A.tank, minKg = 0, maxKg = Infinity, grund = '';
			var setzeMax = function (kg, warum) { if (kg < maxKg) { maxKg = kg; grund = warum; } };
			setzeMax(A.mtom - m0, 'MTOM ' + fmt(A.mtom, 0) + ' kg');
			if (A.tankMax > 0) { setzeMax(A.tankMax * A.kgL, 'Tankinhalt ' + fmt(A.tankMax, 0) + ' l'); }
			[[A.vorn, 1, 'vordere Schwerpunktgrenze'], [A.hinten, -1, 'hintere Schwerpunktgrenze']].forEach(function (g) {
				var X = g[0], richtung = g[1], k = (a - X) * richtung, r = (X * m0 - M0) * richtung;
				if (k > 0) { minKg = Math.max(minKg, r / k); }           // f ≥ r / k
				else if (k < 0) { setzeMax(r / k, g[2]); }               // f ≤ r / k
				else if (r > 0) { setzeMax(-1, g[2]); }                  // Tank genau auf der Grenze und Grenze verletzt
			});
			return {
				// kleine Toleranz gegen Rundungsfehler (60 l × 0,72 / 0,72 = 59,999…)
				min: A.kgL ? Math.ceil(minKg / A.kgL - 1e-6) : 0,
				max: A.kgL ? Math.floor(maxKg / A.kgL + 1e-6) : 0,
				grund: grund,
				moeglich: maxKg >= minKg
			};
		};
		var marken = function (sichtbar) {
			root.querySelectorAll('[data-marke]').forEach(function (m) { m.style.display = sichtbar ? '' : 'none'; });
		};

		function leer(text) {
			['masse', 'cg', 'cg0', 'flugzeit'].forEach(function (k) { out(k, '–'); });
			['masse-info', 'cg-mac', 'cg0-mac', 'tank-kg', 'm-tank'].forEach(function (k) { out(k, ''); });
			root.querySelectorAll('[data-moment]').forEach(function (e) { e.textContent = ''; });
			['masse', 'cg', 'cg0', 'sprit'].forEach(function (k) { box(k, null); });
			out('sprit', '–'); out('sprit-info', '');
			alle.forEach(function (i) { i.classList.remove('wxsp-zuviel'); });
			marken(false);
			hinweise([text], 'info');
		}

		function rechne() {
			var pflichtFehlt = A.posten.some(function (p, i) { return p.pflicht && !num(felder[i].value); });
			if (pflichtFehlt) {
				var namen = A.posten.filter(function (p) { return p.pflicht; }).map(function (p) { return p.name; });
				leer('Bitte mindestens ' + namen.join(', ') + ' eintragen.');
				return;
			}

			var liter = tankFeld ? num(tankFeld.value) : 0;
			var tankKg = liter * A.kgL;
			var masse = A.leerKg, moment = A.leerKg * A.leerArm, h = [];

			A.posten.forEach(function (p, i) {
				var kg = num(felder[i].value), m = kg * p.arm;
				masse += kg; moment += m;
				var cell = root.querySelector('[data-moment="' + i + '"]');
				if (cell) { cell.textContent = kg ? fmt(m, 0) : ''; }
				var zuLeicht = p.min > 0 && kg > 0 && kg < p.min;
				var zuSchwer = p.max > 0 && kg > p.max;
				felder[i].classList.toggle('wxsp-zuviel', zuLeicht || zuSchwer);
				if (zuSchwer) { h.push(p.name + ': max. ' + fmt(p.max, 0) + ' kg.'); }
				if (zuLeicht) { h.push(p.name + ': mindestens ' + fmt(p.min, 0) + ' kg.'); }
			});

			masse += tankKg; moment += tankKg * A.tank;
			var cg = moment / masse;
			var masse0 = masse - tankKg, cg0 = (moment - tankKg * A.tank) / masse0;
			var masseOk = masse <= A.mtom, cgOk = imBereich(cg), cg0Ok = imBereich(cg0);
			var sp = spritBereich(masse0, moment - tankKg * A.tank);
			var spritOk = sp.moeglich && liter <= sp.max && liter >= sp.min;
			if (tankFeld) { tankFeld.classList.toggle('wxsp-zuviel', !spritOk); }
			if (!sp.moeglich) {
				out('sprit', 'keine'); out('sprit-info', 'Beladung ändern');
			} else {
				out('sprit', fmt(Math.max(0, sp.max), 0) + ' l');
				out('sprit-info', 'begrenzt durch ' + sp.grund + (sp.min > 0 ? ' · mind. ' + fmt(sp.min, 0) + ' l' : ''));
			}
			box('sprit', spritOk);

			out('tank-kg', fmt(tankKg, 1) + ' kg');
			out('m-tank', liter ? fmt(tankKg * A.tank, 0) : '');
			out('masse', fmt(masse, 1) + ' kg');
			out('masse-info', masseOk ? 'noch ' + fmt(A.mtom - masse, 1) + ' kg bis ' + fmt(A.mtom, 0) + ' kg' : fmt(masse - A.mtom, 1) + ' kg zu schwer');
			out('cg', fmt(cg, 1) + ' mm'); out('cg-mac', fmt(pctMac(cg), 1) + ' %MAC');
			out('cg0', fmt(cg0, 1) + ' mm'); out('cg0-mac', fmt(pctMac(cg0), 1) + ' %MAC');
			if (A.lh > 0) {
				var std = liter / A.lh, hh = Math.floor(std), mm = Math.round((std - hh) * 60);
				if (mm === 60) { hh++; mm = 0; }
				out('flugzeit', hh + ':' + (mm < 10 ? '0' : '') + mm + ' h');
			}
			box('masse', masseOk); box('cg', cgOk); box('cg0', cg0Ok);

			// Skala: die Grenzen liegen bei 10 % und 90 % der Breite.
			var pos = function (x) { return Math.max(0, Math.min(100, 10 + (x - A.vorn) / (A.hinten - A.vorn) * 80)); };
			marken(true);
			root.querySelector('[data-marke="cg"]').style.left = pos(cg) + '%';
			root.querySelector('[data-marke="cg0"]').style.left = pos(cg0) + '%';

			if (!masseOk) { h.unshift('Startmasse überschritten: ' + fmt(masse, 1) + ' kg statt max. ' + fmt(A.mtom, 0) + ' kg.'); }
			if (cg < A.vorn) { h.push('Schwerpunkt beim Start zu weit vorn (' + fmt(cg, 1) + ' mm, Grenze ' + fmt(A.vorn, 0) + ' mm).'); }
			if (cg > A.hinten) { h.push('Schwerpunkt beim Start zu weit hinten (' + fmt(cg, 1) + ' mm, Grenze ' + fmt(A.hinten, 0) + ' mm).'); }
			if (cgOk && !cg0Ok) { h.push('Mit leerem Tank wandert der Schwerpunkt aus dem zulässigen Bereich (' + fmt(cg0, 1) + ' mm).'); }
			if (!sp.moeglich) {
				h.push('Mit dieser Beladung ist keine Kraftstoffmenge zulässig – Masse oder Schwerpunkt passen nicht.');
			} else if (liter > sp.max) {
				h.push('Kraftstoff: bei dieser Beladung höchstens ' + fmt(Math.max(0, sp.max), 0) + ' l (' + sp.grund + ').');
			} else if (liter < sp.min) {
				h.push('Kraftstoff: bei dieser Beladung mindestens ' + fmt(sp.min, 0) + ' l, sonst liegt der Schwerpunkt zu weit hinten.');
			}

			if (h.length) { hinweise(h); } else { hinweise(['Startmasse und Schwerpunkt liegen innerhalb der Grenzen.'], 'ok'); }
		}

		alle.forEach(function (i) { i.addEventListener('input', rechne); });
		// Bei jedem Aufruf leer starten – auch wenn der Browser die Seite aus dem Verlauf wiederherstellt.
		var zuruecksetzen = function () { alle.forEach(function (i) { i.value = ''; }); rechne(); };
		window.addEventListener('pageshow', zuruecksetzen);
		zuruecksetzen();
	}

	function start() { document.querySelectorAll('.wxsp[data-wxsp]').forEach(function (r) { new Rechner(r); }); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
