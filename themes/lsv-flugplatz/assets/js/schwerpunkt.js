/**
 * Schwerpunktrechner für den Shortcode [schwerpunkt] (inc/schwerpunkt.php).
 * Flugzeugdaten kommen aus den data-Attributen des Containers.
 */
(function () {
	function init(root) {
	var d = root.dataset, num = function (v) { return parseFloat(String(v).replace(',', '.')) || 0; };
	var A = {
		leerKg: num(d.leerMasse), leerArm: num(d.leerArm), sitze: num(d.armSitze), gepaeck: num(d.armGepaeck),
		fluegel: num(d.armFluegel), tank: num(d.armTank), kgL: num(d.kgProLiter), lh: num(d.verbrauch),
		mtom: num(d.mtom), vorn: num(d.cgVorn), hinten: num(d.cgHinten), le: num(d.macVorderkante), mac: num(d.mac)
	};
	var f = function (n, s) { return n.toLocaleString('de-DE', { minimumFractionDigits: s, maximumFractionDigits: s }); };
	var out = function (k, t) { root.querySelectorAll('[data-out="' + k + '"]').forEach(function (e) { e.textContent = t; }); };
	var inp = {}; root.querySelectorAll('[data-in]').forEach(function (e) { inp[e.dataset.in] = e; });
	var val = function (k) { return Math.max(0, num(inp[k].value)); };
	var pctMac = function (cg) { return (cg - A.le) * 100 / A.mac; };
	var imBereich = function (x) { return x >= A.vorn && x <= A.hinten; };
	var grenzen = { pilot: [num(d.pilotMin), num(d.pilotMax)], gepaeck: [0, num(d.maxGepaeck)], 'fluegel-l': [0, num(d.maxFluegel)], 'fluegel-r': [0, num(d.maxFluegel)] };

	out('leer-kg', f(A.leerKg, 1) + ' kg'); out('leer-arm', f(A.leerArm, 2) + ' mm');
	out('arm-sitze', f(A.sitze, 0) + ' mm'); out('arm-gepaeck', f(A.gepaeck, 0) + ' mm');
	out('arm-fluegel', f(A.fluegel, 0) + ' mm'); out('arm-tank', f(A.tank, 0) + ' mm');
	out('mtom', f(A.mtom, 0) + ' kg');
	out('grenzen', f(A.vorn, 0) + '–' + f(A.hinten, 0) + ' mm (' + f(pctMac(A.vorn), 0) + '–' + f(pctMac(A.hinten), 0) + ' %MAC)');
	out('grenze-vorn', f(A.vorn, 0) + ' mm'); out('grenze-hinten', f(A.hinten, 0) + ' mm');
	out('verbrauch', 'bei ' + f(A.lh, 0) + ' l/h');

	function rechne() {
		var tankKg = val('liter') * A.kgL;
		var posten = [
			['leer', A.leerKg, A.leerArm], ['pilot', val('pilot'), A.sitze], ['passagier', val('passagier'), A.sitze],
			['gepaeck', val('gepaeck'), A.gepaeck], ['fluegel-l', val('fluegel-l'), A.fluegel], ['fluegel-r', val('fluegel-r'), A.fluegel],
			['tank', tankKg, A.tank]
		];
		var masse = 0, moment = 0;
		posten.forEach(function (p) { var m = p[1] * p[2]; masse += p[1]; moment += m; out('m-' + p[0], f(m, 0)); });
		var cg = moment / masse, masse0 = masse - tankKg, cg0 = (moment - tankKg * A.tank) / masse0;
		var masseOk = masse <= A.mtom, cgOk = imBereich(cg), cg0Ok = imBereich(cg0);

		out('tank-kg', f(tankKg, 1) + ' kg');
		out('masse', f(masse, 1) + ' kg');
		out('masse-info', masseOk ? 'noch ' + f(A.mtom - masse, 1) + ' kg bis ' + f(A.mtom, 0) + ' kg' : f(masse - A.mtom, 1) + ' kg zu schwer');
		out('cg', f(cg, 1) + ' mm'); out('cg-mac', f(pctMac(cg), 1) + ' %MAC');
		out('cg0', f(cg0, 1) + ' mm'); out('cg0-mac', f(pctMac(cg0), 1) + ' %MAC');
		out('flugzeit', A.lh ? (function (h) { var hh = Math.floor(h), mm = Math.round((h - hh) * 60); if (mm === 60) { hh++; mm = 0; } return hh + ':' + (mm < 10 ? '0' : '') + mm + ' h'; })(val('liter') / A.lh) : '–');

		var setBox = function (k, ok) { var b = root.querySelector('[data-box="' + k + '"]'); b.classList.toggle('ist-gut', ok); b.classList.toggle('ist-schlecht', !ok); };
		setBox('masse', masseOk); setBox('cg', cgOk); setBox('cg0', cg0Ok);

		// Skala: Grenzen liegen bei 10 % und 90 % der Breite.
		var pos = function (x) { return Math.max(0, Math.min(100, 10 + (x - A.vorn) / (A.hinten - A.vorn) * 80)); };
		root.querySelector('[data-marke="cg"]').style.left = pos(cg) + '%';
		root.querySelector('[data-marke="cg0"]').style.left = pos(cg0) + '%';

		var h = [];
		if (!masseOk) { h.push('Startmasse überschritten: ' + f(masse, 1) + ' kg statt max. ' + f(A.mtom, 0) + ' kg.'); }
		if (cg < A.vorn) { h.push('Schwerpunkt beim Start zu weit vorn (' + f(cg, 1) + ' mm, Grenze ' + f(A.vorn, 0) + ' mm).'); }
		if (cg > A.hinten) { h.push('Schwerpunkt beim Start zu weit hinten (' + f(cg, 1) + ' mm, Grenze ' + f(A.hinten, 0) + ' mm).'); }
		if (cgOk && !cg0Ok) { h.push('Mit leerem Tank wandert der Schwerpunkt aus dem zulässigen Bereich (' + f(cg0, 1) + ' mm).'); }
		Object.keys(grenzen).forEach(function (k) {
			var v = val(k), g = grenzen[k], zuLeicht = k === 'pilot' && g[0] > 0 && v < g[0], zuviel = (g[1] > 0 && v > g[1]) || zuLeicht;
			inp[k].classList.toggle('lsv-sp-zuviel', zuviel);
			if (zuviel) {
				var name = inp[k].getAttribute('aria-label').replace(' in kg', '');
				h.push(zuLeicht ? 'Pilot unter dem Mindestgewicht von ' + g[0] + ' kg.' : name + ': max. ' + g[1] + ' kg.');
			}
		});
		var ul = root.querySelector('[data-out="hinweise"]');
		ul.innerHTML = '';
		(h.length ? h : ['Startmasse und Schwerpunkt liegen innerhalb der Grenzen.']).forEach(function (t) {
			var li = document.createElement('li'); li.textContent = t; if (!h.length) { li.className = 'ok'; } ul.appendChild(li);
		});
	}
	Object.keys(inp).forEach(function (k) { inp[k].addEventListener('input', rechne); });
	rechne();
	}
	document.querySelectorAll('.lsv-sp[data-mtom]').forEach(init);
})();
