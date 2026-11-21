<?php
/**
 * Kommentare
 *
 * @package LSV_Flugplatz
 */

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="kommentare lsv-karte">
	<?php if ( have_comments() ) : ?>
		<h2 class="kommentare-titel">
			<?php
			$anzahl = get_comments_number();
			echo esc_html( 1 === (int) $anzahl ? '1 Kommentar' : $anzahl . ' Kommentare' );
			?>
		</h2>
		<ol class="kommentar-liste">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="kommentare-zu">Kommentare sind geschlossen.</p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'        => 'Kommentar schreiben',
			'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply_after'  => '</h2>',
			'label_submit'       => 'Kommentar senden',
		)
	);
	?>
</section>
