<?php
/**
 * Kopfbereich
 *
 * @package LSV_Flugplatz
 */

$lsv_tag = ( is_front_page() || is_home() ) ? 'h1' : 'p';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#486d97">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#content">Zum Inhalt springen</a>

	<header id="masthead" class="kopf">
		<div class="kopf-innen">

			<div class="kopf-marke">
				<?php if ( has_custom_logo() ) : ?>
					<div class="logo"><?php the_custom_logo(); ?></div>
				<?php endif; ?>

				<?php if ( lsv_mod( 'lsv_kopf_anzeigen' ) || ! has_custom_logo() ) : ?>
					<div class="titel">
						<<?php echo $lsv_tag; // phpcs:ignore ?> class="titel-h">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
								<?php if ( lsv_mod( 'lsv_kopf_kuerzel' ) ) : ?>
									<strong><?php echo esc_html( lsv_mod( 'lsv_kopf_kuerzel' ) ); ?></strong>
								<?php endif; ?>
								<span class="titel-name"><?php echo esc_html( lsv_mod( 'lsv_kopf_titel' ) ? lsv_mod( 'lsv_kopf_titel' ) : get_bloginfo( 'name' ) ); ?></span>
							</a>
						</<?php echo $lsv_tag; // phpcs:ignore ?>>
						<?php if ( lsv_mod( 'lsv_kopf_zeile' ) ) : ?>
							<p class="titel-zeile"><?php echo esc_html( lsv_mod( 'lsv_kopf_zeile' ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( has_nav_menu( 'primary' ) ) : ?>
				<button class="menue-knopf" aria-controls="hauptmenue" aria-expanded="false">
					<span class="menue-icon" aria-hidden="true"><span></span></span>
					<span class="menue-text">Menü</span>
				</button>
				<nav id="hauptmenue" class="hauptmenue" aria-label="Hauptmenü">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'primary',
							'menu_class'     => 'menue',
							'container'      => false,
							'depth'          => 3,
							'fallback_cb'    => false,
						)
					);
					?>
				</nav>
			<?php endif; ?>

			<?php if ( lsv_kopf_schild_aktiv() ) : ?>
				<a class="kopf-schild" href="<?php echo esc_url( lsv_mod( 'lsv_kopf_link' ) ); ?>" title="Aktuelles Wetter am Platz">
					<?php echo do_shortcode( lsv_mod( 'lsv_kopf_schild_wind' ) ? '[lsv_landerichtung wind="ja"]' : '[lsv_landerichtung]' ); ?>
				</a>
			<?php elseif ( lsv_mod( 'lsv_kopf_button' ) && lsv_mod( 'lsv_kopf_link' ) ) : ?>
				<a class="kopf-button" href="<?php echo esc_url( lsv_mod( 'lsv_kopf_link' ) ); ?>"><?php echo esc_html( lsv_mod( 'lsv_kopf_button' ) ); ?></a>
			<?php endif; ?>

		</div>
	</header>

	<div id="content" class="inhalt-rahmen">
		<main id="main" class="inhalt">
