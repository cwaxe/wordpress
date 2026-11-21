<?php
/**
 * Footer
 *
 * @package Dunkel_Schlicht
 */
?>
</main>

<footer class="site-footer">
	<nav class="footer-navigation" aria-label="<?php esc_attr_e( 'Rechtliches', 'dunkel-schlicht' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'menu',
				'depth'          => 1,
				'fallback_cb'    => 'dunkel_footer_fallback',
			)
		);
		?>
	</nav>
	<p class="site-info">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
