<?php
/**
 * Einstellungsseite unter "Einstellungen → WX Webcam Archive".
 */

defined( 'ABSPATH' ) || exit;

final class WCA_Admin {

	const SLUG = 'webcam-archiv';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( 'WCA_Plugin', 'ensure_scheduled' ) );
		add_action( 'admin_post_wca_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WCA_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function admin_assets( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wca-frontend', WCA_URL . 'assets/css/frontend.css', array(), WCA_VERSION );
	}

	/**
	 * Fertige Farbschemas.
	 */
	public static function color_presets() {
		return array(
			'LSV-Blau'  => WCA_Plugin::color_defaults(),
			'Hell'      => array(
				'page'        => '#eef2f6',
				'card'        => '#ffffff',
				'inner'       => '#e3e9f0',
				'line'        => '#4f7cac',
				'accent'      => '#2c4767',
				'accent_text' => '#ffffff',
				'header'      => '#1f3854',
				'title'       => '#2c4767',
				'text'        => '#1d2733',
				'muted'       => '#5f6f82',
			),
			'Anthrazit' => array(
				'page'        => '#1b1d21',
				'card'        => '#26292e',
				'inner'       => '#33373d',
				'line'        => '#6fa8dc',
				'accent'      => '#6fa8dc',
				'accent_text' => '#0f1720',
				'header'      => '#ffffff',
				'title'       => '#e4e7eb',
				'text'        => '#f5f6f7',
				'muted'       => '#a3abb5',
			),
			'Grün'      => array(
				'page'        => '#4f7a5a',
				'card'        => '#2f4a36',
				'inner'       => '#3b5c44',
				'line'        => '#a8d5b0',
				'accent'      => '#a8d5b0',
				'accent_text' => '#132418',
				'header'      => '#ffffff',
				'title'       => '#dcefe0',
				'text'        => '#f3faf4',
				'muted'       => '#bcd3c1',
			),
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">Einstellungen</a>' );
		return $links;
	}

	public static function menu() {
		add_options_page( 'WX Webcam Archive', 'WX Webcam Archive', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting(
			'wca_settings',
			WCA_Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Speichern
	 * ------------------------------------------------------------------ */

	private static function clamp( $v, $min, $max, $def ) {
		$v = is_numeric( $v ) ? (int) $v : $def;
		return max( $min, min( $max, $v ) );
	}

	/**
	 * Koordinate (auch mit Komma, z. B. "47,42") in einen gültigen Wert umwandeln.
	 */
	private static function coord( $v, $max ) {
		$v = str_replace( ',', '.', trim( (string) $v ) );
		if ( '' === $v || ! is_numeric( $v ) ) {
			return '';
		}
		$v = (float) $v;
		return ( abs( $v ) <= $max ) ? (string) round( $v, 5 ) : '';
	}

	private static function time_val( $v, $def ) {
		return ( is_string( $v ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ) ? $v : $def;
	}

	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$old = WCA_Plugin::options();
		$o   = array();

		// Kameras.
		$taken = array_fill_keys( array_merge( array_keys( $old['cameras'] ), WCA_Archiver::camera_ids_in_db() ), true );
		$used  = array();
		$cams  = array();
		$n     = 0;

		foreach ( (array) ( $in['cameras'] ?? array() ) as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$id = sanitize_key( $c['id'] ?? '' );

			if ( ! empty( $c['remove'] ) ) {
				if ( '' !== $id && ! empty( $c['remove_images'] ) ) {
					$del = WCA_Archiver::delete_camera_images( $id );
					WCA_Plugin::log( 'info', sprintf( 'Kamera „%s“ entfernt, %d Bilder gelöscht.', sanitize_text_field( $c['name'] ?? $id ), $del ) );
				}
				continue;
			}

			++$n;
			if ( '' === $id || isset( $used[ $id ] ) ) {
				$i = 1;
				while ( isset( $taken[ 'cam' . $i ] ) || isset( $used[ 'cam' . $i ] ) ) {
					++$i;
				}
				$id = 'cam' . $i;
			}
			$used[ $id ] = true;

			$name = trim( sanitize_text_field( $c['name'] ?? '' ) );
			$mode = $c['source_mode'] ?? 'url';

			$cams[] = array(
				'id'                  => $id,
				'name'                => '' !== $name ? $name : 'Webcam ' . $n,
				'source_mode'         => in_array( $mode, array( 'url', 'folder', 'both' ), true ) ? $mode : 'url',
				'source_url'          => esc_url_raw( trim( (string) ( $c['source_url'] ?? '' ) ) ),
				'http_user'           => sanitize_text_field( $c['http_user'] ?? '' ),
				'http_pass'           => (string) ( $c['http_pass'] ?? '' ),
				'timeout'             => self::clamp( $c['timeout'] ?? 20, 5, 120, 20 ),
				'interval'            => self::clamp( $c['interval'] ?? 15, 1, 1440, 15 ),
				'time_from'           => self::time_val( $c['time_from'] ?? '', '00:00' ),
				'time_to'             => self::time_val( $c['time_to'] ?? '', '23:59' ),
				'skip_duplicates'     => empty( $c['skip_duplicates'] ) ? 0 : 1,
				'import_dir'          => untrailingslashit( wp_normalize_path( sanitize_text_field( $c['import_dir'] ?? '' ) ) ),
				'delete_after_import' => empty( $c['delete_after_import'] ) ? 0 : 1,
				'max_width'           => self::clamp( $c['max_width'] ?? 0, 0, 10000, 0 ),
				'night_sun'           => empty( $c['night_sun'] ) ? 0 : 1,
				'lat'                 => self::coord( $c['lat'] ?? '', 90 ),
				'lon'                 => self::coord( $c['lon'] ?? '', 180 ),
				'sun_offset'          => self::clamp( $c['sun_offset'] ?? 30, -180, 180, 30 ),
				'night_brightness'    => empty( $c['night_brightness'] ) ? 0 : 1,
				'night_threshold'     => self::clamp( $c['night_threshold'] ?? 12, 1, 80, 12 ),
				'night_gray'          => empty( $c['night_gray'] ) ? 0 : 1,
			);

			$last = end( $cams );
			if ( $last['night_sun'] && ( '' === $last['lat'] || '' === $last['lon'] ) ) {
				add_settings_error( WCA_Plugin::OPTION, 'wca_coords_' . $id, sprintf( '„%s“: Für den Nachtfilter nach Sonnenstand bitte Breiten- und Längengrad eintragen.', $last['name'] ), 'warning' );
			}
		}
		if ( ! $cams ) {
			add_settings_error( WCA_Plugin::OPTION, 'wca_nocam', 'Mindestens eine Kamera muss bestehen bleiben.' );
			$cams = array_values( $old['cameras'] );
		}
		$o['cameras'] = $cams;

		// Allgemein.
		$o['jpeg_quality']   = self::clamp( $in['jpeg_quality'] ?? 85, 30, 100, 85 );
		$o['thumb_width']    = self::clamp( $in['thumb_width'] ?? 320, 80, 1000, 320 );
		$o['retention_days'] = self::clamp( $in['retention_days'] ?? 0, 0, 36500, 0 );
		$o['require_login']  = empty( $in['require_login'] ) ? 0 : 1;
		$o['week_start']     = empty( $in['week_start'] ) ? 0 : 1;
		$o['colors'] = array();
		foreach ( WCA_Plugin::color_fields() as $key => $f ) {
			$c                   = sanitize_hex_color( $in['colors'][ $key ] ?? '' );
			$o['colors'][ $key ] = $c ? $c : $f[2];
		}

		$o['trigger_key']    = ( ! empty( $in['regen_key'] ) || empty( $old['trigger_key'] ) ) ? wp_generate_password( 32, false ) : $old['trigger_key'];

		return $o;
	}

	/* ---------------------------------------------------------------------
	 * Manuelle Aktionen
	 * ------------------------------------------------------------------ */

	public static function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'wca_action' );

		$do  = sanitize_key( $_POST['do'] ?? '' );
		$cam = WCA_Plugin::camera( sanitize_key( $_POST['cam'] ?? '' ) );
		$msg = '';

		switch ( $do ) {
			case 'capture':
				if ( $cam ) {
					$r   = WCA_Archiver::capture_from_url( $cam, true );
					$msg = $cam['name'] . ': ' . ( is_wp_error( $r ) ? 'Fehler: ' . $r->get_error_message() : 'Abruf ' . $r );
				}
				break;
			case 'import':
				if ( $cam ) {
					$r   = WCA_Archiver::import_folder( $cam );
					$msg = $cam['name'] . ': ' . ( is_wp_error( $r ) ? 'Fehler: ' . $r->get_error_message() : 'Import ' . $r );
				}
				break;
			case 'cleanup':
				$msg = sprintf( 'Bereinigung: %d Bilder gelöscht.', WCA_Archiver::cleanup() );
				break;
			case 'delete_day':
				$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
				if ( $cam && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
					$msg = sprintf( '%s: %d Bilder vom %s gelöscht.', $cam['name'], WCA_Archiver::delete_day( $date, $cam['id'] ), $date );
				} else {
					$msg = 'Fehler: Ungültige Kamera oder ungültiges Datum.';
				}
				break;
			case 'clear_log':
				delete_option( 'wca_log' );
				$msg = 'Protokoll geleert.';
				break;
		}

		set_transient( 'wca_notice_' . get_current_user_id(), $msg, 60 );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG ) );
		exit;
	}

	private static function action_button( $do, $label, $cam_id = '', $class = 'button', $extra = '' ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 6px 6px 0">
			<?php wp_nonce_field( 'wca_action' ); ?>
			<input type="hidden" name="action" value="wca_action">
			<input type="hidden" name="do" value="<?php echo esc_attr( $do ); ?>">
			<?php if ( $cam_id ) : ?>
				<input type="hidden" name="cam" value="<?php echo esc_attr( $cam_id ); ?>">
			<?php endif; ?>
			<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Kamera-Block im Formular
	 * ------------------------------------------------------------------ */

	private static function camera_fields( $idx, array $c ) {
		$f    = WCA_Plugin::OPTION . '[cameras][' . $idx . ']';
		$uid  = 'wca-' . $idx;
		$isnew = '' === $c['id'];
		?>
		<div class="wca-camera postbox" style="padding:0 16px 8px;margin:16px 0">
			<input type="hidden" name="<?php echo esc_attr( $f ); ?>[id]" value="<?php echo esc_attr( $c['id'] ); ?>">
			<h3 style="display:flex;gap:10px;align-items:center;margin:14px 0 4px">
				<span class="dashicons dashicons-video-alt2"></span>
				<input type="text" name="<?php echo esc_attr( $f ); ?>[name]" value="<?php echo esc_attr( $c['name'] ); ?>" placeholder="Name der Kamera" style="font-size:16px;font-weight:600;min-width:240px">
				<?php if ( ! $isnew ) : ?>
					<code title="Kürzel für Shortcodes">kamera="<?php echo esc_html( $c['id'] ); ?>"</code>
				<?php else : ?>
					<em style="font-weight:normal;color:#646970">neu – Kürzel wird beim Speichern vergeben</em>
				<?php endif; ?>
			</h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Quelle</th>
					<td>
						<?php
						foreach ( array(
							'url'    => 'Bild-URL der Kamera abrufen',
							'folder' => 'Bilder aus einem Ordner importieren (FTP-Upload)',
							'both'   => 'Beides',
						) as $k => $label ) :
							?>
							<label style="margin-right:14px"><input type="radio" name="<?php echo esc_attr( $f ); ?>[source_mode]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $c['source_mode'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $uid ); ?>-url">Bild-URL</label></th>
					<td>
						<input type="url" id="<?php echo esc_attr( $uid ); ?>-url" class="large-text code" name="<?php echo esc_attr( $f ); ?>[source_url]" value="<?php echo esc_attr( $c['source_url'] ); ?>" placeholder="https://kamera.example.de/snapshot.jpg">
						<p style="margin-top:6px">
							<input type="text" autocomplete="off" name="<?php echo esc_attr( $f ); ?>[http_user]" value="<?php echo esc_attr( $c['http_user'] ); ?>" placeholder="Benutzer (optional)">
							<input type="password" autocomplete="new-password" name="<?php echo esc_attr( $f ); ?>[http_pass]" value="<?php echo esc_attr( $c['http_pass'] ); ?>" placeholder="Passwort (optional)">
							Timeout <input type="number" min="5" max="120" class="small-text" name="<?php echo esc_attr( $f ); ?>[timeout]" value="<?php echo esc_attr( $c['timeout'] ); ?>"> s
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $uid ); ?>-dir">Import-Ordner</label></th>
					<td>
						<input type="text" id="<?php echo esc_attr( $uid ); ?>-dir" class="large-text code" name="<?php echo esc_attr( $f ); ?>[import_dir]" value="<?php echo esc_attr( $c['import_dir'] ); ?>" placeholder="<?php echo esc_attr( wp_normalize_path( dirname( ABSPATH ) ) . '/webcam-ftp/' . ( $c['id'] ?: 'kamera2' ) ); ?>">
						<p class="description">Absoluter Serverpfad – pro Kamera ein eigener Ordner. Unterordner werden mit durchsucht.</p>
						<label><input type="checkbox" name="<?php echo esc_attr( $f ); ?>[delete_after_import]" value="1" <?php checked( $c['delete_after_import'] ); ?>> Dateien nach dem Import löschen</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Zeitplan</th>
					<td>
						alle <input type="number" min="1" max="1440" class="small-text" name="<?php echo esc_attr( $f ); ?>[interval]" value="<?php echo esc_attr( $c['interval'] ); ?>"> Minuten,
						von <input type="time" name="<?php echo esc_attr( $f ); ?>[time_from]" value="<?php echo esc_attr( $c['time_from'] ); ?>">
						bis <input type="time" name="<?php echo esc_attr( $f ); ?>[time_to]" value="<?php echo esc_attr( $c['time_to'] ); ?>"> Uhr
						<p style="margin-top:6px"><label><input type="checkbox" name="<?php echo esc_attr( $f ); ?>[skip_duplicates]" value="1" <?php checked( $c['skip_duplicates'] ); ?>> Unveränderte Bilder nicht erneut speichern</label></p>
					</td>
				</tr>
				<tr>
					<th scope="row">Max. Breite</th>
					<td><input type="number" min="0" class="small-text" name="<?php echo esc_attr( $f ); ?>[max_width]" value="<?php echo esc_attr( $c['max_width'] ); ?>"> px <span class="description">(0 = Original unverändert speichern)</span></td>
				</tr>
				<tr>
					<th scope="row">Nachtbilder</th>
					<td>
						<fieldset>
							<p><label><input type="checkbox" name="<?php echo esc_attr( $f ); ?>[night_sun]" value="1" <?php checked( $c['night_sun'] ); ?>> <strong>Nach Sonnenstand:</strong> zwischen Sonnenuntergang und Sonnenaufgang nicht aufnehmen</label></p>
							<p style="margin-left:24px">
								Standort: Breite <input type="text" inputmode="decimal" class="small-text" style="width:90px" name="<?php echo esc_attr( $f ); ?>[lat]" value="<?php echo esc_attr( $c['lat'] ); ?>" placeholder="47.4210">
								Länge <input type="text" inputmode="decimal" class="small-text" style="width:90px" name="<?php echo esc_attr( $f ); ?>[lon]" value="<?php echo esc_attr( $c['lon'] ); ?>" placeholder="10.9850">
								&nbsp; Puffer <input type="number" min="-180" max="180" class="small-text" name="<?php echo esc_attr( $f ); ?>[sun_offset]" value="<?php echo esc_attr( $c['sun_offset'] ); ?>"> Min.
								<br><span class="description">Koordinaten z. B. per Rechtsklick in Google Maps kopieren. Der Puffer nimmt Dämmerung vor Sonnenaufgang und nach Sonnenuntergang noch mit (negativ = später beginnen / früher aufhören).</span>
							</p>
							<p><label><input type="checkbox" name="<?php echo esc_attr( $f ); ?>[night_brightness]" value="1" <?php checked( $c['night_brightness'] ); ?>> <strong>Nach Helligkeit:</strong> Bilder verwerfen, die dunkler sind als</label>
								<input type="number" min="1" max="80" class="small-text" name="<?php echo esc_attr( $f ); ?>[night_threshold]" value="<?php echo esc_attr( $c['night_threshold'] ); ?>"> %
							</p>
							<p><label><input type="checkbox" name="<?php echo esc_attr( $f ); ?>[night_gray]" value="1" <?php checked( $c['night_gray'] ); ?>> Schwarzweiß-Bilder (Infrarot-Nachtmodus der Kamera) ebenfalls verwerfen</label>
								<br><span class="description">Vorsicht bei Nebel oder Schnee: sehr farblose Tagbilder könnten sonst ebenfalls aussortiert werden. Ideal in Kombination mit „Nach Sonnenstand“.</span>
							</p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">Entfernen</th>
					<td>
						<label><input type="checkbox" class="wca-remove" name="<?php echo esc_attr( $f ); ?>[remove]" value="1"> Diese Kamera beim Speichern entfernen</label>
						<?php if ( ! $isnew ) : ?>
							<label style="margin-left:14px"><input type="checkbox" name="<?php echo esc_attr( $f ); ?>[remove_images]" value="1"> und alle ihre Bilder endgültig löschen</label>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Seite
	 * ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o      = WCA_Plugin::options();
		$name   = WCA_Plugin::OPTION;
		$next   = wp_next_scheduled( WCA_Plugin::CRON_CAPTURE );
		$log    = get_option( 'wca_log', array() );
		$notice = get_transient( 'wca_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'wca_notice_' . get_current_user_id() );
		}
		$trigger = add_query_arg( 'key', $o['trigger_key'], rest_url( WCA_REST::NS . '/trigger' ) );
		$editor  = wp_image_editor_supports( array( 'mime_type' => 'image/jpeg' ) );
		?>
		<div class="wrap">
			<h1>WX Webcam Archive</h1>
			<?php settings_errors( WCA_Plugin::OPTION ); ?>

			<?php if ( $notice ) : ?>
				<div class="notice <?php echo false !== strpos( $notice, 'Fehler' ) ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $editor ) : ?>
				<div class="notice notice-warning"><p>Auf dem Server ist weder GD noch Imagick verfügbar – Vorschaubilder können nicht erzeugt werden.</p></div>
			<?php endif; ?>
			<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
				<div class="notice notice-info"><p><code>DISABLE_WP_CRON</code> ist aktiv. Richte einen Server-Cronjob für die Trigger-URL unten ein.</p></div>
			<?php endif; ?>

			<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
			<div style="flex:1 1 640px;min-width:320px">
			<form method="post" action="options.php" id="wca-form">
				<?php settings_fields( 'wca_settings' ); ?>

				<h2 class="title">Kameras</h2>
				<div id="wca-cameras">
					<?php
					$i = 0;
					foreach ( $o['cameras'] as $cam ) {
						self::camera_fields( $i++, $cam );
					}
					?>
				</div>
				<p><button type="button" class="button" id="wca-add"><span class="dashicons dashicons-plus-alt2" style="vertical-align:text-bottom"></span> Weitere Kamera hinzufügen</button></p>

				<template id="wca-camera-template">
					<?php self::camera_fields( '__IDX__', array_merge( WCA_Plugin::camera_defaults(), array( 'name' => '' ) ) ); ?>
				</template>

				<h2 class="title">Allgemein (alle Kameras)</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wca-tw">Vorschaubild-Breite</label></th>
						<td><input type="number" id="wca-tw" min="80" max="1000" class="small-text" name="<?php echo esc_attr( $name ); ?>[thumb_width]" value="<?php echo esc_attr( $o['thumb_width'] ); ?>"> px</td>
					</tr>
					<tr>
						<th scope="row"><label for="wca-q">JPEG-Qualität</label></th>
						<td><input type="number" id="wca-q" min="30" max="100" class="small-text" name="<?php echo esc_attr( $name ); ?>[jpeg_quality]" value="<?php echo esc_attr( $o['jpeg_quality'] ); ?>"> <span class="description">(beim Verkleinern)</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="wca-ret">Aufbewahrung</label></th>
						<td><input type="number" id="wca-ret" min="0" class="small-text" name="<?php echo esc_attr( $name ); ?>[retention_days]" value="<?php echo esc_attr( $o['retention_days'] ); ?>"> Tage <span class="description">(0 = unbegrenzt)</span></td>
					</tr>
					<tr>
						<th scope="row">Wochenbeginn</th>
						<td>
							<select name="<?php echo esc_attr( $name ); ?>[week_start]">
								<option value="1" <?php selected( $o['week_start'], 1 ); ?>>Montag</option>
								<option value="0" <?php selected( $o['week_start'], 0 ); ?>>Sonntag</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Zugriff</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[require_login]" value="1" <?php checked( $o['require_login'] ); ?>> Archiv nur für angemeldete Benutzer anzeigen</label></td>
					</tr>
					<tr>
						<th scope="row">Trigger-URL</th>
						<td>
							<input type="text" readonly class="large-text code" value="<?php echo esc_attr( $trigger ); ?>" onclick="this.select()">
							<p class="description">Für zuverlässige Aufnahmen diese URL jede Minute per Server-Cronjob aufrufen – jede Kamera wird dann in ihrem eigenen Intervall abgerufen:<br><code>* * * * * curl -s "<?php echo esc_html( $trigger ); ?>" &gt;/dev/null</code></p>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[regen_key]" value="1"> Neuen Schlüssel erzeugen</label>
						</td>
					</tr>
				</table>

				<h2 class="title">Farben</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Farbschema</th>
						<td>
							<?php foreach ( self::color_presets() as $label => $set ) : ?>
								<button type="button" class="button wca-preset" data-colors="<?php echo esc_attr( wp_json_encode( $set ) ); ?>" style="margin:0 6px 6px 0">
									<span style="display:inline-block;width:14px;height:14px;border-radius:3px;vertical-align:-2px;margin-right:4px;background:<?php echo esc_attr( $set['card'] ); ?>;box-shadow:inset 0 -5px 0 <?php echo esc_attr( $set['line'] ); ?>,0 0 0 3px <?php echo esc_attr( $set['page'] ); ?>"></span>
									<?php echo esc_html( $label ); ?>
								</button>
							<?php endforeach; ?>
							<p class="description">Übernimmt ein fertiges Schema in die Felder unten – danach noch „Einstellungen speichern“.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Einzelne Farben</th>
						<td>
							<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px 20px">
							<?php foreach ( WCA_Plugin::color_fields() as $key => $f ) : ?>
								<div>
									<div style="font-weight:600;margin-bottom:4px"><?php echo esc_html( $f[1] ); ?></div>
									<input type="text" class="wca-color" data-key="<?php echo esc_attr( $key ); ?>" data-var="<?php echo esc_attr( $f[0] ); ?>" data-default-color="<?php echo esc_attr( $f[2] ); ?>" name="<?php echo esc_attr( $name ); ?>[colors][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $o['colors'][ $key ] ); ?>">
								</div>
							<?php endforeach; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Vorschau</th>
						<td>
							<div id="wca-preview" class="wca-archive wca-preview" style="grid-template-columns:minmax(0,1.6fr) minmax(220px,1fr);max-width:760px;pointer-events:none">
								<header class="wca-header" style="grid-area:header">
									<div class="wca-header-main">
										<h2 class="wca-header-title">Webcam-Archiv</h2>
										<div class="wca-tabs"><button type="button" class="wca-btn wca-tab" aria-selected="true">Kamera 1</button><button type="button" class="wca-btn wca-tab" aria-selected="false">Kamera 2</button></div>
									</div>
									<div class="wca-header-stamp"><div class="wca-stamp">Stand 15:45 Uhr</div><div class="wca-stamp-ago">vor 5 min</div></div>
								</header>
								<section class="wca-card wca-viewer">
									<div class="wca-card-head"><h3 class="wca-card-title">Montag, 16. November</h3><div class="wca-daynav"><button type="button" class="wca-btn">‹</button><button type="button" class="wca-btn">›</button></div></div>
									<div class="wca-stage" style="min-height:120px;aspect-ratio:16/7"><div class="wca-badge">12:30</div></div>
									<div class="wca-bar"><button type="button" class="wca-btn wca-play">▶</button><input type="range" class="wca-slider" value="70"><strong class="wca-time">12:30 Uhr</strong></div>
								</section>
								<section class="wca-card wca-cal">
									<div class="wca-cal-head"><button type="button" class="wca-btn">‹</button><h3 class="wca-card-title wca-cal-title">November</h3><button type="button" class="wca-btn">›</button></div>
									<div class="wca-cal-grid">
										<?php
										foreach ( array( 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So' ) as $wd ) {
											echo '<div class="wca-wd">' . esc_html( $wd ) . '</div>';
										}
										for ( $d = 10; $d <= 23; $d++ ) {
											$cls = 16 === $d ? 'wca-day wca-has wca-selected' : ( $d < 16 && 12 !== $d ? 'wca-day wca-has' : 'wca-day' );
											echo '<div class="' . esc_attr( $cls ) . '">' . (int) $d . '</div>';
										}
										?>
									</div>
								</section>
								<div class="wca-tiles">
									<div class="wca-tile"><div class="wca-tile-label">Aufnahmen</div><div class="wca-tile-value">52</div><div class="wca-tile-sub">Bilder an diesem Tag</div></div>
									<div class="wca-tile"><div class="wca-tile-label">Zeitraum</div><div class="wca-tile-value">07:00 – 19:45</div><div class="wca-tile-sub">Erste – letzte Aufnahme</div></div>
									<div class="wca-tile"><div class="wca-tile-label">Angezeigtes Bild</div><div class="wca-tile-value">12:30</div><div class="wca-tile-sub">23 / 52 · <span class="wca-link">Originalgröße</span></div></div>
								</div>
							</div>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Einstellungen speichern' ); ?>
			</form>
			</div>

			<div style="flex:0 1 360px;min-width:300px">
				<?php
				foreach ( $o['cameras'] as $id => $cam ) :
					$stats  = WCA_Archiver::stats( $id );
					$latest = WCA_Archiver::latest( $id );
					$state  = WCA_Plugin::state( $id );
					?>
					<div class="card" style="max-width:none;margin-top:20px">
						<h2 class="title" style="margin-bottom:8px"><?php echo esc_html( $cam['name'] ); ?> <code style="font-size:12px;font-weight:normal"><?php echo esc_html( $id ); ?></code></h2>
						<?php if ( $latest ) : ?>
							<p><img src="<?php echo esc_url( $latest['thumb'] ); ?>" alt="" style="max-width:100%;height:auto;border-radius:4px"></p>
						<?php endif; ?>
						<table class="widefat striped" style="border:0">
							<tr><td>Bilder</td><td><strong><?php echo esc_html( number_format_i18n( $stats ? (int) $stats->cnt : 0 ) ); ?></strong> (<?php echo esc_html( size_format( $stats ? (int) $stats->bytes : 0, 1 ) ?: '0 B' ); ?>)</td></tr>
							<tr><td>Zeitraum</td><td><?php echo esc_html( $stats && $stats->first ? mysql2date( 'd.m.Y', $stats->first ) . ' – ' . mysql2date( 'd.m.Y H:i', $stats->last ) : '–' ); ?></td></tr>
							<tr><td>Letzter Abruf</td><td><?php echo esc_html( ! empty( $state['last_run'] ) ? mysql2date( 'd.m.Y H:i', $state['last_run'] ) : '–' ); ?></td></tr>
							<tr><td>Intervall</td><td>alle <?php echo (int) $cam['interval']; ?> Min., <?php echo esc_html( $cam['time_from'] . '–' . $cam['time_to'] ); ?> Uhr</td></tr>
							<?php
							$sun = $cam['night_sun'] ? WCA_Archiver::sun_times( $cam ) : null;
							if ( $sun ) :
								?>
								<tr><td>Heute aktiv</td><td><?php echo esc_html( $sun['from'] . '–' . $sun['to'] ); ?> Uhr (Sonnenstand)</td></tr>
							<?php endif; ?>
							<?php if ( ! empty( $state['measure'] ) && ( $cam['night_brightness'] || $cam['night_gray'] ) ) : ?>
								<tr><td>Letzte Messung</td><td>Helligkeit <?php echo esc_html( number_format_i18n( $state['measure']['brightness'], 1 ) ); ?> %, Farbe <?php echo esc_html( number_format_i18n( $state['measure']['color'], 1 ) ); ?> %<br><small><?php echo esc_html( mysql2date( 'd.m.Y H:i', $state['measure']['time'] ) ); ?></small></td></tr>
							<?php endif; ?>
							<?php if ( ! empty( $state['night'] ) ) : ?>
								<tr><td>Status</td><td>🌙 Nachtpause</td></tr>
							<?php endif; ?>
						</table>
						<p style="margin-top:12px">
							<?php
							if ( in_array( $cam['source_mode'], array( 'url', 'both' ), true ) ) {
								self::action_button( 'capture', 'Jetzt Bild abrufen', $id, 'button button-primary' );
							}
							if ( in_array( $cam['source_mode'], array( 'folder', 'both' ), true ) ) {
								self::action_button( 'import', 'Ordner importieren', $id );
							}
							?>
						</p>
					</div>
				<?php endforeach; ?>

				<div class="card" style="max-width:none">
					<h2 class="title">Wartung</h2>
					<p>Nächster Cron-Lauf: <?php echo esc_html( $next ? wp_date( 'd.m.Y H:i', $next ) : 'nicht geplant' ); ?></p>
					<?php self::action_button( 'cleanup', 'Alte Bilder bereinigen' ); ?>
					<h3>Tag löschen</h3>
					<?php
					$opts = '';
					foreach ( $o['cameras'] as $id => $cam ) {
						$opts .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $cam['name'] ) . '</option>';
					}
					// Kamera-Auswahl ersetzt hier das versteckte cam-Feld.
					self::action_button( 'delete_day', 'Löschen', '', 'button button-link-delete', '<select name="cam">' . $opts . '</select> <input type="date" name="date" required> ' );
					?>
				</div>

				<div class="card" style="max-width:none">
					<h2 class="title">Shortcodes</h2>
					<p><code>[webcam_archiv]</code><br>Kalender mit Umschalter zwischen allen Kameras.</p>
					<p><code>[webcam_archiv kamera="cam2"]</code><br>Nur eine Kamera. Mehrere: <code>kamera="cam1,cam3"</code>. Weitere Optionen: <code>datum="2026-11-09"</code>, <code>tempo="6"</code></p>
					<p><code>[webcam_aktuell kamera="cam2" refresh="60" archiv="/webcam-archiv/"]</code><br>Neuestes Bild einer Kamera, aktualisiert sich automatisch.</p>
				</div>
			</div>
			</div>

			<h2>Protokoll</h2>
			<?php if ( $log ) : ?>
				<table class="widefat striped" style="max-width:1000px">
					<thead><tr><th style="width:150px">Zeit</th><th style="width:60px">Status</th><th>Meldung</th></tr></thead>
					<tbody>
					<?php
					$labels = array(
						'ok'    => '✅',
						'info'  => 'ℹ️',
						'error' => '❌',
					);
					foreach ( $log as $e ) :
						?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'd.m.Y H:i:s', $e['time'] ) ); ?></td>
							<td><?php echo esc_html( $labels[ $e['level'] ] ?? $e['level'] ); ?></td>
							<td><?php echo esc_html( $e['msg'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><?php self::action_button( 'clear_log', 'Protokoll leeren', '', 'button button-small' ); ?></p>
			<?php else : ?>
				<p>Noch keine Einträge.</p>
			<?php endif; ?>
		</div>

		<script>
		jQuery(function ($) {
			var preview = document.getElementById('wca-preview');
			function apply(input, val) {
				if (preview) { preview.style.setProperty(input.getAttribute('data-var'), val); }
			}
			$('.wca-color').each(function () {
				var input = this;
				apply(input, input.value);
				$(input).wpColorPicker({
					change: function (e, ui) { apply(input, ui.color.toString()); },
					clear: function () { apply(input, input.getAttribute('data-default-color')); }
				});
			});
			$('.wca-preset').on('click', function () {
				var set = JSON.parse(this.getAttribute('data-colors'));
				$('.wca-color').each(function () {
					var v = set[this.getAttribute('data-key')];
					if (v) { $(this).wpColorPicker('color', v); apply(this, v); }
				});
			});
		});
		</script>
		<script>
		(function () {
			var list = document.getElementById('wca-cameras');
			var tpl = document.getElementById('wca-camera-template');
			document.getElementById('wca-add').addEventListener('click', function () {
				var idx = 'n' + Date.now();
				var wrap = document.createElement('div');
				wrap.innerHTML = tpl.innerHTML.split('__IDX__').join(idx);
				var box = wrap.firstElementChild;
				list.appendChild(box);
				box.querySelector('input[type=text]').focus();
			});
			list.addEventListener('change', function (e) {
				if (e.target.classList.contains('wca-remove')) {
					e.target.closest('.wca-camera').style.opacity = e.target.checked ? '.45' : '';
				}
			});
		})();
		</script>
		<?php
	}
}
