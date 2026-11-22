<?php
/**
 * Updates direkt aus dem GitHub-Repository (Releases „wx-easy-template-vX.Y.Z“).
 *
 * WordPress zeigt neue Versionen unter Dashboard → Aktualisierungen und bei
 * Design → Themes an; dort lassen sich auch automatische Updates einschalten.
 *
 * @package Dunkel_Schlicht
 */

namespace DunkelSchlicht\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const REPO      = 'cwaxe/wordpress';
const PAKET     = 'wx-easy-template';
const CACHE     = 'dunkel_update_info';
const CACHE_ZEIT = 6 * HOUR_IN_SECONDS;

/** Ordnername dieses Themes (kann bei alten Installationen abweichen). */
function ordner() {
	return get_template();
}

/** Repo-Adresse für Links. */
function repo_url() {
	return 'https://github.com/' . REPO;
}

/**
 * Neuestes Release dieses Themes von GitHub holen.
 * Rückgabe: array( version, paket, url, notes ) oder null.
 */
function neuestes_release( $neu_laden = false ) {
	$info = $neu_laden ? false : get_site_transient( CACHE );
	if ( false !== $info ) {
		return $info ? $info : null;
	}

	$antwort = wp_remote_get(
		'https://api.github.com/repos/' . REPO . '/releases?per_page=100',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
			),
		)
	);

	$info = array();
	if ( ! is_wp_error( $antwort ) && 200 === (int) wp_remote_retrieve_response_code( $antwort ) ) {
		$releases = json_decode( wp_remote_retrieve_body( $antwort ), true );
		foreach ( is_array( $releases ) ? $releases : array() as $r ) {
			if ( ! empty( $r['draft'] ) || ! empty( $r['prerelease'] ) || empty( $r['tag_name'] ) ) {
				continue;
			}
			if ( ! preg_match( '/^' . preg_quote( PAKET, '/' ) . '-v(\d+\.\d+\.\d+)$/', $r['tag_name'], $m ) ) {
				continue;
			}
			$zip = '';
			foreach ( (array) $r['assets'] as $a ) {
				if ( isset( $a['name'], $a['browser_download_url'] ) && PAKET . '-' . $m[1] . '.zip' === $a['name'] ) {
					$zip = $a['browser_download_url'];
				}
			}
			if ( $zip && ( empty( $info ) || version_compare( $m[1], $info['version'], '>' ) ) ) {
				$info = array(
					'version' => $m[1],
					'paket'   => $zip,
					'url'     => isset( $r['html_url'] ) ? $r['html_url'] : repo_url() . '/releases',
					'notes'   => isset( $r['body'] ) ? (string) $r['body'] : '',
				);
			}
		}
		set_site_transient( CACHE, $info, CACHE_ZEIT );
	} else {
		// Bei Fehlern (z. B. GitHub nicht erreichbar) eine Stunde später erneut versuchen.
		set_site_transient( CACHE, array(), HOUR_IN_SECONDS );
	}
	return $info ? $info : null;
}

/** Neue Version in die Update-Liste von WordPress eintragen. */
function update_eintragen( $transient ) {
	if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
		return $transient;
	}
	$ordner = ordner();
	$jetzt  = wp_get_theme( $ordner )->get( 'Version' );
	// „Erneut prüfen“ unter Dashboard → Aktualisierungen fragt GitHub sofort neu.
	$neu_laden = is_admin() && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$info      = neuestes_release( $neu_laden );

	$eintrag = array(
		'theme'        => $ordner,
		'new_version'  => $info ? $info['version'] : $jetzt,
		'url'          => $info ? $info['url'] : repo_url(),
		'package'      => $info ? $info['paket'] : '',
		'requires'     => '6.2',
		'requires_php' => '7.4',
	);

	if ( $info && version_compare( $info['version'], $jetzt, '>' ) ) {
		$transient->response[ $ordner ] = $eintrag;
		unset( $transient->no_update[ $ordner ] );
	} else {
		// Ohne Eintrag bietet WordPress keinen Schalter für automatische Updates an.
		$transient->no_update[ $ordner ] = $eintrag;
		unset( $transient->response[ $ordner ] );
	}
	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', __NAMESPACE__ . '\\update_eintragen' );

/**
 * Das ZIP enthält den Ordner „wx-easy-template“. Ist das Theme in einem anderen
 * Ordner installiert, wird der entpackte Ordner passend umbenannt, damit das
 * Update das bestehende Theme ersetzt statt ein zweites anzulegen.
 */
function ordner_anpassen( $quelle, $entpackt, $upgrader, $extra = array() ) {
	global $wp_filesystem;
	if ( empty( $extra['theme'] ) || ordner() !== $extra['theme'] || ! $wp_filesystem ) {
		return $quelle;
	}
	$ziel = trailingslashit( $entpackt ) . $extra['theme'] . '/';
	if ( untrailingslashit( $quelle ) === untrailingslashit( $ziel ) ) {
		return $quelle;
	}
	if ( basename( untrailingslashit( $quelle ) ) !== PAKET ) {
		return $quelle;
	}
	return $wp_filesystem->move( $quelle, $ziel, true ) ? $ziel : $quelle;
}
add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\\ordner_anpassen', 10, 4 );

/** Zwischenspeicher nach einem Update leeren. */
function nach_update( $upgrader, $optionen ) {
	if ( isset( $optionen['type'] ) && 'theme' === $optionen['type'] ) {
		delete_site_transient( CACHE );
	}
}
add_action( 'upgrader_process_complete', __NAMESPACE__ . '\\nach_update', 10, 2 );

/** „Details ansehen“ im Update-Hinweis: Änderungen aus dem Release anzeigen. */
function theme_details( $antwort, $aktion, $args ) {
	if ( 'theme_information' !== $aktion || empty( $args->slug ) || ordner() !== $args->slug ) {
		return $antwort;
	}
	$info  = neuestes_release();
	$theme = wp_get_theme( ordner() );
	if ( ! $info ) {
		return $antwort;
	}
	return (object) array(
		'name'          => $theme->get( 'Name' ),
		'slug'          => ordner(),
		'version'       => $info['version'],
		'author'        => $theme->get( 'Author' ),
		'homepage'      => repo_url(),
		'download_link' => $info['paket'],
		'requires'      => '6.2',
		'requires_php'  => '7.4',
		'sections'      => array(
			'description' => esc_html( $theme->get( 'Description' ) ),
			'changelog'   => '<pre>' . esc_html( $info['notes'] ) . '</pre>',
		),
	);
}
add_filter( 'themes_api', __NAMESPACE__ . '\\theme_details', 10, 3 );
