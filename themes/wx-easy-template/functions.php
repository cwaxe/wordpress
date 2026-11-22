<?php
/**
 * WX Easy Template – Funktionen
 *
 * @package Dunkel_Schlicht
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DUNKEL_VERSION', '1.12.0' );

/**
 * Übernimmt beim ersten Aktivieren die Customizer-Einstellungen (Farben,
 * Schriften, Logo, Menüs, zusätzliches CSS) der Vorgänger-Version, die im
 * Ordner „dunkel-schlicht“ installiert war.
 */
function dunkel_import_legacy_mods() {
	if ( get_option( 'wx_easy_template_legacy_imported' ) ) {
		return;
	}
	update_option( 'wx_easy_template_legacy_imported', 1, false );
	$legacy = get_option( 'theme_mods_dunkel-schlicht' );
	if ( ! is_array( $legacy ) || ! $legacy ) {
		return;
	}
	$key     = 'theme_mods_' . get_option( 'stylesheet' );
	$current = get_option( $key );
	update_option( $key, array_merge( is_array( $current ) ? $current : array(), $legacy ) );
}
add_action( 'after_switch_theme', 'dunkel_import_legacy_mods' );

// Aktive Einzelplugins übernehmen ihre bisherigen Aufgaben bis zur Deaktivierung.
if ( ! function_exists( 'ek_register_post_type' ) ) {
	require_once __DIR__ . '/inc/calendar.php';
}
if ( ! function_exists( 'sbl_enqueue_assets' ) ) {
	require_once __DIR__ . '/inc/lightbox.php';
}

// Updates aus dem GitHub-Repository (Dashboard → Aktualisierungen).
require_once __DIR__ . '/inc/updater.php';

/**
 * Theme-Unterstützung und Menüs registrieren.
 */
function dunkel_setup() {
	load_theme_textdomain( 'wx-easy-template', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 96,
			'width'       => 320,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Hauptmenü (oben mittig)', 'wx-easy-template' ),
			'footer'  => __( 'Footer-Menü (Datenschutz & Impressum)', 'wx-easy-template' ),
		)
	);
}
add_action( 'after_setup_theme', 'dunkel_setup' );

/**
 * Inhaltsbreite für eingebettete Medien.
 */
function dunkel_content_width() {
	$GLOBALS['content_width'] = max( 480, (int) get_theme_mod( 'dunkel_content_width', 1000 ) - 48 );
}
add_action( 'after_setup_theme', 'dunkel_content_width', 0 );

/**
 * Styles und Skripte laden.
 */
