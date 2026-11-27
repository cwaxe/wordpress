<?php
/**
 * LSV Flugplatz – eigenständiges Theme für lsv-vilsbiburg.de
 * im Stil von wetter.lsv-vilsbiburg.de
 *
 * Kompatibel mit Inhalten aus Twenty Twenty-One:
 * gleiche Menüpositionen (primary, footer), gleicher Widget-Bereich (sidebar-1),
 * gleiche Block-Stile (twentytwentyone-border usw.) und gleiche Farb-/Schriftgrößen-Klassen.
 *
 * @package LSV_Flugplatz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LSV_VERSION', '1.2.1' );

// Wetter-Block: nur laden, wenn das Plugin "LSV Wetter am Platz" nicht aktiv ist.
if ( ! defined( 'LSVW_VERSION' ) ) {
	require_once get_template_directory() . '/inc/wetter.php';
}

/* -------------------------------------------------------------------------
 * Theme-Setup
 * ---------------------------------------------------------------------- */
function lsv_setup() {
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	set_post_thumbnail_size( 1568, 9999 );
	add_theme_support( 'html5', array( 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets', 'search-form' ) );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'custom-line-height' );
	add_theme_support( 'custom-spacing' );
	add_theme_support( 'custom-units' );
	add_theme_support( 'link-color' );

	add_theme_support(
		'custom-logo',
		array(
			'height'               => 120,
			'width'                => 300,
			'flex-width'           => true,
			'flex-height'          => true,
			'unlink-homepage-logo' => false,
		)
	);

	add_theme_support( 'custom-background', array( 'default-color' => '486d97' ) );

	// Gleiche Menüpositionen wie Twenty Twenty-One → Menüs bleiben zugeordnet.
	register_nav_menus(
		array(
			'primary' => 'Hauptmenü',
			'footer'  => 'Fußzeilen-Menü',
		)
	);

	// Editor soll wie die Website aussehen.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor-style.css' );

	// Farbpalette: LSV-Farben + die Slugs von Twenty Twenty-One,
	// damit vorhandene Farbklassen in Inhalten weiter funktionieren.
	add_theme_support(
		'editor-color-palette',
		array(
			array( 'name' => 'LSV Blau',        'slug' => 'lsv-blau',        'color' => '#486d97' ),
			array( 'name' => 'LSV Blau dunkel', 'slug' => 'lsv-blau-dunkel', 'color' => '#3a5a7f' ),
			array( 'name' => 'Karte',           'slug' => 'lsv-karte',       'color' => '#2c4767' ),
			array( 'name' => 'Karte hell',      'slug' => 'lsv-karte-hell',  'color' => '#385879' ),
			array( 'name' => 'Akzent',          'slug' => 'lsv-akzent',      'color' => '#8fb3dc' ),
			array( 'name' => 'Titel',           'slug' => 'lsv-titel',       'color' => '#d3e3f4' ),
			array( 'name' => 'Text',            'slug' => 'lsv-text',        'color' => '#f2f6fa' ),
			array( 'name' => 'Text leise',      'slug' => 'lsv-text-leise',  'color' => '#b6c7da' ),
			array( 'name' => 'Grün',            'slug' => 'lsv-gut',         'color' => '#2e8b57' ),
			array( 'name' => 'Rot',             'slug' => 'lsv-warn',        'color' => '#c0392b' ),
			array( 'name' => 'Schwarz',         'slug' => 'black',           'color' => '#000000' ),
			array( 'name' => 'Weiß',            'slug' => 'white',           'color' => '#ffffff' ),
		)
	);

	add_theme_support(
		'editor-font-sizes',
		array(
			array( 'name' => 'Sehr klein', 'shortName' => 'XS', 'size' => 13, 'slug' => 'extra-small' ),
			array( 'name' => 'Klein',      'shortName' => 'S',  'size' => 14, 'slug' => 'small' ),
			array( 'name' => 'Normal',     'shortName' => 'M',  'size' => 16, 'slug' => 'normal' ),
			array( 'name' => 'Groß',       'shortName' => 'L',  'size' => 22, 'slug' => 'large' ),
			array( 'name' => 'Sehr groß',  'shortName' => 'XL', 'size' => 30, 'slug' => 'extra-large' ),
			array( 'name' => 'Riesig',     'shortName' => 'XXL','size' => 40, 'slug' => 'huge' ),
			array( 'name' => 'Gigantisch', 'shortName' => 'XXXL','size' => 56, 'slug' => 'gigantic' ),
		)
	);
}
add_action( 'after_setup_theme', 'lsv_setup' );

/* Inhaltsbreite für Einbettungen (YouTube usw.) */
function lsv_content_width() {
	$GLOBALS['content_width'] = 1100; // phpcs:ignore
}
add_action( 'after_setup_theme', 'lsv_content_width', 0 );

/* -------------------------------------------------------------------------
 * Styles & Scripts
 * ---------------------------------------------------------------------- */
function lsv_scripts() {
	wp_enqueue_style( 'lsv-style', get_stylesheet_uri(), array(), LSV_VERSION );
	wp_enqueue_script( 'lsv-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), LSV_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'lsv_scripts' );

/* -------------------------------------------------------------------------
 * Widget-Bereich (gleiche ID wie Twenty Twenty-One)
 * ---------------------------------------------------------------------- */
function lsv_widgets_init() {
	register_sidebar(
		array(
			'name'          => 'Fußzeile',
			'id'            => 'sidebar-1',
			'description'   => 'Widgets in der Fußzeile, nebeneinander als Karten.',
			'before_widget' => '<section id="%1$s" class="widget lsv-karte %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'lsv_widgets_init' );

/* -------------------------------------------------------------------------
 * Block-Stile
 * Die Twenty-Twenty-One-Stile werden mit gleichem Namen registriert,
 * damit vorhandene Inhalte (z. B. Titelbild mit Rahmen) weiter funktionieren.
 * ---------------------------------------------------------------------- */
function lsv_block_styles() {
	// LSV-eigene Stile.
	register_block_style( 'core/group',   array( 'name' => 'lsv-karte',       'label' => 'LSV Karte' ) );
	register_block_style( 'core/group',   array( 'name' => 'lsv-kachel',      'label' => 'LSV Kachel' ) );
	register_block_style( 'core/columns', array( 'name' => 'lsv-ohne-karten', 'label' => 'Ohne Karten' ) );
	register_block_style( 'core/image',   array( 'name' => 'lsv-karte',       'label' => 'LSV Karte' ) );

	// Kompatibilität mit Twenty Twenty-One.
	$tt1 = array(
		'core/cover'     => array( 'twentytwentyone-border' => 'Rahmen' ),
		'core/image'     => array( 'twentytwentyone-border' => 'Rahmen', 'twentytwentyone-image-frame' => 'Bilderrahmen' ),
		'core/gallery'   => array( 'twentytwentyone-border' => 'Rahmen' ),
		'core/media-text'=> array( 'twentytwentyone-border' => 'Rahmen' ),
		'core/group'     => array( 'twentytwentyone-border' => 'Rahmen' ),
		'core/columns'   => array( 'twentytwentyone-columns-overlap' => 'Überlappend' ),
		'core/latest-posts' => array( 'twentytwentyone-latest-posts-borders' => 'Rahmen', 'twentytwentyone-latest-posts-dividers' => 'Trennlinien' ),
		'core/separator' => array( 'twentytwentyone-separator-thick' => 'Dick' ),
		'core/social-links' => array( 'twentytwentyone-social-icons-color' => 'Einfarbig' ),
		'core/heading'   => array( 'twentytwentyone-heading-default' => 'Standard' ),
	);
	foreach ( $tt1 as $block => $stile ) {
		foreach ( $stile as $name => $label ) {
			register_block_style( $block, array( 'name' => $name, 'label' => $label ) );
		}
	}
}
add_action( 'init', 'lsv_block_styles' );

/* -------------------------------------------------------------------------
 * Customizer: Kopf- und Fußzeile
 * ---------------------------------------------------------------------- */
function lsv_defaults() {
	return array(
		'lsv_kopf_anzeigen' => true,
		'lsv_kopf_kuerzel'  => 'EDMP',
		'lsv_kopf_titel'    => 'Flugplatz Vilsbiburg',
		'lsv_kopf_zeile'    => 'Vilsbiburg Radio 118.060 · RWY 03/21 · 450 m Gras · 1450 ft MSL',
		'lsv_kopf_button'   => 'Wetter',
		'lsv_kopf_link'     => 'https://wetter.lsv-vilsbiburg.de',
		'lsv_kopf_schild'      => true,
		'lsv_kopf_schild_wind' => true,
		'lsv_fuss_text'     => 'Luftsportverein Vilsbiburg e. V.',
		'lsv_fuss_adresse'  => '',
	);
}

function lsv_mod( $key ) {
	$d = lsv_defaults();
	return get_theme_mod( $key, isset( $d[ $key ] ) ? $d[ $key ] : '' );
}

function lsv_customize( $wp_customize ) {
	$d = lsv_defaults();

	$wp_customize->add_section( 'lsv_kopf', array( 'title' => 'LSV Kopf- & Fußzeile', 'priority' => 25 ) );

	$felder = array(
		'lsv_kopf_anzeigen' => array( 'Titel neben dem Logo anzeigen', 'checkbox' ),
		'lsv_kopf_kuerzel'  => array( 'Kürzel (fett, z. B. EDMP)', 'text' ),
		'lsv_kopf_titel'    => array( 'Titel', 'text' ),
		'lsv_kopf_zeile'    => array( 'Unterzeile', 'text' ),
		'lsv_kopf_schild'      => array( 'Gelbes Pistenschild rechts oben anzeigen (braucht Plugin „LSV Wetter am Platz“)', 'checkbox' ),
		'lsv_kopf_schild_wind' => array( 'Beim Schild Wind, Gegen- und Seitenwind anzeigen', 'checkbox' ),
		'lsv_kopf_link'     => array( 'Link für Schild bzw. Button rechts oben', 'url' ),
		'lsv_kopf_button'   => array( 'Button-Text, falls kein Schild (leer = kein Button)', 'text' ),
		'lsv_fuss_text'     => array( 'Fußzeile: Text nach ©', 'text' ),
		'lsv_fuss_adresse'  => array( 'Fußzeile: Zusatzzeile (z. B. Adresse)', 'textarea' ),
	);

	foreach ( $felder as $id => $f ) {
		$san = 'sanitize_text_field';
		if ( 'checkbox' === $f[1] ) {
			$san = 'rest_sanitize_boolean';
		} elseif ( 'url' === $f[1] ) {
			$san = 'esc_url_raw';
		} elseif ( 'textarea' === $f[1] ) {
			$san = 'sanitize_textarea_field';
		}
		$wp_customize->add_setting( $id, array( 'default' => $d[ $id ], 'sanitize_callback' => $san ) );
		$wp_customize->add_control( $id, array( 'label' => $f[0], 'section' => 'lsv_kopf', 'type' => $f[1] ) );
	}
}
add_action( 'customize_register', 'lsv_customize' );

/* -------------------------------------------------------------------------
 * Pistenschild in der Kopfzeile
 * ---------------------------------------------------------------------- */
function lsv_kopf_schild_aktiv() {
	return lsv_mod( 'lsv_kopf_schild' ) && lsv_mod( 'lsv_kopf_link' ) && shortcode_exists( 'lsv_landerichtung' );
}

/** Plugin-CSS schon im <head> laden, wenn das Schild in der Kopfzeile steht */
function lsv_kopf_schild_assets() {
	if ( lsv_kopf_schild_aktiv() && wp_style_is( 'lsvw-style', 'registered' ) ) {
		wp_enqueue_style( 'lsvw-style' );
	}
}
add_action( 'wp_enqueue_scripts', 'lsv_kopf_schild_assets', 5 );

/* -------------------------------------------------------------------------
 * Übernahme von Twenty Twenty-One beim Aktivieren:
 * Logo, Menüzuordnung, Hintergrund und Site-Icon werden kopiert,
 * sofern im neuen Theme noch nichts gesetzt ist.
 * ---------------------------------------------------------------------- */
function lsv_uebernahme_tt1() {
	$quellen = array( 'twentytwentyone', 'lsv-wetter' );
	foreach ( $quellen as $quelle ) {
		$alt = get_option( 'theme_mods_' . $quelle );
		if ( ! is_array( $alt ) ) {
			continue;
		}
		foreach ( array( 'custom_logo', 'nav_menu_locations', 'background_image', 'background_repeat', 'background_position_x', 'background_position_y', 'background_size', 'background_attachment' ) as $key ) {
			if ( isset( $alt[ $key ] ) && ! get_theme_mod( $key ) ) {
				set_theme_mod( $key, $alt[ $key ] );
			}
		}
		foreach ( array( 'lsv_kopf_anzeigen', 'lsv_kopf_kuerzel', 'lsv_kopf_titel', 'lsv_kopf_zeile', 'lsv_fuss_text' ) as $key ) {
			if ( isset( $alt[ $key ] ) && null === get_theme_mod( $key, null ) ) {
				set_theme_mod( $key, $alt[ $key ] );
			}
		}
	}
}
add_action( 'after_switch_theme', 'lsv_uebernahme_tt1' );

/* -------------------------------------------------------------------------
 * Hilfsfunktionen für Templates
 * ---------------------------------------------------------------------- */
function lsv_body_class( $classes ) {
	$classes[] = 'lsv';
	if ( is_singular() ) {
		$classes[] = 'singular';
	}
	return $classes;
}
add_filter( 'body_class', 'lsv_body_class' );

/** Datum + Kategorie unter einem Beitrag */
function lsv_beitrag_meta() {
	echo '<div class="lsv-meta">';
	printf(
		'<time datetime="%1$s">%2$s</time>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() )
	);
	$kats = get_the_category_list( ', ' );
	if ( $kats && 'post' === get_post_type() ) {
		echo ' · <span class="lsv-kategorien">' . $kats . '</span>'; // phpcs:ignore
	}
	echo '</div>';
}

/** Weiterlesen-Link */
function lsv_excerpt_more() {
	return ' …';
}
add_filter( 'excerpt_more', 'lsv_excerpt_more' );

/** Seitennavigation für Archive */
function lsv_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => '← Neuere',
			'next_text' => 'Ältere →',
		)
	);
}

/** Pfeil-Symbol für Untermenüs (wird vom JS genutzt) */
function lsv_menu_arrow( $output, $item, $depth, $args ) {
	if ( isset( $args->theme_location ) && 'primary' === $args->theme_location && in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
		$output .= '<button class="lsv-sub-toggle" aria-expanded="false" aria-label="Untermenü öffnen"><span aria-hidden="true"></span></button>';
	}
	return $output;
}
add_filter( 'walker_nav_menu_start_el', 'lsv_menu_arrow', 10, 4 );
