<?php
/**
 * Aufräumen: alte Termine in den Papierkorb verschieben oder endgültig löschen,
 * von Hand oder automatisch (täglich).
 */

defined( 'ABSPATH' ) || exit;

class TK_Cleanup {

	const CRON     = 'tk_daily_cleanup';
	const OPTION   = 'tk_auto_cleanup'; // Monate, 0 = aus.
	const CAP      = 'delete_others_posts';
	const BATCH    = 300;
	const STATUSES = [ 'publish', 'future', 'draft', 'pending', 'private' ];

	/**
	 * Wählbare Zeiträume in Monaten.
	 *
	 * @var int[]
	 */
	private static $ages = [ 1, 3, 6, 12, 24 ];

	public static function init() {
		add_action( self::CRON, [ __CLASS__, 'run_auto' ] );
		add_action( 'init', [ __CLASS__, 'schedule' ] );

		if ( is_admin() ) {
			add_action( 'admin_menu', [ __CLASS__, 'menu' ], 15 );
			add_action( 'admin_post_tk_cleanup', [ __CLASS__, 'handle' ] );
			add_action( 'admin_post_tk_cleanup_auto', [ __CLASS__, 'handle_auto' ] );
		}
	}

	/**
	 * Täglichen Lauf einplanen bzw. entfernen.
	 */
	public static function schedule() {
		$on   = (int) get_option( self::OPTION, 0 ) > 0;
		$next = wp_next_scheduled( self::CRON );
		if ( $on && ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		} elseif ( ! $on && $next ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Stichtag: Termine, deren letzter Tag davor liegt, gelten als alt.
	 *
	 * @param int $months Monate.
	 * @return string Y-m-d
	 */
	public static function cutoff( $months ) {
		return current_datetime()->modify( '-' . (int) $months . ' months' )->format( 'Y-m-d' );
	}

	/**
	 * Alte Termine (bei Serien zählt das Ende der Serie).
	 *
	 * @param int  $months Älter als … Monate.
	 * @param int  $limit  Höchstzahl, -1 = alle.
	 * @param bool $count  Nur zählen.
	 * @return int[]|int
	 */
	public static function old( $months, $limit = -1, $count = false ) {
		$q = new WP_Query(
			[
				'post_type'              => TK_Model::CPT,
				'post_status'            => self::STATUSES,
				'posts_per_page'         => $count ? 1 : $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => ! $count,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery
					[
						'key'     => TK_Model::META_LAST,
						'value'   => self::cutoff( $months ),
						'compare' => '<',
					],
				],
			]
		);

		return $count ? (int) $q->found_posts : array_map( 'intval', $q->posts );
	}

	/**
	 * @param int  $months    Älter als … Monate.
	 * @param bool $permanent Endgültig löschen statt Papierkorb.
	 * @return array{done:int,left:int}
	 */
	public static function run( $months, $permanent ) {
		$ids  = self::old( $months, self::BATCH );
		$done = 0;
		foreach ( $ids as $id ) {
			$ok = $permanent ? wp_delete_post( $id, true ) : wp_trash_post( $id );
			if ( $ok ) {
				$done++;
			}
		}
		return [
			'done' => $done,
			'left' => count( $ids ) >= self::BATCH ? self::old( $months, -1, true ) : 0,
		];
	}

	/**
	 * Automatischer Lauf: immer nur in den Papierkorb (WordPress leert ihn nach 30 Tagen).
	 */
	public static function run_auto() {
		$months = (int) get_option( self::OPTION, 0 );
		if ( $months > 0 ) {
			self::run( $months, false );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Seite „Aufräumen“
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . TK_Model::CPT,
			__( 'Alte Termine aufräumen', 'terminkalender' ),
			__( 'Aufräumen', 'terminkalender' ),
			self::CAP,
			'tk-cleanup',
			[ __CLASS__, 'page' ]
		);
	}

	/**
	 * @param int $months Monate.
	 * @return string
	 */
	private static function age_label( $months ) {
		if ( 1 === $months ) {
			return __( 'älter als 1 Monat', 'terminkalender' );
		}
		if ( 12 === $months ) {
			return __( 'älter als 1 Jahr', 'terminkalender' );
		}
		if ( 0 === $months % 12 ) {
			/* translators: %d: Jahre. */
			return sprintf( __( 'älter als %d Jahre', 'terminkalender' ), $months / 12 );
		}
		/* translators: %d: Monate. */
		return sprintf( __( 'älter als %d Monate', 'terminkalender' ), $months );
	}

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$done      = isset( $_GET['tk_done'] ) ? absint( $_GET['tk_done'] ) : null;
		$left      = isset( $_GET['tk_left'] ) ? absint( $_GET['tk_left'] ) : 0;
		$was_trash = isset( $_GET['tk_mode'] ) && 'trash' === $_GET['tk_mode'];
		$saved     = isset( $_GET['tk_saved'] );
		// phpcs:enable

		$auto  = (int) get_option( self::OPTION, 0 );
		$trash = admin_url( 'edit.php?post_status=trash&post_type=' . TK_Model::CPT );
		$total = 0;
		$count = [];
		foreach ( self::$ages as $m ) {
			$count[ $m ] = self::old( $m, -1, true );
		}
		$total = $count[1];
		?>
		<div class="wrap tk-settings">
			<h1><?php esc_html_e( 'Alte Termine aufräumen', 'terminkalender' ); ?></h1>

			<?php if ( null !== $done ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						echo esc_html(
							$was_trash
								/* translators: %d: Anzahl. */
								? sprintf( _n( '%d Termin wurde in den Papierkorb verschoben.', '%d Termine wurden in den Papierkorb verschoben.', $done, 'terminkalender' ), $done )
								/* translators: %d: Anzahl. */
								: sprintf( _n( '%d Termin wurde endgültig gelöscht.', '%d Termine wurden endgültig gelöscht.', $done, 'terminkalender' ), $done )
						);
						?>
						<?php if ( $was_trash && $done ) : ?>
							<a href="<?php echo esc_url( $trash ); ?>"><?php esc_html_e( 'Zum Papierkorb (dort lässt sich alles wiederherstellen)', 'terminkalender' ); ?></a>
						<?php endif; ?>
					</p>
					<?php if ( $left ) : ?>
						<?php /* translators: %d: Anzahl. */ ?>
						<p><strong><?php echo esc_html( sprintf( __( 'Es sind noch %d weitere alte Termine übrig – bitte noch einmal ausführen.', 'terminkalender' ), $left ) ); ?></strong></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Einstellung gespeichert.', 'terminkalender' ); ?></p></div>
			<?php endif; ?>

			<div class="tk-card">
				<h2><?php esc_html_e( 'Jetzt aufräumen', 'terminkalender' ); ?></h2>
				<?php if ( ! $total ) : ?>
					<p><?php esc_html_e( 'Es gibt keine Termine, die älter als einen Monat sind.', 'terminkalender' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Entfernt Termine, die vorbei sind. Bei Serien zählt das Ende der Serie – eine laufende Serie bleibt erhalten. Kategorien, Orte, Veranstalter und Bilder in der Mediathek bleiben unverändert.', 'terminkalender' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tk-cleanup-form">
						<input type="hidden" name="action" value="tk_cleanup">
						<?php wp_nonce_field( 'tk_cleanup' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="tk-age"><?php esc_html_e( 'Termine', 'terminkalender' ); ?></label></th>
								<td>
									<select id="tk-age" name="months">
										<?php foreach ( self::$ages as $m ) : ?>
											<option value="<?php echo esc_attr( $m ); ?>" data-count="<?php echo esc_attr( $count[ $m ] ); ?>" <?php selected( 12, $m ); ?> <?php disabled( 0, $count[ $m ] ); ?>>
												<?php
												/* translators: 1: Zeitraum, 2: Anzahl. */
												echo esc_html( sprintf( __( '%1$s (%2$d)', 'terminkalender' ), self::age_label( $m ), $count[ $m ] ) );
												?>
											</option>
										<?php endforeach; ?>
									</select>
									<?php /* translators: %s: Datum. */ ?>
									<p class="description"><?php echo esc_html( sprintf( __( 'Zum Beispiel „älter als 1 Jahr“: alle Termine, die vor dem %s geendet haben.', 'terminkalender' ), TK_Model::fmt( 'j. F Y', TK_Model::ts( self::cutoff( 12 ) . ' 00:00' ) ) ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Wie', 'terminkalender' ); ?></th>
								<td>
									<fieldset>
										<label style="display:block;margin-bottom:6px"><input type="radio" name="mode" value="trash" checked> <?php esc_html_e( 'In den Papierkorb verschieben (empfohlen – lässt sich 30 Tage lang wiederherstellen)', 'terminkalender' ); ?></label>
										<label style="display:block"><input type="radio" name="mode" value="delete"> <?php esc_html_e( 'Endgültig löschen (nicht rückgängig zu machen)', 'terminkalender' ); ?></label>
									</fieldset>
								</td>
							</tr>
						</table>
						<?php submit_button( __( 'Alte Termine entfernen', 'terminkalender' ), 'primary', 'submit', false ); ?>
					</form>
					<script>
					( function () {
						var form = document.getElementById( 'tk-cleanup-form' );
						form.addEventListener( 'submit', function ( e ) {
							var opt = form.months.options[ form.months.selectedIndex ];
							var n = opt.getAttribute( 'data-count' );
							var del = form.mode.value === 'delete';
							var msg = del
								? <?php echo wp_json_encode( __( '%d Termine ENDGÜLTIG löschen? Das lässt sich nicht rückgängig machen.', 'terminkalender' ) ); ?>
								: <?php echo wp_json_encode( __( '%d Termine in den Papierkorb verschieben?', 'terminkalender' ) ); ?>;
							if ( ! window.confirm( msg.replace( '%d', n ) ) ) {
								e.preventDefault();
							}
						} );
					}() );
					</script>
				<?php endif; ?>
			</div>

			<div class="tk-card">
				<h2><?php esc_html_e( 'Automatisch aufräumen', 'terminkalender' ); ?></h2>
				<p><?php esc_html_e( 'Einmal täglich werden alte Termine in den Papierkorb verschoben. WordPress leert den Papierkorb nach 30 Tagen von selbst.', 'terminkalender' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tk_cleanup_auto">
					<?php wp_nonce_field( 'tk_cleanup_auto' ); ?>
					<label for="tk-auto"><?php esc_html_e( 'Termine automatisch entfernen, wenn sie', 'terminkalender' ); ?></label>
					<select id="tk-auto" name="months">
						<option value="0" <?php selected( $auto, 0 ); ?>><?php esc_html_e( 'nie (aus)', 'terminkalender' ); ?></option>
						<?php foreach ( [ 3, 6, 12, 24 ] as $m ) : ?>
							<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $auto, $m ); ?>><?php echo esc_html( self::age_label( $m ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php esc_html_e( 'sind.', 'terminkalender' ); ?>
					<?php submit_button( __( 'Speichern', 'terminkalender' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<p><a href="<?php echo esc_url( $trash ); ?>"><?php esc_html_e( 'Papierkorb der Termine ansehen', 'terminkalender' ); ?></a></p>
		</div>
		<?php
	}

	public static function handle() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'terminkalender' ) );
		}
		check_admin_referer( 'tk_cleanup' );

		$months    = isset( $_POST['months'] ) ? absint( $_POST['months'] ) : 12;
		$months    = in_array( $months, self::$ages, true ) ? $months : 12;
		$permanent = isset( $_POST['mode'] ) && 'delete' === $_POST['mode'];

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$result = self::run( $months, $permanent );

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type' => TK_Model::CPT,
					'page'      => 'tk-cleanup',
					'tk_done'   => $result['done'],
					'tk_left'   => $result['left'],
					'tk_mode'   => $permanent ? 'delete' : 'trash',
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	public static function handle_auto() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'terminkalender' ) );
		}
		check_admin_referer( 'tk_cleanup_auto' );

		$months = isset( $_POST['months'] ) ? absint( $_POST['months'] ) : 0;
		$months = in_array( $months, [ 0, 3, 6, 12, 24 ], true ) ? $months : 0;
		update_option( self::OPTION, $months, false );
		self::schedule();

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . TK_Model::CPT . '&page=tk-cleanup&tk_saved=1' ) );
		exit;
	}
}
