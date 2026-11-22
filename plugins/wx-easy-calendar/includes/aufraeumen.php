<?php
/**
 * Aufräumen: alte Termine in den Papierkorb verschieben oder endgültig löschen –
 * von Hand oder automatisch (täglich).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EK_AUFRAEUMEN_RECHT', 'delete_others_posts' );

function ek_aufraeumen_zeitraeume() {
	return array( 1, 3, 6, 12, 24 );
}

/** Stichtag: Termine, deren letzter Tag davor liegt, gelten als alt. */
function ek_aufraeumen_stichtag( $monate ) {
	return current_datetime()->modify( '-' . (int) $monate . ' months' )->format( 'Y-m-d' );
}

/**
 * Alte Termine (bei Serien zählt das Ende der Serie).
 *
 * @return int[]|int IDs oder – mit $zaehlen – die Anzahl.
 */
function ek_alte_termine( $monate, $limit = -1, $zaehlen = false ) {
	$q = new WP_Query(
		array(
			'post_type'              => 'ek_termin',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => $zaehlen ? 1 : $limit,
			'fields'                 => 'ids',
			'no_found_rows'          => ! $zaehlen,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_ek_letzter',
					'value'   => ek_aufraeumen_stichtag( $monate ),
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
		)
	);
	return $zaehlen ? (int) $q->found_posts : array_map( 'intval', $q->posts );
}

/** @return array{erledigt:int,uebrig:int} */
function ek_aufraeumen( $monate, $endgueltig ) {
	$ids      = ek_alte_termine( $monate, 300 );
	$erledigt = 0;
	foreach ( $ids as $id ) {
		if ( $endgueltig ? wp_delete_post( $id, true ) : wp_trash_post( $id ) ) {
			$erledigt++;
		}
	}
	return array(
		'erledigt' => $erledigt,
		'uebrig'   => count( $ids ) >= 300 ? ek_alte_termine( $monate, -1, true ) : 0,
	);
}

/* Täglicher automatischer Lauf: immer nur in den Papierkorb. */
function ek_automatisch_aufraeumen() {
	$monate = (int) get_option( 'ek_auto_aufraeumen', 0 );
	if ( $monate > 0 ) {
		ek_aufraeumen( $monate, false );
	}
}
add_action( 'ek_taeglich_aufraeumen', 'ek_automatisch_aufraeumen' );

function ek_aufraeumen_planen() {
	$an     = (int) get_option( 'ek_auto_aufraeumen', 0 ) > 0;
	$geplant = wp_next_scheduled( 'ek_taeglich_aufraeumen' );
	if ( $an && ! $geplant ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ek_taeglich_aufraeumen' );
	} elseif ( ! $an && $geplant ) {
		wp_clear_scheduled_hook( 'ek_taeglich_aufraeumen' );
	}
}
add_action( 'init', 'ek_aufraeumen_planen' );

function ek_aufraeumen_menue() {
	add_submenu_page( 'edit.php?post_type=ek_termin', 'Alte Termine aufräumen', 'Aufräumen', EK_AUFRAEUMEN_RECHT, 'ek-aufraeumen', 'ek_aufraeumen_seite' );
}
add_action( 'admin_menu', 'ek_aufraeumen_menue', 15 );

function ek_zeitraum_text( $monate ) {
	if ( 1 === $monate ) {
		return 'älter als 1 Monat';
	}
	if ( 12 === $monate ) {
		return 'älter als 1 Jahr';
	}
	return 0 === $monate % 12 ? 'älter als ' . ( $monate / 12 ) . ' Jahre' : 'älter als ' . $monate . ' Monate';
}

