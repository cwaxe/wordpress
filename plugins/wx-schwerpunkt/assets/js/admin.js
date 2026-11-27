/* WX Schwerpunkt – Einstellungsseite: Zeilen und Flugzeuge hinzufügen bzw. entfernen. */
(function () {
	'use strict';
	var zaehler = Date.now();
	var neu = function () { return 'n' + (zaehler++); };

	document.addEventListener('click', function (e) {
		var t = e.target;

		if (t.classList.contains('wxsp-zeile-neu')) {
			var tab = t.closest('.inside').querySelector('.wxsp-posten');
			var html = document.getElementById('wxsp-vorlage-zeile').innerHTML
				.split('__B__').join(tab.getAttribute('data-basis'))
				.split('__P__').join(neu());
			tab.querySelector('tbody').insertAdjacentHTML('beforeend', html);
			tab.querySelector('tbody tr:last-child input').focus();
		}

		if (t.classList.contains('wxsp-zeile-weg')) {
			t.closest('tr').remove();
		}

		if (t.classList.contains('wxsp-flugzeug-neu')) {
			var html2 = document.getElementById('wxsp-vorlage-flugzeug').innerHTML.split('__F__').join(neu());
			var liste = document.querySelector('.wxsp-liste');
			liste.insertAdjacentHTML('beforeend', html2);
			var box = liste.lastElementChild;
			box.removeAttribute('data-vorlage');
			box.querySelector('input').focus();
		}
	});
})();
