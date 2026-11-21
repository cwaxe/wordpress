/* WX Easy Backup – Admin-Oberfläche */
(function () {
	'use strict';

	var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
	var running = null;
	var PHASES = {
		init: 'Vorbereitung', db: 'Datenbank wird exportiert', db_add: 'Datenbank wird archiviert',
		scan: 'Dateien werden ermittelt', files: 'Dateien werden gesichert', finalize: 'Abschluss',
		extract: 'Archiv wird entpackt', import: 'Datenbank wird importiert',
		replace: 'URLs und Pfade werden angepasst', finish: 'Datenbank wird ausgetauscht',
		done: 'Fertig'
	};

	function post(action, data, file) {
		var fd = new FormData();
		fd.append('action', 'wpkb_' + action);
		fd.append('nonce', WPKB.nonce);
		Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
		if (file) { fd.append('chunk', file, 'chunk'); }
		return fetch(WPKB.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) {
				return r.text().then(function (t) {
					var json;
					try { json = JSON.parse(t); } catch (e) {
						var err = new Error('Ungültige Serverantwort (HTTP ' + r.status + ')' + (t ? ': ' + t.replace(/<[^>]+>/g, ' ').trim().slice(0, 300) : ''));
						err.retry = r.status === 0 || r.status >= 500 || r.status === 429;
						throw err;
					}
					if (!json.success) {
						var e2 = new Error((json.data && json.data.message) || 'Unbekannter Fehler');
						e2.data = json.data;
						throw e2;
					}
					return json.data;
				});
			}, function (netErr) {
				var e = new Error('Netzwerkfehler: ' + netErr.message);
				e.retry = true;
				throw e;
			});
	}

	function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

	function refreshList() {
		return post('list').then(function (d) { $('#wpkb-list').innerHTML = d.html; });
	}

	/* ---------------- Fortschritt ---------------- */

	function showProgress(title) {
		var box = $('#wpkb-progress');
		box.hidden = false;
		box.classList.remove('is-error', 'is-done');
		$('#wpkb-progress-title').textContent = title;
		$('#wpkb-log').textContent = '';
		$('#wpkb-result').innerHTML = '';
		$('#wpkb-cancel').hidden = false;
		setProgress(0, 'init');
		box.scrollIntoView({ behavior: 'smooth', block: 'start' });
		document.querySelectorAll('.wpkb-grid button, .wpkb-table button').forEach(function (b) { b.disabled = true; });
	}

	function enableUi() {
		document.querySelectorAll('.wpkb-grid button, .wpkb-table button').forEach(function (b) { b.disabled = false; });
	}

	function setProgress(p, phase) {
		$('#wpkb-bar-fill').style.width = p + '%';
		$('#wpkb-percent').textContent = (Math.round(p * 10) / 10).toLocaleString('de-DE') + ' %';
		$('#wpkb-phase').textContent = PHASES[phase] || phase || '';
	}

	function log(lines) {
		var el = $('#wpkb-log');
		(lines || []).forEach(function (l) { el.textContent += l + '\n'; });
		el.scrollTop = el.scrollHeight;
	}

	function runJob(job) {
		running = job.id;
		log(job.log);
		var failures = 0;

		function next() {
			if (running !== job.id) { return Promise.resolve(); }
			return post('step', { id: job.id }).then(function (d) {
				failures = 0;
				log(d.log);
				setProgress(d.progress, d.phase);
				if (d.status === 'running') { return next(); }
				return finished(d);
			}, function (err) {
				if (err.retry && failures < 6) {
					failures++;
					log(['… ' + err.message + ' – neuer Versuch ' + failures + '/6']);
					return wait(3000 * failures).then(next);
				}
				throw err;
			});
		}
		return next().catch(function (err) {
			fail(err.message);
		});
	}

	function finished(d) {
		running = null;
		$('#wpkb-cancel').hidden = true;
		var box = $('#wpkb-progress');
		if (d.status === 'error') {
			box.classList.add('is-error');
			$('#wpkb-progress-title').textContent = 'Vorgang fehlgeschlagen';
			enableUi();
			return;
		}
		box.classList.add('is-done');
		if (d.type === 'backup') {
			$('#wpkb-progress-title').textContent = 'Backup erfolgreich erstellt';
			var a = document.createElement('a');
			a.className = 'button button-primary';
			a.href = WPKB.download + '&file=' + encodeURIComponent(d.result.file);
			a.textContent = 'Backup herunterladen (' + d.result.size + ')';
			$('#wpkb-result').appendChild(a);
			refreshList().then(enableUi, enableUi);
		} else {
			$('#wpkb-progress-title').textContent = 'Wiederherstellung abgeschlossen';
			if (d.result && d.result.relogin) {
				$('#wpkb-result').innerHTML = '<p><strong>Die Datenbank wurde ersetzt.</strong> Bitte melde dich mit den Zugangsdaten der gesicherten Seite neu an.</p>';
				var l = document.createElement('a');
				l.className = 'button button-primary button-hero';
				l.href = d.result.login_url;
				l.textContent = 'Zur Anmeldung';
				$('#wpkb-result').appendChild(l);
			} else {
				refreshList().then(enableUi, enableUi);
			}
		}
	}

	function fail(msg) {
		running = null;
		log(['FEHLER: ' + msg]);
		$('#wpkb-progress').classList.add('is-error');
		$('#wpkb-progress-title').textContent = 'Vorgang fehlgeschlagen';
		$('#wpkb-cancel').hidden = true;
		enableUi();
	}

	/* ---------------- Backup ---------------- */

	$('#wpkb-backup-form').addEventListener('submit', function (e) {
		e.preventDefault();
		var f = e.target;
		var data = {
			db: f.db.checked ? 1 : '',
			content: f.content.checked ? 1 : '',
			core: f.core.checked ? 1 : '',
			exclude: f.exclude.value
		};
		showProgress('Backup wird erstellt …');
		post('start_backup', data).then(runJob, function (err) { fail(err.message); });
	});

	/* ---------------- Wiederherstellen / Löschen ---------------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.wpkb-restore, .wpkb-delete');
		if (!btn) { return; }
		var row = btn.closest('tr');
		var file = row.getAttribute('data-file');

		if (btn.classList.contains('wpkb-delete')) {
			if (!window.confirm('Backup „' + file + '“ endgültig löschen?')) { return; }
			post('delete', { file: file }).then(refreshList, function (err) { window.alert(err.message); });
			return;
		}
		openRestoreDialog(row, file);
	});

	function openRestoreDialog(row, file) {
		var dlg = document.createElement('dialog');
		dlg.className = 'wpkb-dialog';
		var src = row.getAttribute('data-source');
		var opt = function (name, label, avail, checked) {
			return '<p><label><input type="checkbox" name="' + name + '" value="1"' + (avail ? (checked ? ' checked' : '') : ' disabled') + '> ' + label + (avail ? '' : ' <em>(nicht im Backup)</em>') + '</label></p>';
		};
		dlg.innerHTML =
			'<form method="dialog">' +
			'<h2>Backup wiederherstellen</h2>' +
			'<p><code></code></p>' +
			'<p>Quelle: <strong class="wpkb-src"></strong><br>Ziel: <strong>' + location.origin + '</strong></p>' +
			opt('db', 'Datenbank (ersetzt alle Inhalte, Benutzer und Einstellungen)', row.dataset.db === '1', true) +
			opt('files', 'wp-content (Themes, Plugins, Uploads)', row.dataset.content === '1', true) +
			opt('core', 'Kerndateien &amp; Hauptverzeichnis (wp-config.php und .htaccess bleiben erhalten)', row.dataset.core === '1', true) +
			'<div class="notice notice-warning inline"><p>Achtung: Vorhandene Inhalte dieser Seite werden überschrieben. Erstelle vorher ggf. ein Backup der aktuellen Seite.</p></div>' +
			'<p><label><input type="checkbox" name="confirm" required> Ich habe verstanden, dass diese Seite überschrieben wird.</label></p>' +
			'<p class="wpkb-dialog-actions"><button value="cancel" class="button" formnovalidate>Abbrechen</button> <button value="ok" class="button button-primary">Jetzt wiederherstellen</button></p>' +
			'</form>';
		$('code', dlg).textContent = file;
		$('.wpkb-src', dlg).textContent = src || 'unbekannt';
		document.body.appendChild(dlg);
		dlg.addEventListener('close', function () {
			var f = $('form', dlg);
			if (dlg.returnValue === 'ok') {
				var data = { file: file, db: f.db.checked ? 1 : '', files: f.files.checked ? 1 : '', core: f.core.checked ? 1 : '' };
				if (!data.db && !data.files && !data.core) { window.alert('Bitte mindestens einen Bestandteil auswählen.'); }
				else {
					showProgress('Backup wird wiederhergestellt …');
					post('start_restore', data).then(runJob, function (err) { fail(err.message); });
				}
			}
			dlg.remove();
		});
		dlg.showModal();
	}

	/* ---------------- Abbrechen ---------------- */

	$('#wpkb-cancel').addEventListener('click', function () {
		if (!running || !window.confirm('Vorgang wirklich abbrechen?')) { return; }
		var id = running;
		running = null;
		post('cancel', { id: id }).then(function () {
			log(['Vorgang abgebrochen.']);
			$('#wpkb-progress-title').textContent = 'Abgebrochen';
			$('#wpkb-cancel').hidden = true;
			enableUi();
		}, function (err) { fail(err.message); });
	});

	/* ---------------- Upload (gestückelt) ---------------- */

	$('#wpkb-upload-btn').addEventListener('click', function () {
		var input = $('#wpkb-upload-file');
		var file = input.files[0];
		if (!file) { window.alert('Bitte zuerst eine .tar-Datei auswählen.'); return; }
		if (!/\.tar$/i.test(file.name)) { window.alert('Nur .tar-Backups dieses Plugins können hochgeladen werden.'); return; }

		var bar = $('#wpkb-upload-bar');
		var fill = $('span', bar);
		var btn = this;
		var uid = Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
		uid = uid.replace(/[^a-z0-9]/g, '').slice(0, 32);
		var size = WPKB.chunkSize;
		var offset = 0;
		var failures = 0;
		bar.hidden = false;
		btn.disabled = true;
		running = 'upload';

		function send() {
			var blob = file.slice(offset, offset + size);
			return post('upload', { upload_id: uid, name: file.name, offset: offset, total: file.size }, blob)
				.then(function (d) {
					failures = 0;
					if (d.done) {
						fill.style.width = '100%';
						return d;
					}
					offset = d.received;
					fill.style.width = (offset / file.size * 100) + '%';
					btn.textContent = 'Hochladen … ' + Math.floor(offset / file.size * 100) + ' %';
					return send();
				}, function (err) {
					if (err.data && typeof err.data.expect === 'number') {
						offset = err.data.expect;
						return send();
					}
					if (err.retry && failures < 6) {
						failures++;
						return wait(2000 * failures).then(send);
					}
					throw err;
				});
		}
		send().then(function () {
			running = null;
			btn.textContent = 'Hochladen';
			btn.disabled = false;
			input.value = '';
			setTimeout(function () { bar.hidden = true; fill.style.width = '0'; }, 1500);
			return refreshList();
		}).catch(function (err) {
			running = null;
			btn.textContent = 'Hochladen';
			btn.disabled = false;
			bar.hidden = true;
			window.alert('Upload fehlgeschlagen: ' + err.message);
		});
	});

	/* ---------------- Sonstiges ---------------- */

	window.addEventListener('beforeunload', function (e) {
		if (running) { e.preventDefault(); e.returnValue = ''; }
	});

	if (window.WPKB_RUNNING) {
		showProgress((window.WPKB_RUNNING.type === 'backup' ? 'Backup' : 'Wiederherstellung') + ' wird fortgesetzt …');
		runJob({ id: window.WPKB_RUNNING.id, log: [] });
	}
})();
