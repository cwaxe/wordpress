<?php
/**
 * Fußbereich
 *
 * @package LSV_Flugplatz
 */

?>
		</main><!-- #main -->
	</div><!-- #content -->

	<footer id="colophon" class="fuss">
		<div class="fuss-innen">

			<?php if ( is_active_sidebar( 'sidebar-1' ) ) : ?>
				<div class="fuss-widgets">
					<?php dynamic_sidebar( 'sidebar-1' ); ?>
				</div>
			<?php endif; ?>

			<div class="fuss-zeile">
				<div class="fuss-marke">
					<?php if ( has_custom_logo() ) : ?>
						<div class="fuss-logo"><?php the_custom_logo(); ?></div>
					<?php endif; ?>
					<div>
						<div class="fuss-copy">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( lsv_mod( 'lsv_fuss_text' ) ); ?></div>
						<?php if ( lsv_mod( 'lsv_fuss_adresse' ) ) : ?>
							<div class="fuss-adresse"><?php echo nl2br( esc_html( lsv_mod( 'lsv_fuss_adresse' ) ) ); ?></div>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( has_nav_menu( 'footer' ) ) : ?>
					<nav class="fuss-menue" aria-label="Fußzeilen-Menü">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'footer',
								'container'      => false,
								'depth'          => 1,
								'fallback_cb'    => false,
							)
						);
						?>
					</nav>
				<?php endif; ?>

				<?php
				if ( function_exists( 'the_privacy_policy_link' ) && ! has_nav_menu( 'footer' ) ) {
					the_privacy_policy_link( '<div class="fuss-datenschutz">', '</div>' );
				}
				?>
			</div>

		</div>
	</footer>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
