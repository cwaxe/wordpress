<?php
/**
 * Suchformular
 *
 * @package LSV_Flugplatz
 */

$lsv_id = wp_unique_id( 'suche-' );
?>
<form role="search" method="get" class="suchformular" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $lsv_id ); ?>" class="screen-reader-text">Suchen nach</label>
	<input type="search" id="<?php echo esc_attr( $lsv_id ); ?>" class="suchfeld" value="<?php echo get_search_query(); ?>" name="s" placeholder="Suchen …">
	<button type="submit" class="such-knopf">Suchen</button>
</form>
