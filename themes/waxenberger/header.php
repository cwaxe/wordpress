<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Zum Inhalt springen', 'waxenberger' ); ?></a>

<header class="website-header" id="top">
	<div class="wrapper-website">
		<div class="logo-module">
			<?php waxenberger_logo(); ?>
		</div>

		<nav class="main-navigation" id="site-navigation" aria-label="<?php esc_attr_e( 'Hauptmenü', 'waxenberger' ); ?>">
			<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
				<span class="bars" aria-hidden="true"></span><?php esc_html_e( 'Menü', 'waxenberger' ); ?>
			</button>
			<?php
			wp_nav_menu(
				array(
					'theme_location'  => 'primary',
					'menu_id'         => 'primary-menu',
					'container'       => 'div',
					'container_class' => 'menu-wrap',
					'fallback_cb'     => 'waxenberger_fallback_menu',
				)
			);
			?>
		</nav>
	</div>
</header>

<main class="website-content" id="content">
	<div class="wrapper-website">
