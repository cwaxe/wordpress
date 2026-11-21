<?php
/**
 * Header
 *
 * @package Dunkel_Schlicht
 */
?><!doctype html>
<html <?php language_attributes(); ?> data-scheme="<?php echo esc_attr( dunkel_color_scheme() ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="dark light">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#inhalt"><?php esc_html_e( 'Zum Inhalt springen', 'wx-easy-template' ); ?></a>

<header class="site-header">
	<div class="header-inner">

		<button class="menu-toggle" aria-controls="hauptmenue" aria-expanded="false">
			<span></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Menü', 'wx-easy-template' ); ?></span>
		</button>

		<nav id="hauptmenue" class="main-navigation" aria-label="<?php esc_attr_e( 'Hauptmenü', 'wx-easy-template' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'depth'          => 3,
					'fallback_cb'    => 'dunkel_primary_fallback',
				)
			);
			?>
		</nav>

		<?php $dunkel_logo_light = (int) get_theme_mod( 'dunkel_logo_light', 0 ); ?>
		<div class="site-branding<?php echo ( $dunkel_logo_light && has_custom_logo() ) ? ' has-light-logo' : ''; ?>">
			<?php dunkel_mode_toggle(); ?>
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
				<?php if ( $dunkel_logo_light ) : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="custom-logo-link logo-light-link" rel="home">
						<?php
						echo wp_get_attachment_image(
							$dunkel_logo_light,
							'full',
							false,
							array(
								'class' => 'custom-logo',
								'alt'   => get_bloginfo( 'name', 'display' ),
							)
						);
						?>
					</a>
				<?php endif; ?>
			<?php else : ?>
				<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
			<?php endif; ?>
		</div>

	</div>
</header>

<main id="inhalt" class="site-main">