function ek_aufraeumen_seite() {
	if ( ! current_user_can( EK_AUFRAEUMEN_RECHT ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$erledigt = isset( $_GET['ek_erledigt'] ) ? absint( $_GET['ek_erledigt'] ) : null;
	$uebrig   = isset( $_GET['ek_uebrig'] ) ? absint( $_GET['ek_uebrig'] ) : 0;
	$korb     = isset( $_GET['ek_art'] ) && 'korb' === $_GET['ek_art'];
	$gesp     = isset( $_GET['ek_gespeichert'] );
	// phpcs:enable

	$auto   = (int) get_option( 'ek_auto_aufraeumen', 0 );
	$papier = admin_url( 'edit.php?post_status=trash&post_type=ek_termin' );
	$anzahl = array();
	foreach ( ek_aufraeumen_zeitraeume() as $m ) {
		$anzahl[ $m ] = ek_alte_termine( $m, -1, true );
	}
	?>
	<div class="wrap ek-einstellungen">
		<h1>Alte Termine aufräumen</h1>

		<?php if ( null !== $erledigt ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						$korb
							? sprintf( 1 === $erledigt ? '%d Termin wurde in den Papierkorb verschoben.' : '%d Termine wurden in den Papierkorb verschoben.', $erledigt )
							: sprintf( 1 === $erledigt ? '%d Termin wurde endgültig gelöscht.' : '%d Termine wurden endgültig gelöscht.', $erledigt )
					);
					?>
					<?php if ( $korb && $erledigt ) : ?>
						<a href="<?php echo esc_url( $papier ); ?>">Zum Papierkorb (dort lässt sich alles wiederherstellen)</a>
					<?php endif; ?>
				</p>
				<?php if ( $uebrig ) : ?>
					<p><strong><?php echo esc_html( sprintf( 'Es sind noch %d weitere alte Termine übrig – bitte noch einmal ausführen.', $uebrig ) ); ?></strong></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $gesp ) : ?>
			<div class="notice notice-success is-dismissible"><p>Einstellung gespeichert.</p></div>
		<?php endif; ?>

		<div class="ek-karte">
			<h2>Jetzt aufräumen</h2>
			<?php if ( ! $anzahl[1] ) : ?>
				<p>Es gibt keine Termine, die älter als einen Monat sind.</p>
			<?php else : ?>
				<p>Entfernt Termine, die vorbei sind. Bei Serien zählt das Ende der Serie – eine laufende Serie bleibt erhalten. Kategorien, Orte, Veranstalter und Bilder in der Mediathek bleiben unverändert.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ek-aufraeumen-form">
					<input type="hidden" name="action" value="ek_aufraeumen">
					<?php wp_nonce_field( 'ek_aufraeumen' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="ek-alter">Termine</label></th>
							<td>
								<select id="ek-alter" name="monate">
									<?php foreach ( ek_aufraeumen_zeitraeume() as $m ) : ?>
										<option value="<?php echo esc_attr( $m ); ?>" data-anzahl="<?php echo esc_attr( $anzahl[ $m ] ); ?>" <?php selected( 12, $m ); ?> <?php disabled( 0, $anzahl[ $m ] ); ?>><?php echo esc_html( ek_zeitraum_text( $m ) . ' (' . $anzahl[ $m ] . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html( 'Zum Beispiel „älter als 1 Jahr“: alle Termine, die vor dem ' . ek_datum_ausgeben( ek_aufraeumen_stichtag( 12 ), 'j. F Y' ) . ' geendet haben.' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">Wie</th>
							<td>
								<fieldset>
									<label style="display:block;margin-bottom:6px"><input type="radio" name="art" value="korb" checked> In den Papierkorb verschieben (empfohlen – lässt sich 30 Tage lang wiederherstellen)</label>
									<label style="display:block"><input type="radio" name="art" value="loeschen"> Endgültig löschen (nicht rückgängig zu machen)</label>
								</fieldset>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Alte Termine entfernen', 'primary', 'submit', false ); ?>
				</form>
				<script>
				( function () {
					var f = document.getElementById( 'ek-aufraeumen-form' );
					f.addEventListener( 'submit', function ( e ) {
						var n = f.monate.options[ f.monate.selectedIndex ].getAttribute( 'data-anzahl' );
						var msg = f.art.value === 'loeschen'
							? n + ' Termine ENDGÜLTIG löschen? Das lässt sich nicht rückgängig machen.'
							: n + ' Termine in den Papierkorb verschieben?';
						if ( ! window.confirm( msg ) ) {
							e.preventDefault();
						}
					} );
				}() );
				</script>
			<?php endif; ?>
		</div>

		<div class="ek-karte">
			<h2>Automatisch aufräumen</h2>
			<p>Einmal täglich werden alte Termine in den Papierkorb verschoben. WordPress leert den Papierkorb nach 30 Tagen von selbst.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ek_aufraeumen_auto">
				<?php wp_nonce_field( 'ek_aufraeumen_auto' ); ?>
				<label for="ek-auto">Termine automatisch entfernen, wenn sie</label>
				<select id="ek-auto" name="monate">
					<option value="0" <?php selected( $auto, 0 ); ?>>nie (aus)</option>
					<?php foreach ( array( 3, 6, 12, 24 ) as $m ) : ?>
						<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $auto, $m ); ?>><?php echo esc_html( ek_zeitraum_text( $m ) ); ?></option>
					<?php endforeach; ?>
				</select>
				sind.
				<?php submit_button( 'Speichern', 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<p><a href="<?php echo esc_url( $papier ); ?>">Papierkorb der Termine ansehen</a></p>
	</div>
	<?php
}

function ek_aufraeumen_ausfuehren() {
	if ( ! current_user_can( EK_AUFRAEUMEN_RECHT ) ) {
		wp_die( 'Keine Berechtigung.' );
	}
	check_admin_referer( 'ek_aufraeumen' );
	$monate     = isset( $_POST['monate'] ) ? absint( $_POST['monate'] ) : 12;
	$monate     = in_array( $monate, ek_aufraeumen_zeitraeume(), true ) ? $monate : 12;
	$endgueltig = isset( $_POST['art'] ) && 'loeschen' === $_POST['art'];
	$ergebnis   = ek_aufraeumen( $monate, $endgueltig );

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type'   => 'ek_termin',
				'page'        => 'ek-aufraeumen',
				'ek_erledigt' => $ergebnis['erledigt'],
				'ek_uebrig'   => $ergebnis['uebrig'],
				'ek_art'      => $endgueltig ? 'loeschen' : 'korb',
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_post_ek_aufraeumen', 'ek_aufraeumen_ausfuehren' );

function ek_aufraeumen_auto_speichern() {
	if ( ! current_user_can( EK_AUFRAEUMEN_RECHT ) ) {
		wp_die( 'Keine Berechtigung.' );
	}
	check_admin_referer( 'ek_aufraeumen_auto' );
	$monate = isset( $_POST['monate'] ) ? absint( $_POST['monate'] ) : 0;
	update_option( 'ek_auto_aufraeumen', in_array( $monate, array( 0, 3, 6, 12, 24 ), true ) ? $monate : 0, false );
	ek_aufraeumen_planen();
	wp_safe_redirect( admin_url( 'edit.php?post_type=ek_termin&page=ek-aufraeumen&ek_gespeichert=1' ) );
	exit;
}
add_action( 'admin_post_ek_aufraeumen_auto', 'ek_aufraeumen_auto_speichern' );