function dunkel_scripts() {
	wp_enqueue_style( 'dunkel-style', get_stylesheet_uri(), array(), DUNKEL_VERSION );

	wp_enqueue_script(
		'dunkel-navigation',
		get_template_directory_uri() . '/assets/js/navigation.js',
		array(),
		DUNKEL_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_add_inline_style( 'dunkel-style', dunkel_customizer_css() );

	wp_enqueue_script(
		'dunkel-mode',
		get_template_directory_uri() . '/assets/js/mode-toggle.js',
		array(),
		DUNKEL_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script(
		'dunkel-mode',
		'dunkelMode',
		array(
			'fallback' => dunkel_default_mode(),
			'toLight'  => __( 'Zum hellen Design wechseln', 'wx-easy-template' ),
			'toDark'   => __( 'Zum dunklen Design wechseln', 'wx-easy-template' ),
		)
	);

	wp_localize_script(
		'dunkel-navigation',
		'dunkelNav',
		array(
			'expand'   => __( 'Untermenü öffnen', 'wx-easy-template' ),
			'collapse' => __( 'Untermenü schließen', 'wx-easy-template' ),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'dunkel_scripts' );

/**
 * Fallback für das Footer-Menü: zeigt Datenschutz und Impressum,
 * solange im Backend noch kein Menü zugewiesen ist.
 */
function dunkel_footer_fallback() {
	$links = array();

	$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( $privacy_id && 'publish' === get_post_status( $privacy_id ) ) {
		$links[] = '<li><a href="' . esc_url( get_permalink( $privacy_id ) ) . '">' . esc_html__( 'Datenschutz', 'wx-easy-template' ) . '</a></li>';
	}

	$imprint = get_page_by_path( 'impressum' );
	if ( $imprint && 'publish' === $imprint->post_status ) {
		$links[] = '<li><a href="' . esc_url( get_permalink( $imprint ) ) . '">' . esc_html__( 'Impressum', 'wx-easy-template' ) . '</a></li>';
	}

	if ( $links ) {
		echo '<ul class="menu">' . implode( '', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Fallback für das Hauptmenü: einfache Seitenliste.
 */
function dunkel_primary_fallback() {
	echo '<ul class="menu">';
	wp_list_pages(
		array(
			'title_li' => '',
			'depth'    => 0,
		)
	);
	echo '</ul>';
}

/**
 * Seitenlisten-Klassen an Menü-Klassen angleichen (für den Fallback).
 */
function dunkel_page_css_class( $classes ) {
	if ( in_array( 'page_item_has_children', $classes, true ) ) {
		$classes[] = 'menu-item-has-children';
	}
	if ( in_array( 'current_page_item', $classes, true ) ) {
		$classes[] = 'current-menu-item';
	}
	if ( in_array( 'current_page_ancestor', $classes, true ) ) {
		$classes[] = 'current-menu-ancestor';
	}
	return $classes;
}
add_filter( 'page_css_class', 'dunkel_page_css_class' );

/**
 * Fallback-Untermenüs bekommen die Klasse .sub-menu.
 */
function dunkel_list_pages_submenu( $output ) {
	return str_replace( "<ul class='children'>", '<ul class="sub-menu">', $output );
}
add_filter( 'wp_list_pages', 'dunkel_list_pages_submenu' );

/**
 * Kürzerer Auszug.
 */
function dunkel_excerpt_length() {
	return 40;
}
add_filter( 'excerpt_length', 'dunkel_excerpt_length' );

function dunkel_excerpt_more() {
	return ' …';
}
add_filter( 'excerpt_more', 'dunkel_excerpt_more' );

/**
 * Erstes Bild aus dem gespeicherten Inhalt des aktuellen Beitrags ermitteln.
 */
function dunkel_first_post_image() {
	if ( post_password_required() ) {
		return null;
	}

	$content   = get_post_field( 'post_content', get_the_ID(), 'raw' );
	$protocols = array( 'http', 'https' );

	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$processor = new WP_HTML_Tag_Processor( $content );
		while ( $processor->next_tag( 'IMG' ) ) {
			$src = esc_url_raw( (string) $processor->get_attribute( 'src' ), $protocols );
			if ( $src ) {
				return array( 'src' => $src, 'class' => (string) $processor->get_attribute( 'class' ) );
			}
		}
	} else {
		// WordPress 6.0 und 6.1 haben noch keinen HTML-Tag-Prozessor.
		foreach ( wp_html_split( $content ) as $part ) {
			if ( ! preg_match( '/^<img\s+(.*?)\s*\/?>$/is', $part, $matches ) ) {
				continue;
			}

			$attributes = wp_kses_hair( $matches[1], $protocols );
			$src        = esc_url_raw( html_entity_decode( $attributes['src']['value'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $protocols );
			if ( $src ) {
				return array( 'src' => $src, 'class' => $attributes['class']['value'] ?? '' );
			}
		}
	}

	return null;
}

/**
 * Vorschaubild für Beitragslisten. Ein gesetztes Beitragsbild hat Vorrang.
 */
function dunkel_listing_thumbnail() {
	$thumbnail = get_the_post_thumbnail( null, 'large' );
	if ( $thumbnail || 'post' !== get_post_type() ) {
		return $thumbnail;
	}

	$image = dunkel_first_post_image();
	if ( ! $image ) {
		return '';
	}

	if ( preg_match( '/(?:^|\s)wp-image-(\d+)(?=\s|$)/', $image['class'], $matches ) ) {
		$thumbnail = wp_get_attachment_image( (int) $matches[1], 'large', false, array( 'alt' => '' ) );
		if ( $thumbnail ) {
			return $thumbnail;
		}
	}

	return sprintf( '<img src="%s" alt="" loading="lazy" decoding="async">', esc_url( $image['src'] ) );
}

/* =========================================================
 * Hell/Dunkel-Modus
 * ======================================================= */

/**
 * Standard-Design aus dem Customizer: auto | dark | light.
 */
function dunkel_default_mode() {
	$mode = get_theme_mod( 'dunkel_default_mode', 'auto' );
	return in_array( $mode, array( 'auto', 'dark', 'light' ), true ) ? $mode : 'auto';
}

/**
 * Setzt data-mode am <html>, bevor die Seite gezeichnet wird –
 * so blitzt beim Laden nie das falsche Design auf.
 */
function dunkel_mode_head_script() {
	$fallback = dunkel_default_mode();
	$toggle   = get_theme_mod( 'dunkel_show_toggle', true ) ? 'true' : 'false';
	?>
	<script>
	(function(){var d=document.documentElement,s=null,f=<?php echo wp_json_encode( $fallback ); ?>;
	if(<?php echo $toggle; // phpcs:ignore ?>){try{s=localStorage.getItem('dunkel-mode');}catch(e){}}
	var sys=window.matchMedia&&matchMedia('(prefers-color-scheme: light)').matches?'light':'dark';
	d.setAttribute('data-mode',(s==='light'||s==='dark')?s:(f==='auto'?sys:f));})();
	</script>
	<?php
}
add_action( 'wp_head', 'dunkel_mode_head_script', 0 );

/**
 * Hell/Dunkel-Umschalter ausgeben.
 */
function dunkel_mode_toggle() {
	if ( ! get_theme_mod( 'dunkel_show_toggle', true ) ) {
		return;
	}
	?>
	<button type="button" class="mode-toggle" aria-label="<?php esc_attr_e( 'Design wechseln', 'wx-easy-template' ); ?>">
		<svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6"/></svg>
		<svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.6A8.2 8.2 0 0 1 9.4 4a8.2 8.2 0 1 0 10.6 10.6Z"/></svg>
	</button>
	<?php
}

/* =========================================================
 * Farbschemata
 * ======================================================= */

/**
 * Verfügbare Farbschemata.
 * Die festen Farbschemata sind in style.css definiert.
 * Reihenfolge der Vorschaufarben: dunkler Hintergrund, dunkler Akzent,
 * heller Hintergrund, heller Akzent.
 */
function dunkel_color_schemes() {
	return array(
		'marine'    => array(
			'label'  => __( 'Marineblau', 'wx-easy-template' ),
			'colors' => array( '#0b1628', '#84a4dc', '#f7f9fc', '#1f3c70' ),
		),
		'petrol'    => array(
			'label'  => __( 'Petrol', 'wx-easy-template' ),
			'colors' => array( '#0a1d20', '#6cc4c4', '#f5fafa', '#0f5e63' ),
		),
		'wald'      => array(
			'label'  => __( 'Waldgrün', 'wx-easy-template' ),
			'colors' => array( '#0d1a12', '#8fc79c', '#f6faf6', '#2a5c38' ),
		),
		'bordeaux'  => array(
			'label'  => __( 'Bordeaux', 'wx-easy-template' ),
			'colors' => array( '#1c0d12', '#e591a6', '#fcf7f8', '#7a2338' ),
		),
		'aubergine' => array(
			'label'  => __( 'Aubergine', 'wx-easy-template' ),
			'colors' => array( '#160f22', '#b9a0e8', '#f9f7fc', '#4e3487' ),
		),
		'graphit'   => array(
			'label'  => __( 'Graphit & Bernstein', 'wx-easy-template' ),
			'colors' => array( '#141517', '#e0b45c', '#f7f7f8', '#855a10' ),
		),
		'custom'    => array(
			'label'  => __( 'Eigenes Farbschema', 'wx-easy-template' ),
			'colors' => array( '#0b1628', '#84a4dc', '#f7f9fc', '#1f3c70' ),
		),
	);
}

/**
 * Ausgangsfarben für gespeicherte Paletten aus Version 1.6.
 */
function dunkel_legacy_palette_defaults() {
	return array(
		'light' => array(
			'bg'            => '#f7f9fc',
			'bg-raised'     => '#e9eef6',
			'text'          => '#1e2a40',
			'heading'       => '#0b1a33',
			'scheme-accent' => '#1f3c70',
			'button-bg'     => '#1f3c70',
			'on-accent'     => '#ffffff',
		),
		'dark'  => array(
			'bg'            => '#0b1628',
			'bg-raised'     => '#112038',
			'text'          => '#d5dcea',
			'heading'       => '#f2f5fb',
			'scheme-accent' => '#84a4dc',
			'button-bg'     => '#1f3c70',
			'on-accent'     => '#ffffff',
		),
	);
}

function dunkel_simple_palette_defaults() {
	$legacy = dunkel_legacy_palette_defaults();

	return array(
		'light'  => dunkel_sanitize_theme_color( get_theme_mod( 'dunkel_palette_light_bg' ) ) ?: $legacy['light']['bg'],
		'dark'   => dunkel_sanitize_theme_color( get_theme_mod( 'dunkel_palette_dark_bg' ) ) ?: $legacy['dark']['bg'],
		'accent' => dunkel_sanitize_theme_color( get_theme_mod( 'dunkel_palette_light_scheme-accent' ) ) ?: $legacy['light']['scheme-accent'],
	);
}

function dunkel_color_channels( $hex ) {
	$hex = substr( $hex, 1 );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	return array_map( 'hexdec', str_split( $hex, 2 ) );
}

function dunkel_color_mix( $base, $tint, $amount ) {
	$channels = array_map(
		static function ( $from, $to ) use ( $amount ) {
			return (int) round( $from + ( $to - $from ) * $amount );
		},
		dunkel_color_channels( $base ),
		dunkel_color_channels( $tint )
	);

	return sprintf( '#%02x%02x%02x', ...$channels );
}

function dunkel_color_luminance( $hex ) {
	list( $red, $green, $blue ) = array_map(
		static function ( $channel ) {
			$value = $channel / 255;
			return $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		},
		dunkel_color_channels( $hex )
	);

	return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
}

function dunkel_color_contrast( $first, $second ) {
	$a = dunkel_color_luminance( $first ) + 0.05;
	$b = dunkel_color_luminance( $second ) + 0.05;
	return max( $a, $b ) / min( $a, $b );
}

/**
 * Text und Akzente aus den gewählten Grundfarben ableiten.
 */
function dunkel_generate_palette( $background, $accent ) {
	$text    = dunkel_color_contrast( '#000000', $background ) >= dunkel_color_contrast( '#ffffff', $background ) ? '#000000' : '#ffffff';
	$surface = dunkel_color_mix( $background, $text, 0.07 );
	if ( dunkel_color_contrast( $text, $surface ) < 4.5 ) {
		$surface = $background;
	}

	$link = $accent;
	for ( $step = 1; $step <= 10 && min( dunkel_color_contrast( $link, $background ), dunkel_color_contrast( $link, $surface ) ) < 4.5; $step++ ) {
		$link = dunkel_color_mix( $accent, $text, $step / 10 );
	}

	$button_text = dunkel_color_contrast( '#000000', $accent ) >= dunkel_color_contrast( '#ffffff', $accent ) ? '#000000' : '#ffffff';
	$button_tint = '#000000' === $button_text ? '#ffffff' : '#000000';
	$muted       = dunkel_color_mix( $text, $background, 0.18 );

	return array(
		'bg'            => $background,
		'bg-raised'     => $surface,
		'bg-sub'        => $surface,
		'text'          => $text,
		'text-muted'    => dunkel_color_contrast( $muted, $surface ) >= 4.5 ? $muted : $text,
		'heading'       => $text,
		'scheme-accent' => $link,
		'button-bg'     => $accent,
		'button-hover'  => dunkel_color_mix( $accent, $button_tint, 0.1 ),
		'on-accent'     => $button_text,
	);
}

function dunkel_custom_palette() {
	global $wp_customize;

	$defaults = dunkel_simple_palette_defaults();
	$stored   = (array) get_theme_mods();
	$base     = array();
	$has_base = false;

	foreach ( $defaults as $role => $default ) {
		// Die Standardwerte der Vorschau zählen nicht als gespeicherte Farbwahl.
		$id            = "dunkel_palette_base_{$role}";
		$setting       = is_customize_preview() ? $wp_customize->get_setting( $id ) : null;
		$value         = $stored[ $id ] ?? null;
		$saved         = dunkel_sanitize_theme_color( $setting ? $setting->post_value( $value ) : $value );
		$base[ $role ] = $saved ?: $default;
		$has_base      = $has_base || (bool) $saved;
	}

	if ( ! $has_base ) {
		$legacy     = dunkel_legacy_palette_defaults();
		$has_legacy = false;

		foreach ( $legacy as $mode => $colors ) {
			foreach ( array_keys( $colors ) as $property ) {
				$saved = dunkel_sanitize_theme_color( get_theme_mod( "dunkel_palette_{$mode}_{$property}" ) );
				if ( $saved ) {
					$legacy[ $mode ][ $property ] = $saved;
					$has_legacy = true;
				}
			}
		}

		if ( $has_legacy ) {
			return $legacy;
		}
	}

	return array(
		'light' => dunkel_generate_palette( $base['light'], $base['accent'] ),
		'dark'  => dunkel_generate_palette( $base['dark'], $base['accent'] ),
	);
}

/**
 * Aktives Farbschema.
 */
function dunkel_color_scheme() {
	return dunkel_sanitize_scheme( get_theme_mod( 'dunkel_color_scheme', 'marine' ) );
}

function dunkel_sanitize_scheme( $value ) {
	return is_string( $value ) && isset( dunkel_color_schemes()[ $value ] ) ? $value : 'marine';
}

function dunkel_sanitize_theme_color( $value ) {
	return is_string( $value ) ? sanitize_hex_color( $value ) : null;
}

/* =========================================================
 * Customizer
 * ======================================================= */

/**
 * Zahl auf einen Bereich begrenzen.
 */
function dunkel_sanitize_range( $value, $setting ) {
	$attrs = $setting->manager->get_control( $setting->id )->input_attrs;
	$value = absint( $value );
	return max( $attrs['min'], min( $attrs['max'], $value ) );
}

function dunkel_sanitize_mode( $value ) {
	return in_array( $value, array( 'auto', 'dark', 'light' ), true ) ? $value : 'auto';
}

/**
 * Verfügbare Schriftarten.
 * Alle Webfonts liegen im Theme (assets/fonts, SIL Open Font License) –
 * es werden keine externen Server wie Google Fonts angefragt.
 * Noto Serif wird in style.css eingebunden, die übrigen Webfonts nur bei Bedarf
 * über dunkel_fonts_css(). Je Datei: Dateiname, Strichstärke, Stil.
 */
function dunkel_fonts() {
	return array(
		'system' => array(
			'label' => __( 'Systemschrift', 'wx-easy-template' ),
			'desc'  => __( 'Schlicht, lädt nichts nach', 'wx-easy-template' ),
			'stack' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
		),
		'arial' => array(
			'label' => __( 'Arial', 'wx-easy-template' ),
			'desc'  => __( 'Klassisch, lädt nichts nach', 'wx-easy-template' ),
			'stack' => 'Arial, Helvetica, sans-serif',
		),
		'inter' => array(
			'label' => __( 'Inter', 'wx-easy-template' ),
			'desc'  => __( 'Modern und sachlich', 'wx-easy-template' ),
			'stack' => '"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			'family' => 'Inter',
			'files' => array(
				array( 'Inter-Regular.woff', '400', 'normal' ),
				array( 'Inter-Italic.woff', '400', 'italic' ),
				array( 'Inter-SemiBold.woff', '600', 'normal' ),
				array( 'Inter-Bold.woff', '700', 'normal' ),
			),
		),
		'work-sans' => array(
			'label' => __( 'Work Sans', 'wx-easy-template' ),
			'desc'  => __( 'Freundlich und offen', 'wx-easy-template' ),
			'stack' => '"Work Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			'family' => 'Work Sans',
			'files' => array(
				array( 'WorkSans-Regular.woff', '400', 'normal' ),
				array( 'WorkSans-Italic.woff', '400', 'italic' ),
				array( 'WorkSans-Bold.woff', '700', 'normal' ),
				array( 'WorkSans-BoldItalic.woff', '700', 'italic' ),
			),
		),
		'instrument-sans' => array(
			'label' => __( 'Instrument Sans', 'wx-easy-template' ),
			'desc'  => __( 'Schmal und elegant', 'wx-easy-template' ),
			'stack' => '"Instrument Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			'family' => 'Instrument Sans',
			'files' => array(
				array( 'InstrumentSans-Regular.woff', '400', 'normal' ),
				array( 'InstrumentSans-Italic.woff', '400', 'italic' ),
				array( 'InstrumentSans-Bold.woff', '700', 'normal' ),
				array( 'InstrumentSans-BoldItalic.woff', '700', 'italic' ),
			),
		),
		'poppins' => array(
			'label' => __( 'Poppins', 'wx-easy-template' ),
			'desc'  => __( 'Geometrisch und rund', 'wx-easy-template' ),
			'stack' => '"Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			'family' => 'Poppins',
			'files' => array(
				array( 'Poppins-Regular.woff', '400', 'normal' ),
				array( 'Poppins-Italic.woff', '400', 'italic' ),
				array( 'Poppins-Medium.woff', '500', 'normal' ),
				array( 'Poppins-Bold.woff', '700', 'normal' ),
			),
		),
		'outfit' => array(
			'label' => __( 'Outfit', 'wx-easy-template' ),
			'desc'  => __( 'Geometrisch, gut für Überschriften', 'wx-easy-template' ),
			'stack' => '"Outfit", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			'family' => 'Outfit',
			'files' => array(
				array( 'Outfit-Regular.woff', '400', 'normal' ),
				array( 'Outfit-Bold.woff', '700', 'normal' ),
			),
		),
		'georgia' => array(
			'label' => __( 'Georgia', 'wx-easy-template' ),
			'desc'  => __( 'Klassische Serifenschrift, lädt nichts nach', 'wx-easy-template' ),
			'stack' => 'Georgia, "Times New Roman", serif',
		),
		'noto-serif' => array(
			'label' => __( 'Noto Serif', 'wx-easy-template' ),
			'desc'  => __( 'Serifen, ruhig und gut lesbar', 'wx-easy-template' ),
			'stack' => '"Noto Serif", Georgia, "Times New Roman", serif',
		),
		'lora' => array(
			'label' => __( 'Lora', 'wx-easy-template' ),
			'desc'  => __( 'Serifen, angenehm für längere Texte', 'wx-easy-template' ),
			'stack' => '"Lora", Georgia, "Times New Roman", serif',
			'family' => 'Lora',
			'files' => array(
				array( 'Lora.woff', '400 700', 'normal' ),
				array( 'Lora-Italic.woff', '400 700', 'italic' ),
			),
		),
		'ibm-plex-serif' => array(
			'label' => __( 'IBM Plex Serif', 'wx-easy-template' ),
			'desc'  => __( 'Serifen, technisch-klar', 'wx-easy-template' ),
			'stack' => '"IBM Plex Serif", Georgia, "Times New Roman", serif',
			'family' => 'IBM Plex Serif',
			'files' => array(
				array( 'IBMPlexSerif-Regular.woff', '400', 'normal' ),
				array( 'IBMPlexSerif-Italic.woff', '400', 'italic' ),
				array( 'IBMPlexSerif-Bold.woff', '700', 'normal' ),
				array( 'IBMPlexSerif-BoldItalic.woff', '700', 'italic' ),
			),
		),
		'crimson-pro' => array(
			'label' => __( 'Crimson Pro', 'wx-easy-template' ),
			'desc'  => __( 'Serifen, buchartig', 'wx-easy-template' ),
			'stack' => '"Crimson Pro", Georgia, "Times New Roman", serif',
			'family' => 'Crimson Pro',
			'size'  => '112%',
			'files' => array(
				array( 'CrimsonPro-Regular.woff', '400', 'normal' ),
				array( 'CrimsonPro-Italic.woff', '400', 'italic' ),
				array( 'CrimsonPro-Bold.woff', '700', 'normal' ),
			),
		),
		'young-serif' => array(
			'label' => __( 'Young Serif', 'wx-easy-template' ),
			'desc'  => __( 'Kräftige Serifen, nur für Überschriften', 'wx-easy-template' ),
			'stack' => '"Young Serif", Georgia, "Times New Roman", serif',
			'family' => 'Young Serif',
			'files' => array(
				array( 'YoungSerif-Regular.woff', '400', 'normal' ),
			),
		),
	);
}

/**
 * Bis Version 1.8 gab es nur eine Schrift-Vorgabe (dunkel_font_preset).
 * Daraus werden die Standardwerte der neuen Einstellungen abgeleitet,
 * damit bestehende Websites nach dem Update genauso aussehen wie vorher.
 */
function dunkel_legacy_font_preset() {
	$preset = get_theme_mod( 'dunkel_font_preset', 'draft' );
	return in_array( $preset, array( 'draft', 'system', 'noto-serif', 'georgia', 'arial' ), true ) ? $preset : 'draft';
}

function dunkel_font_defaults() {
	$preset = dunkel_legacy_font_preset();
	return array(
		'body'    => 'draft' === $preset ? 'system' : $preset,
		'heading' => 'body',
		'news'    => 'draft' === $preset ? 'noto-serif' : 'heading',
	);
}

function dunkel_sanitize_font( $value ) {
	return is_string( $value ) && array_key_exists( $value, dunkel_fonts() ) ? $value : 'system';
}

function dunkel_sanitize_heading_font( $value ) {
	return ( 'body' === $value || ( is_string( $value ) && array_key_exists( $value, dunkel_fonts() ) ) ) ? $value : 'body';
}

function dunkel_sanitize_news_font( $value ) {
	return ( 'heading' === $value || ( is_string( $value ) && array_key_exists( $value, dunkel_fonts() ) ) ) ? $value : 'heading';
}

/**
 * Gewählte Schriften: array( 'body' => slug, 'heading' => slug, 'news' => slug|'heading' ).
 */
function dunkel_active_fonts() {
	$defaults = dunkel_font_defaults();
	$body     = dunkel_sanitize_font( get_theme_mod( 'dunkel_font_body', $defaults['body'] ) );
	$heading  = dunkel_sanitize_heading_font( get_theme_mod( 'dunkel_font_heading', $defaults['heading'] ) );
	$news     = dunkel_sanitize_news_font( get_theme_mod( 'dunkel_font_news', $defaults['news'] ) );
	return array(
		'body'    => $body,
		'heading' => 'body' === $heading ? $body : $heading,
		'news'    => $news,
	);
}

/**
 * @font-face-Regeln und Schrift-Variablen für die gewählten Schriften.
 */
function dunkel_fonts_css() {
	$fonts  = dunkel_fonts();
	$active = dunkel_active_fonts();
	$base   = get_template_directory_uri() . '/assets/fonts/';
	$used   = array( $active['body'], $active['heading'] );
	if ( 'heading' !== $active['news'] ) {
		$used[] = $active['news'];
	}

	$css = '';
	foreach ( array_unique( $used ) as $slug ) {
		if ( empty( $fonts[ $slug ]['files'] ) ) {
			continue;
		}
		foreach ( $fonts[ $slug ]['files'] as $file ) {
			$css .= sprintf(
				'@font-face{font-family:"%1$s";src:url("%2$s") format("woff");font-weight:%3$s;font-style:%4$s;font-display:swap;%5$s}',
				$fonts[ $slug ]['family'],
				esc_url( $base . $slug . '/' . $file[0] ),
				$file[1],
				$file[2],
				isset( $fonts[ $slug ]['size'] ) ? 'size-adjust:' . $fonts[ $slug ]['size'] . ';' : ''
			);
		}
	}

	$css .= ':root{--font:' . $fonts[ $active['body'] ]['stack'] . ';--font-heading:' . $fonts[ $active['heading'] ]['stack'] . ';';
	if ( 'heading' !== $active['news'] ) {
		$css .= '--news-title-font:' . $fonts[ $active['news'] ]['stack'] . ';--news-title-weight:400;';
	}
	return $css . '}';
}

/**
 * Textschrift vorladen, damit sie ohne Verzögerung erscheint.
 */
function dunkel_preload_font() {
	$fonts = dunkel_fonts();
	$slug  = dunkel_active_fonts()['body'];
	if ( empty( $fonts[ $slug ]['files'] ) ) {
		return;
	}
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff" crossorigin>' . "\n",
		esc_url( get_template_directory_uri() . '/assets/fonts/' . $slug . '/' . $fonts[ $slug ]['files'][0][0] )
	);
}
add_action( 'wp_head', 'dunkel_preload_font', 1 );

/**
 * Optionen unter Design → Customizer.
 * Das Farbschema bekommt eine Auswahl mit Farbfeldern statt einfacher Radiobuttons.
 */
function dunkel_customize_register( $wp_customize ) {
	if ( ! class_exists( 'Dunkel_Scheme_Control' ) ) {
		/**
		 * Farbschema-Auswahl.
		 */
		class Dunkel_Scheme_Control extends WP_Customize_Control {
			public $type = 'dunkel_scheme';

			public function render_content() {
				$name = '_customize-radio-' . $this->id;
				?>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php if ( $this->description ) : ?>
					<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
				<?php endif; ?>
				<div class="dunkel-schemes">
					<?php foreach ( $this->choices as $key => $scheme ) : ?>
						<label class="dunkel-scheme">
							<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php $this->link(); ?> <?php checked( $this->value(), $key ); ?>>
							<span class="dunkel-scheme__swatch" aria-hidden="true">
								<span style="background:<?php echo esc_attr( $scheme['colors'][0] ); ?>"><i style="background:<?php echo esc_attr( $scheme['colors'][1] ); ?>"></i></span>
								<span style="background:<?php echo esc_attr( $scheme['colors'][2] ); ?>"><i style="background:<?php echo esc_attr( $scheme['colors'][3] ); ?>"></i></span>
							</span>
							<span class="dunkel-scheme__label"><?php echo esc_html( $scheme['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<?php
			}
		}
	}

	// Farbschema.
	$wp_customize->add_section(
		'dunkel_colors',
		array(
			'title'    => __( 'Farbschema', 'wx-easy-template' ),
			'priority' => 29,
		)
	);
	$wp_customize->add_setting(
		'dunkel_color_scheme',
		array(
			'default'           => 'marine',
			'sanitize_callback' => 'dunkel_sanitize_scheme',
		)
	);
	$schemes = dunkel_color_schemes();
	$palette = dunkel_custom_palette();
	$schemes['custom']['colors'] = array(
		$palette['dark']['bg'],
		$palette['dark']['scheme-accent'],
		$palette['light']['bg'],
		$palette['light']['scheme-accent'],
	);
	$wp_customize->add_control(
		new Dunkel_Scheme_Control(
			$wp_customize,
			'dunkel_color_scheme',
			array(
				'label'       => __( 'Farbschema', 'wx-easy-template' ),
				'description' => __( 'Wähle „Eigenes Farbschema“, um zwei Hintergrundfarben und eine gemeinsame Akzentfarbe festzulegen. Das Theme passt Texte, Kacheln und Schaltflächen automatisch an.', 'wx-easy-template' ),
				'section'     => 'dunkel_colors',
				'choices'     => $schemes,
			)
		)
	);

	$custom_scheme_active = static function ( $control ) {
		return 'custom' === $control->manager->get_setting( 'dunkel_color_scheme' )->value();
	};

	$wp_customize->add_setting(
		'dunkel_accent_color',
		array(
			'default'           => '',
			'sanitize_callback' => 'dunkel_sanitize_theme_color',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'dunkel_accent_color',
			array(
				'label'           => __( 'Akzentfarbe des vorgegebenen Schemas überschreiben', 'wx-easy-template' ),
				'description'     => __( 'Gilt im Hell- und Dunkelmodus. Leere das Feld, um die Farben der Vorlage wiederherzustellen.', 'wx-easy-template' ),
				'section'         => 'dunkel_colors',
				'active_callback' => static function ( $control ) use ( $custom_scheme_active ) {
					return ! $custom_scheme_active( $control );
				},
			)
		)
	);

	$color_labels = array(
		'light'  => __( 'Hintergrund im Hellmodus', 'wx-easy-template' ),
		'dark'   => __( 'Hintergrund im Dunkelmodus', 'wx-easy-template' ),
		'accent' => __( 'Akzentfarbe für beide Modi', 'wx-easy-template' ),
	);

	foreach ( dunkel_simple_palette_defaults() as $role => $default ) {
		$id = "dunkel_palette_base_{$role}";
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $default,
				'sanitize_callback' => 'dunkel_sanitize_theme_color',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				$id,
				array(
					'label'           => $color_labels[ $role ],
					'section'         => 'dunkel_colors',
					'active_callback' => $custom_scheme_active,
				)
			)
		);
	}

	$wp_customize->add_section(
		'dunkel_options',
		array(
			'title'    => __( 'Theme-Optionen', 'wx-easy-template' ),
			'priority' => 30,
		)
	);

	$wp_customize->add_section(
		'dunkel_typography',
		array(
			'title'       => __( 'Typografie', 'wx-easy-template' ),
			'description' => __( 'Alle Schriften sind kostenlos (Open Font License) und liegen im Theme. Es werden keine Daten an Google oder andere Anbieter übertragen.', 'wx-easy-template' ),
			'priority'    => 31,
		)
	);
	$font_defaults = dunkel_font_defaults();
	$font_choices  = array();
	foreach ( dunkel_fonts() as $slug => $font ) {
		$font_choices[ $slug ] = $font['label'] . ' – ' . $font['desc'];
	}

	$wp_customize->add_setting(
		'dunkel_font_body',
		array(
			'default'           => $font_defaults['body'],
			'sanitize_callback' => 'dunkel_sanitize_font',
		)
	);
	$wp_customize->add_control(
		'dunkel_font_body',
		array(
			'label'   => __( 'Schrift für Text und Menü', 'wx-easy-template' ),
			'section' => 'dunkel_typography',
			'type'    => 'select',
			'choices' => $font_choices,
		)
	);

	$wp_customize->add_setting(
		'dunkel_font_heading',
		array(
			'default'           => $font_defaults['heading'],
			'sanitize_callback' => 'dunkel_sanitize_heading_font',
		)
	);
	$wp_customize->add_control(
		'dunkel_font_heading',
		array(
			'label'   => __( 'Schrift für Überschriften', 'wx-easy-template' ),
			'section' => 'dunkel_typography',
			'type'    => 'select',
			'choices' => array( 'body' => __( 'Wie Text', 'wx-easy-template' ) ) + $font_choices,
		)
	);

	$wp_customize->add_setting(
		'dunkel_font_news',
		array(
			'default'           => $font_defaults['news'],
			'sanitize_callback' => 'dunkel_sanitize_news_font',
		)
	);
	$wp_customize->add_control(
		'dunkel_font_news',
		array(
			'label'       => __( 'Schrift für „Aktuelles“-Beitragstitel', 'wx-easy-template' ),
			'description' => __( 'Titel der Beitragskarten auf der Übersichtsseite.', 'wx-easy-template' ),
			'section'     => 'dunkel_typography',
			'type'        => 'select',
			'choices'     => array( 'heading' => __( 'Wie Überschriften', 'wx-easy-template' ) ) + $font_choices,
		)
	);

	// Bildecken.
	$wp_customize->add_setting(
		'dunkel_img_radius',
		array(
			'default'           => 10,
			'sanitize_callback' => 'dunkel_sanitize_range',
		)
	);
	$wp_customize->add_control(
		'dunkel_img_radius',
		array(
			'label'       => __( 'Abrundung der Bildecken (px)', 'wx-easy-template' ),
			'description' => __( '0 = eckig. Gilt für Beitragsbilder und Bilder im Inhalt.', 'wx-easy-template' ),
			'section'     => 'dunkel_options',
			'type'        => 'range',
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 24,
				'step' => 1,
			),
		)
	);

	// Logogröße.
	$wp_customize->add_setting(
		'dunkel_logo_height',
		array(
			'default'           => 80,
			'sanitize_callback' => 'dunkel_sanitize_range',
		)
	);
	$wp_customize->add_control(
		'dunkel_logo_height',
		array(
			'label'       => __( 'Logohöhe (px)', 'wx-easy-template' ),
			'description' => __( 'Die Kopfzeile passt sich automatisch an. Auf dem Handy wird das Logo höchstens 50 px hoch.', 'wx-easy-template' ),
			'section'     => 'dunkel_options',
			'type'        => 'range',
			'input_attrs' => array(
				'min'  => 30,
				'max'  => 160,
				'step' => 2,
			),
		)
	);

	// Inhaltsbreite.
	$wp_customize->add_setting(
		'dunkel_content_width',
		array(
			'default'           => 1000,
			'sanitize_callback' => 'dunkel_sanitize_range',
		)
	);
	$wp_customize->add_control(
		'dunkel_content_width',
		array(
			'label'       => __( 'Inhaltsbreite (px)', 'wx-easy-template' ),
			'description' => __( 'Maximale Breite des Inhaltsbereichs.', 'wx-easy-template' ),
			'section'     => 'dunkel_options',
			'type'        => 'range',
			'input_attrs' => array(
				'min'  => 600,
				'max'  => 1400,
				'step' => 20,
			),
		)
	);

	// Standard-Design.
	$wp_customize->add_setting(
		'dunkel_default_mode',
		array(
			'default'           => 'auto',
			'sanitize_callback' => 'dunkel_sanitize_mode',
		)
	);
	$wp_customize->add_control(
		'dunkel_default_mode',
		array(
			'label'   => __( 'Standard-Design', 'wx-easy-template' ),
			'section' => 'dunkel_options',
			'type'    => 'radio',
			'choices' => array(
				'auto'  => __( 'Automatisch (nach Geräteeinstellung)', 'wx-easy-template' ),
				'dark'  => __( 'Immer dunkel', 'wx-easy-template' ),
				'light' => __( 'Immer hell', 'wx-easy-template' ),
			),
		)
	);

	// Umschalter anzeigen.
	$wp_customize->add_setting(
		'dunkel_show_toggle',
		array(
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	$wp_customize->add_control(
		'dunkel_show_toggle',
		array(
			'label'       => __( 'Hell/Dunkel-Umschalter im Header anzeigen', 'wx-easy-template' ),
			'description' => __( 'Besucher können das Design selbst wählen; die Wahl wird im Browser gespeichert.', 'wx-easy-template' ),
			'section'     => 'dunkel_options',
			'type'        => 'checkbox',
		)
	);

	// Logo für das helle Design.
	$wp_customize->add_setting(
		'dunkel_logo_light',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'dunkel_logo_light',
			array(
				'label'       => __( 'Logo für das helle Design (optional)', 'wx-easy-template' ),
				'description' => __( 'Wird statt des normalen Logos gezeigt, wenn das helle Design aktiv ist.', 'wx-easy-template' ),
				'section'     => 'dunkel_options',
				'mime_type'   => 'image',
			)
		)
	);
}
add_action( 'customize_register', 'dunkel_customize_register' );

/**
 * Nach dem Theme-Upload beim Schließen des Customizers die Theme-Übersicht öffnen.
 */
function dunkel_customizer_return_after_upload() {
	global $wp_customize;

	$return_url  = $wp_customize->get_return_url();
	$upload_path = wp_parse_url( admin_url( 'update.php' ), PHP_URL_PATH );

	if ( wp_parse_url( $return_url, PHP_URL_PATH ) !== $upload_path ) {
		return;
	}

	wp_parse_str( (string) wp_parse_url( $return_url, PHP_URL_QUERY ), $query );

	if ( 'upload-theme' === ( $query['action'] ?? '' ) ) {
		$wp_customize->set_return_url( admin_url( 'themes.php' ) );
	}
}
add_action( 'customize_controls_init', 'dunkel_customizer_return_after_upload' );

/**
 * CSS-Variablen aus den Customizer-Werten.
 */
function dunkel_customizer_css() {
	$logo  = max( 30, min( 160, (int) get_theme_mod( 'dunkel_logo_height', 80 ) ) );
	$width = max( 600, min( 1400, (int) get_theme_mod( 'dunkel_content_width', 1000 ) ) );
	$img   = max( 0, min( 24, (int) get_theme_mod( 'dunkel_img_radius', 10 ) ) );
	$color = dunkel_sanitize_theme_color( get_theme_mod( 'dunkel_accent_color', '' ) );
	$css   = dunkel_fonts_css() . sprintf(
		':root{--logo-h:%dpx;--content-w:%dpx;--img-radius:%dpx;%s}',
		$logo,
		$width,
		$img,
		$color ? '--custom-accent:' . $color . ';' : ''
	);

	if ( 'custom' === dunkel_color_scheme() ) {
		foreach ( dunkel_custom_palette() as $mode => $colors ) {
			$css .= ':root[data-scheme="custom"]';
			$css .= 'light' === $mode ? '[data-mode="light"]{' : '{';
			foreach ( $colors as $property => $value ) {
				$css .= '--' . $property . ':' . $value . ';';
			}
			$css .= '}';
		}
	}

	return $css;
}

/**
 * Zahlenwert neben den Schiebereglern im Customizer anzeigen.
 */
function dunkel_customizer_range_values() {
	?>
	<script>
	(function(api){
		['dunkel_logo_height','dunkel_content_width','dunkel_img_radius'].forEach(function(id){
			api.control(id,function(control){
				control.deferred.embedded.done(function(){
					var r=control.container.find('input[type="range"]')[0];
					if(!r){return;}
					var o=document.createElement('output');
					o.style.cssText='display:inline-block;min-width:4em;margin-left:8px;font-weight:600;';
					o.textContent=r.value+' px';
					r.insertAdjacentElement('afterend',o);
					r.addEventListener('input',function(){o.textContent=r.value+' px';});
				});
			});
		});
	})(wp.customize);
	</script>
	<?php
}
add_action( 'customize_controls_print_footer_scripts', 'dunkel_customizer_range_values' );

/**
 * Darstellung der Farbschema-Auswahl im Customizer.
 */
function dunkel_customizer_scheme_styles() {
	?>
	<style>
	.dunkel-schemes{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px;}
	.dunkel-scheme{position:relative;display:block;cursor:pointer;}
	.dunkel-scheme input{position:absolute;opacity:0;}
	.dunkel-scheme__swatch{display:flex;height:56px;border-radius:8px;overflow:hidden;border:2px solid #dcdcde;transition:border-color .15s,box-shadow .15s;}
	.dunkel-scheme__swatch>span{flex:1;display:flex;align-items:center;justify-content:center;}
	.dunkel-scheme__swatch i{display:block;width:18px;height:18px;border-radius:50%;}
	.dunkel-scheme__label{display:block;margin-top:5px;font-size:12px;text-align:center;}
	.dunkel-scheme:hover .dunkel-scheme__swatch{border-color:#8c8f94;}
	.dunkel-scheme input:focus-visible+.dunkel-scheme__swatch{box-shadow:0 0 0 2px #fff,0 0 0 4px #2271b1;}
	.dunkel-scheme input:checked+.dunkel-scheme__swatch{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;}
	.dunkel-scheme input:checked~.dunkel-scheme__label{font-weight:600;}
	</style>
	<?php
}
add_action( 'customize_controls_print_styles', 'dunkel_customizer_scheme_styles' );
