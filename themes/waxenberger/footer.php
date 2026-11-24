	</div>
</main>

<footer class="js-copyright">
	<div class="wrapper-website">
		<?php echo wp_kses_post( get_theme_mod( 'waxenberger_footer_text', '© ' . date( 'Y' ) . ' IT-Service Christian Waxenberger' ) ); ?>
	</div>
</footer>

<a href="#top" class="back-to-top" aria-label="<?php esc_attr_e( 'Nach oben', 'waxenberger' ); ?>"><svg viewBox="0 0 28 28" aria-hidden="true"><circle cx="14" cy="14" r="13" fill="currentColor"/><path d="M14 6.5 6.8 13.7l2.1 2.1 3.6-3.6V21.5h3V12.2l3.6 3.6 2.1-2.1z" fill="#fff"/></svg></a>

<?php wp_footer(); ?>
</body>
</html>
