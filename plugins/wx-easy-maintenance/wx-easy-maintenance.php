<?php
/**
 * Plugin Name:       WX Easy Maintenance
 * Description:       Schaltet die Website in den Wartungsmodus. Besucher sehen eine Wartungsseite (HTTP 503), angemeldete Benutzer mit Berechtigung sehen die normale Seite.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Update URI:        https://github.com/cwaxe/wordpress
 * Author:            IT-Service Waxenberger
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wx-easy-maintenance
 */

defined( 'ABSPATH' ) || exit;

// Updates aus den GitHub-Releases (siehe includes/github-updater.php).
require_once __DIR__ . '/includes/github-updater.php';
WX_GitHub_Updater::register( __FILE__ );

define( 'WXEM_VERSION', '1.0.0' );

final class WXEM_Maintenance {

	const OPTION = 'wxem_settings';
	const PAGE   = 'wx-easy-maintenance';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_show_maintenance' ), 0 );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_notice' ), 100 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_post_wxem_toggle', array( __CLASS__, 'handle_toggle' ) );
		add_action( 'wp_head', array( __CLASS__, 'admin_bar_style' ) );
		add_action( 'admin_head', array( __CLASS__, 'admin_bar_style' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'action_links' ) );
	}

	/* ---------------------------------------------------------------------
	 * Einstellungen
	 * ------------------------------------------------------------------- */

	public static function defaults() {
		return array(
			'enabled'    => 0,
			'title'      => 'Wir sind gleich zurück',
			'message'    => 'Unsere Website wird gerade gewartet. Bitte schau in Kürze wieder vorbei.',
			'end_time'   => '',
			'capability' => 'edit_posts',
			'bg_color'   => '#1e293b',
			'text_color' => '#f8fafc',
		);
	}

	public static function get_settings() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function is_enabled() {
		$s = self::get_settings();
		if ( empty( $s['enabled'] ) ) {
			return false;
		}
		// Automatisch beenden, wenn das Enddatum erreicht ist.
		$end = self::end_timestamp( $s );
		if ( $end && time() >= $end ) {
			return false;
		}
		return true;
	}

	private static function end_timestamp( $s ) {
		if ( empty( $s['end_time'] ) ) {
			return 0;
		}
		try {
			$dt = new DateTime( $s['end_time'], wp_timezone() );
			return $dt->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	public static function register_settings() {
		register_setting(
			'wxem_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	public static function sanitize( $input ) {
		$d   = self::defaults();
		$out = array();

		$out['enabled']  = empty( $input['enabled'] ) ? 0 : 1;
		$out['title']    = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $d['title'];
		$out['message']  = isset( $input['message'] ) ? wp_kses_post( $input['message'] ) : $d['message'];
		$out['end_time'] = '';
		if ( ! empty( $input['end_time'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $input['end_time'] ) ) {
			$out['end_time'] = $input['end_time'];
		}

		$caps              = array_keys( self::capability_choices() );
		$out['capability'] = ( isset( $input['capability'] ) && in_array( $input['capability'], $caps, true ) ) ? $input['capability'] : $d['capability'];

		$bg                = isset( $input['bg_color'] ) ? sanitize_hex_color( $input['bg_color'] ) : '';
		$tx                = isset( $input['text_color'] ) ? sanitize_hex_color( $input['text_color'] ) : '';
		$out['bg_color']   = $bg ? $bg : $d['bg_color'];
		$out['text_color'] = $tx ? $tx : $d['text_color'];

		return $out;
	}

	private static function capability_choices() {
		return array(
			'manage_options' => 'Nur Administratoren',
			'edit_pages'     => 'Redakteure und höher',
			'edit_posts'     => 'Mitarbeiter (Contributor) und höher',
			'read'           => 'Alle angemeldeten Benutzer',
		);
	}

	/* ---------------------------------------------------------------------
	 * Frontend: Wartungsseite ausgeben
	 * ------------------------------------------------------------------- */

	public static function maybe_show_maintenance() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$s = self::get_settings();

		// Berechtigte Benutzer sehen die normale Seite.
		if ( is_user_logged_in() && current_user_can( $s['capability'] ) ) {
			return;
		}

		// Ausnahmen: CLI, Cron, AJAX, REST, Feeds bleiben unberührt; Login liegt nicht im Template-Flow.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		/**
		 * Filter: true zurückgeben, um die Wartungsseite für die aktuelle Anfrage zu überspringen.
		 */
		if ( apply_filters( 'wxem_bypass', false ) ) {
			return;
		}

		self::render( $s );
		exit;
	}

	private static function render( $s ) {
		$end = self::end_timestamp( $s );

		nocache_headers();
		status_header( 503 );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		header( 'Retry-After: ' . ( $end ? max( 60, $end - time() ) : 3600 ) );

		// Eigenes Template im Theme erlauben: wartungsmodus.php
		$theme_tpl = locate_template( 'wartungsmodus.php' );
		if ( $theme_tpl ) {
			include $theme_tpl;
			return;
		}

		$site     = get_bloginfo( 'name' );
		$end_text = $end ? wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $end ) : '';
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $s['title'] . ' – ' . $site ); ?></title>
	<style>
		*{box-sizing:border-box;margin:0;padding:0}
		html,body{height:100%}
		body{display:flex;align-items:center;justify-content:center;padding:24px;
			background:<?php echo esc_attr( $s['bg_color'] ); ?>;color:<?php echo esc_attr( $s['text_color'] ); ?>;
			font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.6;text-align:center}
		main{max-width:620px}
		.icon{width:64px;height:64px;margin:0 auto 28px;opacity:.85}
		h1{font-size:clamp(1.8rem,4vw,2.6rem);font-weight:700;margin-bottom:16px;letter-spacing:-.01em}
		.msg{font-size:1.1rem;opacity:.85}
		.msg p+p{margin-top:.8em}
		.end{margin-top:28px;font-size:.95rem;opacity:.7}
		.site{margin-top:48px;font-size:.85rem;opacity:.5;letter-spacing:.08em;text-transform:uppercase}
		a{color:inherit}
	</style>
</head>
<body>
	<main>
		<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
		<h1><?php echo esc_html( $s['title'] ); ?></h1>
		<div class="msg"><?php echo wpautop( wp_kses_post( $s['message'] ) ); ?></div>
		<?php if ( $end_text ) : ?>
			<p class="end">Voraussichtlich wieder erreichbar: <?php echo esc_html( $end_text ); ?></p>
		<?php endif; ?>
		<p class="site"><?php echo esc_html( $site ); ?></p>
	</main>
</body>
</html>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------- */

	public static function add_settings_page() {
		add_options_page( 'Wartungsmodus', 'Wartungsmodus', 'manage_options', self::PAGE, array( __CLASS__, 'settings_page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">Einstellungen</a>' );
		return $links;
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::get_settings();
		$o = self::OPTION;
		?>
		<div class="wrap">
			<h1>Wartungsmodus</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wxem_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Status</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $o ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?>>
								<strong>Wartungsmodus aktivieren</strong>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wxem-title">Überschrift</label></th>
						<td><input type="text" id="wxem-title" class="regular-text" name="<?php echo esc_attr( $o ); ?>[title]" value="<?php echo esc_attr( $s['title'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wxem-message">Nachricht</label></th>
						<td>
							<?php
							wp_editor(
								$s['message'],
								'wxem-message',
								array(
									'textarea_name' => $o . '[message]',
									'textarea_rows' => 6,
									'media_buttons' => false,
									'teeny'         => true,
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wxem-end">Automatisch beenden am</label></th>
						<td>
							<input type="datetime-local" id="wxem-end" name="<?php echo esc_attr( $o ); ?>[end_time]" value="<?php echo esc_attr( $s['end_time'] ); ?>">
							<p class="description">Optional. Zeitzone: <?php echo esc_html( wp_timezone_string() ); ?>. Danach ist die Seite automatisch wieder erreichbar.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wxem-cap">Wer sieht die normale Seite?</label></th>
						<td>
							<select id="wxem-cap" name="<?php echo esc_attr( $o ); ?>[capability]">
								<?php foreach ( self::capability_choices() as $cap => $label ) : ?>
									<option value="<?php echo esc_attr( $cap ); ?>" <?php selected( $s['capability'], $cap ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Farben</th>
						<td>
							<label>Hintergrund <input type="color" name="<?php echo esc_attr( $o ); ?>[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>"></label>
							&nbsp;&nbsp;
							<label>Text <input type="color" name="<?php echo esc_attr( $o ); ?>[text_color]" value="<?php echo esc_attr( $s['text_color'] ); ?>"></label>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Einstellungen speichern' ); ?>
			</form>
			<p class="description">Tipp: Lege eine Datei <code>wartungsmodus.php</code> in deinem Theme an, um die Wartungsseite komplett selbst zu gestalten.</p>
		</div>
		<?php
	}

	/** Schnell-Umschalter über die Admin-Leiste. */
	public static function handle_toggle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'wxem_toggle' );

		$s            = self::get_settings();
		$s['enabled'] = self::is_enabled() ? 0 : 1;
		if ( $s['enabled'] ) {
			$end = self::end_timestamp( $s );
			if ( $end && time() >= $end ) {
				$s['end_time'] = ''; // abgelaufenes Enddatum entfernen
			}
		}
		update_option( self::OPTION, $s );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	public static function admin_bar_notice( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$on  = self::is_enabled();
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=wxem_toggle' ), 'wxem_toggle' );

		$bar->add_node(
			array(
				'id'    => 'wxem-status',
				'title' => $on ? 'Wartungsmodus AN' : 'Wartungsmodus aus',
				'href'  => admin_url( 'options-general.php?page=' . self::PAGE ),
				'meta'  => array( 'class' => $on ? 'wxem-on' : 'wxem-off' ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'wxem-status',
				'id'     => 'wxem-toggle',
				'title'  => $on ? 'Jetzt deaktivieren' : 'Jetzt aktivieren',
				'href'   => $url,
			)
		);
	}

	public static function admin_bar_style() {
		if ( ! is_admin_bar_showing() || ! self::is_enabled() ) {
			return;
		}
		echo '<style>#wpadminbar #wp-admin-bar-wxem-status.wxem-on > .ab-item{background:#d63638;color:#fff}</style>';
	}

	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_enabled() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'settings_page_' . self::PAGE === $screen->id ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>Wartungsmodus ist aktiv.</strong> Besucher sehen die Wartungsseite. <a href="%s">Einstellungen</a></p></div>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) )
		);
	}
}

WXEM_Maintenance::init();
