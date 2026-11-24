<?php
/**
 * Waxenberger IT-Service – Theme-Funktionen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WAXENBERGER_VERSION', '1.3.0' );

// Updates aus dem GitHub-Repository (Dashboard → Aktualisierungen).
require_once __DIR__ . '/inc/updater.php';

function waxenberger_setup() {
	load_theme_textdomain( 'waxenberger', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/editor-style.css' );

	add_theme_support(
		'custom-logo',
		array(
			'height'      => 300,
			'width'       => 877,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_theme_support(
		'editor-color-palette',
		array(
			array( 'name' => 'Dunkelblau', 'slug' => 'wx-blue', 'color' => '#0d3082' ),
			array( 'name' => 'Hellblau', 'slug' => 'wx-light', 'color' => '#e8efff' ),
			array( 'name' => 'Weiß', 'slug' => 'white', 'color' => '#ffffff' ),
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Hauptmenü', 'waxenberger' ),
		)
	);
}
add_action( 'after_setup_theme', 'waxenberger_setup' );

function waxenberger_assets() {
	wp_enqueue_style( 'waxenberger-style', get_stylesheet_uri(), array(), WAXENBERGER_VERSION );
	wp_enqueue_script(
		'waxenberger-navigation',
		get_template_directory_uri() . '/assets/js/navigation.js',
		array(),
		WAXENBERGER_VERSION,
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'waxenberger_assets' );

/**
 * Datenschutz: Emoji-Skript abschalten. Es lädt sonst bei Bedarf Grafiken
 * vom WordPress-Server (s.w.org) nach.
 */
function waxenberger_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'waxenberger_disable_emojis' );

/**
 * Einblend-Animation vorbereiten: Inhalt erst unsichtbar, navigation.js blendet ihn ein.
 * Steht früh im <head>, damit nichts aufblitzt. Ohne JavaScript bleibt der Inhalt sichtbar.
 */
function waxenberger_reveal_flag() {
	echo "<script>document.documentElement.classList.add('wx-reveal-on');</script>\n";
}
add_action( 'wp_head', 'waxenberger_reveal_flag', 1 );

/**
 * Weiterleitungen von den alten Joomla-Adressen (z. B. /index.php/service) auf die neuen Seiten.
 * WordPress schreibt diese Regeln beim Speichern der Permalinks in die .htaccess (vor die WordPress-Regeln).
 */
function waxenberger_joomla_redirects( $rules ) {
	$map = array(
		'service'     => '/service/',
		'impressum'   => '/impressum/',
		'datenschutz' => '/datenschutz/',
	);
	$out  = "# BEGIN Weiterleitungen alte Joomla-Seite\n<IfModule mod_rewrite.c>\nRewriteEngine On\n";
	foreach ( $map as $old => $new ) {
		$out .= 'RewriteRule ^index\.php/' . $old . '/?$ ' . $new . " [R=301,L]\n";
	}
	$out .= "</IfModule>\n# END Weiterleitungen alte Joomla-Seite\n\n";
	return $out . $rules;
}
add_filter( 'mod_rewrite_rules', 'waxenberger_joomla_redirects' );

/**
 * Favicon aus dem Logo (das „x“ mit dem Bogen).
 * Wird nur ausgegeben, solange unter Design → Customizer → Website-Informationen
 * kein eigenes Website-Icon eingestellt ist – das hat Vorrang.
 */
function waxenberger_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	$dir = get_template_directory_uri() . '/assets/images/';
	printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", esc_url( $dir . 'favicon-32.png' ) );
	printf( '<link rel="icon" href="%s" sizes="192x192">' . "\n", esc_url( $dir . 'favicon-192.png' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $dir . 'apple-touch-icon.png' ) );
}
add_action( 'wp_head', 'waxenberger_favicon', 5 );
add_action( 'admin_head', 'waxenberger_favicon' );
add_action( 'login_head', 'waxenberger_favicon' );

/**
 * Customizer: Text in der Fußzeile.
 */
function waxenberger_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'waxenberger_footer',
		array(
			'title'    => __( 'Fußzeile', 'waxenberger' ),
			'priority' => 120,
		)
	);
	$wp_customize->add_setting(
		'waxenberger_footer_text',
		array(
			'default'           => '© ' . date( 'Y' ) . ' IT-Service Christian Waxenberger',
			'sanitize_callback' => 'wp_kses_post',
		)
	);
	$wp_customize->add_control(
		'waxenberger_footer_text',
		array(
			'label'   => __( 'Text in der Fußzeile', 'waxenberger' ),
			'section' => 'waxenberger_footer',
			'type'    => 'text',
		)
	);
}
add_action( 'customize_register', 'waxenberger_customize_register' );

/**
 * Logo ausgeben: eigenes Logo aus dem Customizer, sonst das mitgelieferte.
 */
function waxenberger_logo() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	$logo = get_template_directory_uri() . '/assets/images/logo.png';
	printf(
		'<a href="%1$s" rel="home"><img src="%2$s" width="395" height="135" alt="%3$s"></a>',
		esc_url( home_url( '/' ) ),
		esc_url( $logo ),
		esc_attr( get_bloginfo( 'name' ) )
	);
}

/**
 * Ersatzmenü, solange noch kein Menü zugewiesen ist.
 */
function waxenberger_fallback_menu() {
	echo '<div class="menu-wrap"><ul id="primary-menu" class="menu">';
	wp_list_pages(
		array(
			'title_li' => '',
			'depth'    => 1,
		)
	);
	echo '</ul></div>';
}
