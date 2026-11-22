<?php
/**
 * Vereinfachtes Backend: Eingabemaske, Terminliste, Duplizieren, Felder für Orte,
 * Veranstalter und Kategorien, Einstellungsseite.
 */

defined( 'ABSPATH' ) || exit;

class TK_Admin {

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_filter( 'enter_title_here', [ __CLASS__, 'title_placeholder' ], 10, 2 );
		add_action( 'edit_form_after_title', [ __CLASS__, 'render_box' ] );
		add_action( 'save_post_' . TK_Model::CPT, [ __CLASS__, 'save' ], 10, 2 );
		add_action( 'admin_notices', [ __CLASS__, 'notices' ] );
		add_action( 'post_submitbox_misc_actions', [ __CLASS__, 'duplicate_button' ] );

		// Terminliste.
		add_filter( 'manage_' . TK_Model::CPT . '_posts_columns', [ __CLASS__, 'columns' ] );
		add_action( 'manage_' . TK_Model::CPT . '_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
		add_filter( 'manage_edit-' . TK_Model::CPT . '_sortable_columns', [ __CLASS__, 'sortable' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'list_query' ] );
		add_filter( 'views_edit-' . TK_Model::CPT, [ __CLASS__, 'views' ] );
		add_filter( 'post_row_actions', [ __CLASS__, 'row_actions' ], 10, 2 );
		add_action( 'admin_post_tk_duplicate', [ __CLASS__, 'duplicate' ] );
		add_action( 'admin_post_tk_create_page', [ __CLASS__, 'create_page' ] );

		// Felder für Orte, Veranstalter, Kategorien.
		foreach ( self::term_fields() as $tax => $fields ) {
			add_action( $tax . '_add_form_fields', [ __CLASS__, 'term_add_fields' ] );
			add_action( $tax . '_edit_form_fields', [ __CLASS__, 'term_edit_fields' ], 10, 2 );
			add_action( 'created_' . $tax, [ __CLASS__, 'term_save' ], 10, 2 );
			add_action( 'edited_' . $tax, [ __CLASS__, 'term_save' ], 10, 2 );
			add_filter( 'manage_edit-' . $tax . '_columns', [ __CLASS__, 'term_columns' ] );
			add_filter( 'manage_' . $tax . '_custom_column', [ __CLASS__, 'term_column' ], 10, 3 );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Menü und Dateien
	 * ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . TK_Model::CPT,
			__( 'Einstellungen & Import', 'terminkalender' ),
			__( 'Einstellungen', 'terminkalender' ),
			'manage_options',
			'tk-settings',
			[ __CLASS__, 'settings_page' ]
		);
	}

	/**
	 * @param string $hook Seite.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$ours = TK_Model::CPT === $screen->post_type
			|| in_array( $screen->taxonomy, [ TK_Model::TAX_CAT, TK_Model::TAX_PLACE, TK_Model::TAX_ORG ], true );
		if ( ! $ours ) {
			return;
		}

		wp_enqueue_style( 'tk-admin', TK_URL . 'assets/css/admin.css', [], TK_VERSION );

		if ( TK_Model::TAX_CAT === $screen->taxonomy ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'tk-color', TK_URL . 'assets/js/admin.js', [ 'jquery', 'wp-color-picker' ], TK_VERSION, true );
		}

		if ( 'post' === $screen->base || false !== strpos( $hook, 'tk-settings' ) ) {
			global $wp_locale;
			$weekdays = [];
			for ( $d = 0; $d <= 6; $d++ ) {
				$weekdays[] = $wp_locale->get_weekday( $d );
			}
			wp_enqueue_script( 'tk-admin', TK_URL . 'assets/js/admin.js', [ 'jquery' ], TK_VERSION, true );
			wp_localize_script(
				'tk-admin',
				'tkAdmin',
				[
					'weekdays' => $weekdays,
					'ajax'     => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'tk_import' ),
					'i18n'     => [
						'weekly'       => __( 'jede Woche', 'terminkalender' ),
						'weekly2'      => __( 'alle 2 Wochen', 'terminkalender' ),
						'startDay'     => __( 'Tag des Beginns – immer dabei', 'terminkalender' ),
						'monthlyDay'   => __( 'jeden Monat am %d.', 'terminkalender' ),
						'monthlyNth'   => __( 'jeden Monat am %1$d. %2$s', 'terminkalender' ),
						'monthlyLast'  => __( 'jeden Monat am letzten %s', 'terminkalender' ),
						'importing'    => __( 'Übernehme Daten …', 'terminkalender' ),
						'importDone'   => __( 'Fertig! %d Termine wurden übernommen.', 'terminkalender' ),
						'importError'  => __( 'Fehler bei der Übernahme:', 'terminkalender' ),
						'confirm'      => __( 'Jetzt alle Daten aus The Events Calendar übernehmen? Bereits übernommene Termine werden dabei aktualisiert, nicht doppelt angelegt.', 'terminkalender' ),
					],
				]
			);
		}
	}

	/**
	 * @param string  $text Platzhalter.
	 * @param WP_Post $post Beitrag.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		return TK_Model::CPT === $post->post_type ? __( 'Titel des Termins, z. B. „Theater Binabiburg“', 'terminkalender' ) : $text;
	}

	/* ------------------------------------------------------------------ *
	 * Eingabemaske
	 * ------------------------------------------------------------------ */

	/**
	 * Box „Wann, Wo, Wer“ direkt unter dem Titel.
	 *
	 * @param WP_Post $post Termin.
	 */
	public static function render_box( $post ) {
		if ( TK_Model::CPT !== $post->post_type ) {
			return;
		}

		$data   = TK_Model::get( $post->ID );
		$is_new = '' === $data['start'];
		$start  = $is_new ? '' : $data['start'];
		$end    = $is_new ? '' : $data['end'];
		$rule   = $data['rule'];

		$s_date = $start ? substr( $start, 0, 10 ) : '';
		$s_time = $start && ! $data['allday'] ? substr( $start, 11, 5 ) : ( $is_new ? '19:00' : '' );
		$e_date = $end ? substr( $end, 0, 10 ) : '';
		$e_time = $end && ! $data['allday'] ? substr( $end, 11, 5 ) : ( $is_new ? '21:00' : '' );

		$repeat = '';
		if ( $rule ) {
			$repeat = $rule['freq'];
			if ( 'weekly' === $rule['freq'] && 2 === (int) $rule['interval'] ) {
				$repeat = 'weekly2';
			}
		}
		$exdates = $rule ? implode( "\n", array_map( [ __CLASS__, 'de_date' ], $rule['exdates'] ) ) : '';

		$place = TK_Model::first_term( $post->ID, TK_Model::TAX_PLACE );
		$org   = TK_Model::first_term( $post->ID, TK_Model::TAX_ORG );

		$places = get_terms( [ 'taxonomy' => TK_Model::TAX_PLACE, 'hide_empty' => false, 'orderby' => 'name' ] );
		$orgs   = get_terms( [ 'taxonomy' => TK_Model::TAX_ORG, 'hide_empty' => false, 'orderby' => 'name' ] );

		wp_nonce_field( 'tk_save', 'tk_nonce' );
		?>
		<div class="tk-box postbox">
			<div class="tk-box__section">
				<h2 class="tk-box__title"><?php esc_html_e( 'Wann?', 'terminkalender' ); ?></h2>
				<div class="tk-grid">
					<div class="tk-field">
						<label for="tk-start-date"><?php esc_html_e( 'Beginn', 'terminkalender' ); ?></label>
						<div class="tk-inline">
							<input type="date" id="tk-start-date" name="tk[start_date]" value="<?php echo esc_attr( $s_date ); ?>" required>
							<input type="time" id="tk-start-time" name="tk[start_time]" value="<?php echo esc_attr( $s_time ); ?>" class="tk-time" aria-label="<?php esc_attr_e( 'Uhrzeit Beginn', 'terminkalender' ); ?>">
						</div>
					</div>
					<div class="tk-field">
						<label for="tk-end-date"><?php esc_html_e( 'Ende', 'terminkalender' ); ?></label>
						<div class="tk-inline">
							<input type="date" id="tk-end-date" name="tk[end_date]" value="<?php echo esc_attr( $e_date ); ?>">
							<input type="time" id="tk-end-time" name="tk[end_time]" value="<?php echo esc_attr( $e_time ); ?>" class="tk-time" aria-label="<?php esc_attr_e( 'Uhrzeit Ende', 'terminkalender' ); ?>">
						</div>
					</div>
					<div class="tk-field tk-field--check">
						<label><input type="checkbox" id="tk-allday" name="tk[allday]" value="1" <?php checked( $data['allday'] ); ?>> <?php esc_html_e( 'Ganztägig', 'terminkalender' ); ?></label>
					</div>
				</div>

				<div class="tk-grid tk-grid--repeat">
					<div class="tk-field">
						<label for="tk-repeat"><?php esc_html_e( 'Wiederholen', 'terminkalender' ); ?></label>
						<select id="tk-repeat" name="tk[repeat]" data-current="<?php echo esc_attr( $repeat ); ?>">
							<option value=""><?php esc_html_e( 'nicht wiederholen', 'terminkalender' ); ?></option>
							<option value="weekly" <?php selected( $repeat, 'weekly' ); ?>><?php esc_html_e( 'jede Woche', 'terminkalender' ); ?></option>
							<option value="weekly2" <?php selected( $repeat, 'weekly2' ); ?>><?php esc_html_e( 'alle 2 Wochen', 'terminkalender' ); ?></option>
							<option value="monthly_day" <?php selected( $repeat, 'monthly_day' ); ?>><?php esc_html_e( 'jeden Monat am gleichen Tag', 'terminkalender' ); ?></option>
							<option value="monthly_nth" <?php selected( $repeat, 'monthly_nth' ); ?>><?php esc_html_e( 'jeden Monat am gleichen Wochentag', 'terminkalender' ); ?></option>
							<option value="monthly_last" <?php selected( $repeat, 'monthly_last' ); ?>><?php esc_html_e( 'jeden Monat am letzten Wochentag', 'terminkalender' ); ?></option>
						</select>
					</div>
					<fieldset class="tk-field tk-days" <?php echo ( 'weekly' === $repeat || 'weekly2' === $repeat ) ? '' : 'hidden'; ?>>
						<legend><?php esc_html_e( 'an diesen Tagen', 'terminkalender' ); ?></legend>
						<div class="tk-days__list">
							<?php
							global $wp_locale;
							$start_wd = $s_date ? (int) gmdate( 'w', TK_Model::ts( $s_date . ' 00:00' ) ) : -1;
							$sel_days = $rule && 'weekly' === $rule['freq'] ? TK_Model::rule_days( $rule, $start ) : [ $start_wd ];
							foreach ( [ 1, 2, 3, 4, 5, 6, 0 ] as $wd ) :
								$wd_name = $wp_locale->get_weekday( $wd );
								?>
								<label class="tk-day" title="<?php echo esc_attr( $wd_name ); ?>">
									<input type="checkbox" name="tk[days][]" value="<?php echo esc_attr( $wd ); ?>" <?php checked( in_array( $wd, $sel_days, true ) ); ?>>
									<span><?php echo esc_html( $wp_locale->get_weekday_abbrev( $wd_name ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<div class="tk-field tk-series">
						<label for="tk-until"><?php esc_html_e( 'bis einschließlich', 'terminkalender' ); ?></label>
						<input type="date" id="tk-until" name="tk[until]" value="<?php echo esc_attr( $rule ? $rule['until'] : '' ); ?>">
					</div>
					<div class="tk-field tk-series tk-field--wide">
						<label for="tk-exdates"><?php esc_html_e( 'Fällt aus am (ein Datum pro Zeile, z. B. 24.12.2026)', 'terminkalender' ); ?></label>
						<textarea id="tk-exdates" name="tk[exdates]" rows="2"><?php echo esc_textarea( $exdates ); ?></textarea>
					</div>
				</div>
			</div>

			<div class="tk-box__section">
				<h2 class="tk-box__title"><?php esc_html_e( 'Wo?', 'terminkalender' ); ?></h2>
				<div class="tk-field">
					<label for="tk-place"><?php esc_html_e( 'Ort', 'terminkalender' ); ?></label>
					<select id="tk-place" name="tk[place]" class="tk-picker" data-new="tk-place-new">
						<option value=""><?php esc_html_e( '— kein Ort —', 'terminkalender' ); ?></option>
						<?php if ( ! is_wp_error( $places ) ) : ?>
							<?php foreach ( $places as $t ) : ?>
								<?php
								$city = get_term_meta( $t->term_id, 'tk_stadt', true );
								$name = $t->name . ( $city && false === stripos( $t->name, $city ) ? ' (' . $city . ')' : '' );
								?>
								<option value="<?php echo esc_attr( $t->term_id ); ?>" <?php selected( $place && (int) $place->term_id === (int) $t->term_id ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
						<option value="new"><?php esc_html_e( '+ Neuen Ort anlegen …', 'terminkalender' ); ?></option>
					</select>
				</div>
				<div class="tk-new" id="tk-place-new" hidden>
					<div class="tk-grid">
						<div class="tk-field tk-field--wide"><label for="tk-pn-name"><?php esc_html_e( 'Name des Ortes', 'terminkalender' ); ?></label><input type="text" id="tk-pn-name" name="tk[place_new][name]" placeholder="<?php esc_attr_e( 'z. B. Pfarrsaal Binabiburg', 'terminkalender' ); ?>"></div>
						<div class="tk-field tk-field--wide"><label for="tk-pn-street"><?php esc_html_e( 'Straße und Hausnummer', 'terminkalender' ); ?></label><input type="text" id="tk-pn-street" name="tk[place_new][street]"></div>
						<div class="tk-field"><label for="tk-pn-zip"><?php esc_html_e( 'PLZ', 'terminkalender' ); ?></label><input type="text" id="tk-pn-zip" name="tk[place_new][zip]" inputmode="numeric" class="tk-short"></div>
						<div class="tk-field"><label for="tk-pn-city"><?php esc_html_e( 'Ort', 'terminkalender' ); ?></label><input type="text" id="tk-pn-city" name="tk[place_new][city]"></div>
					</div>
				</div>
			</div>

			<div class="tk-box__section">
				<h2 class="tk-box__title"><?php esc_html_e( 'Wer?', 'terminkalender' ); ?></h2>
				<div class="tk-field">
					<label for="tk-org"><?php esc_html_e( 'Veranstalter', 'terminkalender' ); ?></label>
					<select id="tk-org" name="tk[org]" class="tk-picker" data-new="tk-org-new">
						<option value=""><?php esc_html_e( '— kein Veranstalter —', 'terminkalender' ); ?></option>
						<?php if ( ! is_wp_error( $orgs ) ) : ?>
							<?php foreach ( $orgs as $t ) : ?>
								<option value="<?php echo esc_attr( $t->term_id ); ?>" <?php selected( $org && (int) $org->term_id === (int) $t->term_id ); ?>><?php echo esc_html( $t->name ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
						<option value="new"><?php esc_html_e( '+ Neuen Veranstalter anlegen …', 'terminkalender' ); ?></option>
					</select>
				</div>
				<div class="tk-new" id="tk-org-new" hidden>
					<div class="tk-grid">
						<div class="tk-field tk-field--wide"><label for="tk-on-name"><?php esc_html_e( 'Name', 'terminkalender' ); ?></label><input type="text" id="tk-on-name" name="tk[org_new][name]" placeholder="<?php esc_attr_e( 'z. B. Musikgesellschaft Aich', 'terminkalender' ); ?>"></div>
						<div class="tk-field"><label for="tk-on-phone"><?php esc_html_e( 'Telefon', 'terminkalender' ); ?></label><input type="tel" id="tk-on-phone" name="tk[org_new][phone]"></div>
						<div class="tk-field"><label for="tk-on-email"><?php esc_html_e( 'E-Mail', 'terminkalender' ); ?></label><input type="email" id="tk-on-email" name="tk[org_new][email]"></div>
						<div class="tk-field tk-field--wide"><label for="tk-on-web"><?php esc_html_e( 'Webseite', 'terminkalender' ); ?></label><input type="url" id="tk-on-web" name="tk[org_new][web]" placeholder="https://"></div>
					</div>
				</div>
			</div>
		</div>
		<h2 class="tk-desc-title"><?php esc_html_e( 'Beschreibung', 'terminkalender' ); ?></h2>
		<?php
	}

	/**
	 * @param string $ymd Y-m-d.
	 * @return string d.m.Y
	 */
	public static function de_date( $ymd ) {
		$p = explode( '-', $ymd );
		return 3 === count( $p ) ? $p[2] . '.' . $p[1] . '.' . $p[0] : $ymd;
	}

	/**
	 * „24.12.2026“ oder „2026-12-24“ → „2026-12-24“.
	 *
	 * @param string $text Eingabe.
	 * @return string
	 */
	public static function parse_date( $text ) {
		$text = trim( $text );
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] ) : '';
		}
		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $text, $m ) ) {
			$y = (int) $m[3];
			$y = $y < 100 ? 2000 + $y : $y;
			return checkdate( (int) $m[2], (int) $m[1], $y ) ? sprintf( '%04d-%02d-%02d', $y, $m[2], $m[1] ) : '';
		}
		return '';
	}

	/**
	 * @param int     $post_id Termin.
	 * @param WP_Post $post    Termin.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['tk_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tk_nonce'] ) ), 'tk_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Felder werden einzeln geprüft.
		$in = isset( $_POST['tk'] ) && is_array( $_POST['tk'] ) ? wp_unslash( $_POST['tk'] ) : [];

		$allday = ! empty( $in['allday'] );
		$s_date = self::parse_date( isset( $in['start_date'] ) ? (string) $in['start_date'] : '' );
		if ( ! $s_date ) {
			$s_date = current_datetime()->format( 'Y-m-d' );
			set_transient( 'tk_notice_' . get_current_user_id(), 'nodate', 60 );
		}
		$e_date = self::parse_date( isset( $in['end_date'] ) ? (string) $in['end_date'] : '' );
		$e_date = $e_date && $e_date >= $s_date ? $e_date : $s_date;

		$s_time = self::clean_time( isset( $in['start_time'] ) ? $in['start_time'] : '', '00:00' );
		$e_time = self::clean_time( isset( $in['end_time'] ) ? $in['end_time'] : '', $s_time );

		$start = $s_date . ' ' . $s_time;
		$end   = $e_date . ' ' . $e_time;

		// Serie.
		$rule   = null;
		$repeat = isset( $in['repeat'] ) ? (string) $in['repeat'] : '';
		if ( $repeat ) {
			$ex = [];
			foreach ( preg_split( '/[\s,;]+/', isset( $in['exdates'] ) ? (string) $in['exdates'] : '' ) as $line ) {
				$d = self::parse_date( $line );
				if ( $d ) {
					$ex[] = $d;
				}
			}
			$rule = [
				'freq'     => 'weekly2' === $repeat ? 'weekly' : $repeat,
				'interval' => 'weekly2' === $repeat ? 2 : 1,
				'until'    => self::parse_date( isset( $in['until'] ) ? (string) $in['until'] : '' ),
				'exdates'  => $ex,
				'days'     => isset( $in['days'] ) && is_array( $in['days'] ) ? array_map( 'intval', $in['days'] ) : [],
			];
		}

		TK_Model::save( $post_id, $start, $end, $allday, $rule );

		// Ort und Veranstalter.
		self::save_term_choice(
			$post_id,
			TK_Model::TAX_PLACE,
			isset( $in['place'] ) ? (string) $in['place'] : '',
			isset( $in['place_new'] ) && is_array( $in['place_new'] ) ? $in['place_new'] : [],
			[
				'street' => 'tk_strasse',
				'zip'    => 'tk_plz',
				'city'   => 'tk_stadt',
			]
		);
		self::save_term_choice(
			$post_id,
			TK_Model::TAX_ORG,
			isset( $in['org'] ) ? (string) $in['org'] : '',
			isset( $in['org_new'] ) && is_array( $in['org_new'] ) ? $in['org_new'] : [],
			[
				'phone' => 'tk_telefon',
				'email' => 'tk_email',
				'web'   => 'tk_web',
			]
		);
	}

	/**
	 * @param mixed  $value    Eingabe.
	 * @param string $fallback Ersatz.
	 * @return string H:i
	 */
	private static function clean_time( $value, $fallback ) {
		$value = trim( (string) $value );
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
	}

	/**
	 * Ort/Veranstalter: vorhandenen wählen, neu anlegen oder entfernen.
	 *
	 * @param int                  $post_id Termin.
	 * @param string               $tax     Taxonomie.
	 * @param string               $choice  Auswahl.
	 * @param array<string,mixed>  $new     Felder für neuen Eintrag.
	 * @param array<string,string> $map     Feld → Term-Meta.
	 */
	private static function save_term_choice( $post_id, $tax, $choice, $new, $map ) {
		if ( 'new' === $choice ) {
			$name = isset( $new['name'] ) ? sanitize_text_field( $new['name'] ) : '';
			if ( '' === $name ) {
				return;
			}
			$exists = term_exists( $name, $tax );
			if ( $exists ) {
				$term_id = (int) $exists['term_id'];
			} else {
				$created = wp_insert_term( $name, $tax );
				if ( is_wp_error( $created ) ) {
					return;
				}
				$term_id = (int) $created['term_id'];
			}
			foreach ( $map as $field => $meta ) {
				if ( ! empty( $new[ $field ] ) ) {
					update_term_meta( $term_id, $meta, self::clean_meta( $meta, $new[ $field ] ) );
				}
			}
			wp_set_object_terms( $post_id, [ $term_id ], $tax );
			return;
		}

		$id = absint( $choice );
		wp_set_object_terms( $post_id, $id ? [ $id ] : [], $tax );
	}

	/**
	 * @param string $meta  Schlüssel.
	 * @param mixed  $value Wert.
	 * @return string
	 */
	private static function clean_meta( $meta, $value ) {
		$value = (string) $value;
		switch ( $meta ) {
			case 'tk_email':
				return sanitize_email( $value );
			case 'tk_web':
				return esc_url_raw( $value );
			case 'tk_farbe':
				return (string) sanitize_hex_color( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	public static function notices() {
		$key  = 'tk_notice_' . get_current_user_id();
		$note = get_transient( $key );
		if ( 'nodate' === $note ) {
			delete_transient( $key );
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Es war kein Datum angegeben. Der Termin wurde auf heute gesetzt – bitte das Datum prüfen.', 'terminkalender' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tk_duplicated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Kopie angelegt. Bitte Datum anpassen und veröffentlichen.', 'terminkalender' ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Duplizieren
	 * ------------------------------------------------------------------ */

	/**
	 * @param WP_Post $post Termin.
	 */
	public static function duplicate_button( $post ) {
		if ( TK_Model::CPT !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return;
		}
		printf(
			'<div class="misc-pub-section tk-dup"><a class="button" href="%s">%s</a></div>',
			esc_url( self::duplicate_url( $post->ID ) ),
			esc_html__( 'Termin kopieren', 'terminkalender' )
		);
	}

	/**
	 * @param int $post_id Termin.
	 * @return string
	 */
	private static function duplicate_url( $post_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=tk_duplicate&post=' . $post_id ), 'tk_duplicate_' . $post_id );
	}

	/**
	 * @param string[] $actions Aktionen.
	 * @param WP_Post  $post    Beitrag.
	 * @return string[]
	 */
	public static function row_actions( $actions, $post ) {
		if ( TK_Model::CPT === $post->post_type && current_user_can( 'edit_posts' ) ) {
			$actions['tk_duplicate'] = '<a href="' . esc_url( self::duplicate_url( $post->ID ) ) . '">' . esc_html__( 'Kopieren', 'terminkalender' ) . '</a>';
		}
		return $actions;
	}

	public static function duplicate() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'tk_duplicate_' . $id );

		$post = get_post( $id );
		if ( ! $post || TK_Model::CPT !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'terminkalender' ) );
		}

		$new_id = wp_insert_post(
			[
				'post_type'    => TK_Model::CPT,
				'post_status'  => 'draft',
				'post_title'   => $post->post_title,
				'post_content' => $post->post_content,
				'post_author'  => get_current_user_id(),
			],
			true
		);
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		$data = TK_Model::get( $id );
		TK_Model::save( $new_id, $data['start'], $data['end'], $data['allday'], $data['rule'] );

		$thumb = get_post_thumbnail_id( $id );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}
		foreach ( [ TK_Model::TAX_CAT, TK_Model::TAX_PLACE, TK_Model::TAX_ORG ] as $tax ) {
			$ids = wp_get_object_terms( $id, $tax, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $ids ) ) {
				wp_set_object_terms( $new_id, $ids, $tax );
			}
		}

		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id . '&tk_duplicated=1' ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Terminliste
	 * ------------------------------------------------------------------ */

	/**
	 * @param string[] $cols Spalten.
	 * @return string[]
	 */
	public static function columns( $cols ) {
		$new = [];
		foreach ( $cols as $key => $label ) {
			if ( 'date' === $key ) {
				continue; // Veröffentlichungsdatum ist hier unwichtig.
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['tk_when']  = __( 'Datum', 'terminkalender' );
				$new['tk_where'] = __( 'Ort', 'terminkalender' );
			}
		}
		return $new;
	}

	/**
	 * @param string $col     Spalte.
	 * @param int    $post_id Termin.
	 */
	public static function column( $col, $post_id ) {
		if ( 'tk_when' === $col ) {
			$data = TK_Model::get( $post_id );
			if ( ! $data['start'] ) {
				echo '—';
				return;
			}
			$ts = TK_Model::ts( $data['start'] );
			echo esc_html( TK_Model::fmt( 'D, j. M Y', $ts ) );
			if ( ! $data['allday'] ) {
				echo ' · ' . esc_html( substr( $data['start'], 11, 5 ) );
			}
			if ( $data['rule'] ) {
				echo '<br><span class="tk-badge">' . esc_html__( 'Serie', 'terminkalender' ) . '</span> <small>' . esc_html( TK_Model::rule_text( $data ) ) . '</small>';
			}
		} elseif ( 'tk_where' === $col ) {
			$place = TK_Model::place( $post_id );
			echo $place ? esc_html( $place['name'] ) : '—';
		}
	}

	/**
	 * @param string[] $cols Sortierbare Spalten.
	 * @return string[]
	 */
	public static function sortable( $cols ) {
		$cols['tk_when'] = 'tk_when';
		return $cols;
	}

	/**
	 * Kommende/Vergangene Termine und Sortierung nach Datum.
	 *
	 * @param WP_Query $q Abfrage.
	 */
	public static function list_query( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		global $pagenow;
		if ( 'edit.php' !== $pagenow || TK_Model::CPT !== $q->get( 'post_type' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$zeit    = isset( $_GET['tk_zeit'] ) ? sanitize_key( wp_unslash( $_GET['tk_zeit'] ) ) : '';
		$status  = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		// phpcs:enable

		if ( '' === $zeit ) {
			$zeit = $status ? 'alle' : 'kommend';
		}

		$today   = current_datetime()->format( 'Y-m-d' );
		$clauses = [
			'relation' => 'AND',
			'tk_start' => [
				'key'     => TK_Model::META_START,
				'compare' => 'EXISTS',
			],
		];
		if ( 'kommend' === $zeit ) {
			$clauses[] = [
				'key'     => TK_Model::META_LAST,
				'value'   => $today,
				'compare' => '>=',
			];
		} elseif ( 'vergangen' === $zeit ) {
			$clauses[] = [
				'key'     => TK_Model::META_LAST,
				'value'   => $today,
				'compare' => '<',
			];
		}
		$q->set( 'meta_query', $clauses );

		if ( '' === $orderby || 'tk_when' === $orderby ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC'
				: ( isset( $_GET['order'] ) ? 'DESC' : ( 'kommend' === $zeit ? 'ASC' : 'DESC' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$q->set( 'orderby', [ 'tk_start' => $order ] );
		}
	}

	/**
	 * Ansichten „Kommende | Vergangene | Alle“ statt „Alle“.
	 *
	 * @param string[] $views Ansichten.
	 * @return string[]
	 */
	public static function views( $views ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$zeit   = isset( $_GET['tk_zeit'] ) ? sanitize_key( wp_unslash( $_GET['tk_zeit'] ) ) : '';
		$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
		// phpcs:enable
		if ( '' === $zeit && '' === $status ) {
			$zeit = 'kommend';
		}

		$base = admin_url( 'edit.php?post_type=' . TK_Model::CPT );
		$mine = [];
		foreach (
			[
				'kommend'   => __( 'Kommende', 'terminkalender' ),
				'vergangen' => __( 'Vergangene', 'terminkalender' ),
				'alle'      => __( 'Alle', 'terminkalender' ),
			] as $key => $label
		) {
			$mine[ 'tk_' . $key ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( add_query_arg( 'tk_zeit', $key, $base ) ),
				$zeit === $key && '' === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}

		unset( $views['all'], $views['publish'] );

		return array_merge( $mine, $views );
	}

	/* ------------------------------------------------------------------ *
	 * Felder für Orte, Veranstalter, Kategorien
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string,array<string,array{label:string,type:string}>>
	 */
	private static function term_fields() {
		return [
			TK_Model::TAX_PLACE => [
				'tk_strasse' => [ 'label' => __( 'Straße und Hausnummer', 'terminkalender' ), 'type' => 'text' ],
				'tk_plz'     => [ 'label' => __( 'PLZ', 'terminkalender' ), 'type' => 'text' ],
				'tk_stadt'   => [ 'label' => __( 'Ort', 'terminkalender' ), 'type' => 'text' ],
			],
			TK_Model::TAX_ORG   => [
				'tk_telefon' => [ 'label' => __( 'Telefon', 'terminkalender' ), 'type' => 'tel' ],
				'tk_email'   => [ 'label' => __( 'E-Mail', 'terminkalender' ), 'type' => 'email' ],
				'tk_web'     => [ 'label' => __( 'Webseite', 'terminkalender' ), 'type' => 'url' ],
			],
			TK_Model::TAX_CAT   => [
				'tk_farbe' => [ 'label' => __( 'Farbe', 'terminkalender' ), 'type' => 'color' ],
			],
		];
	}

	/**
	 * @param string $tax Taxonomie.
	 */
	public static function term_add_fields( $tax ) {
		$fields = self::term_fields();
		foreach ( $fields[ $tax ] as $key => $f ) {
			printf(
				'<div class="form-field"><label for="%1$s">%2$s</label><input type="%3$s" name="%1$s" id="%1$s" value=""%4$s></div>',
				esc_attr( $key ),
				esc_html( $f['label'] ),
				'color' === $f['type'] ? 'text' : esc_attr( $f['type'] ),
				'color' === $f['type'] ? ' class="tk-color"' : ''
			);
		}
		if ( TK_Model::TAX_CAT === $tax ) {
			echo '<p class="description">' . esc_html__( 'Leer lassen = automatische Farbe.', 'terminkalender' ) . '</p>';
		}
		wp_nonce_field( 'tk_term', 'tk_term_nonce' );
	}

	/**
	 * @param WP_Term $term Begriff.
	 * @param string  $tax  Taxonomie.
	 */
	public static function term_edit_fields( $term, $tax ) {
		$fields = self::term_fields();
		foreach ( $fields[ $tax ] as $key => $f ) {
			printf(
				'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" name="%1$s" id="%1$s" value="%4$s"%5$s></td></tr>',
				esc_attr( $key ),
				esc_html( $f['label'] ),
				'color' === $f['type'] ? 'text' : esc_attr( $f['type'] ),
				esc_attr( get_term_meta( $term->term_id, $key, true ) ),
				'color' === $f['type'] ? ' class="tk-color"' : ''
			);
		}
		wp_nonce_field( 'tk_term', 'tk_term_nonce' );
	}

	/**
	 * @param int $term_id Begriff.
	 * @param int $tt_id   Taxonomie-Begriff.
	 */
	public static function term_save( $term_id, $tt_id ) {
		if ( ! isset( $_POST['tk_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tk_term_nonce'] ) ), 'tk_term' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}
		$fields = self::term_fields();
		if ( empty( $fields[ $term->taxonomy ] ) ) {
			return;
		}
		foreach ( array_keys( $fields[ $term->taxonomy ] ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_term_meta( $term_id, $key, self::clean_meta( $key, wp_unslash( $_POST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}
	}

	/**
	 * @param string[] $cols Spalten.
	 * @return string[]
	 */
	public static function term_columns( $cols ) {
		unset( $cols['description'], $cols['slug'] );
		$screen = get_current_screen();
		if ( $screen && TK_Model::TAX_PLACE === $screen->taxonomy ) {
			$cols['tk_info'] = __( 'Adresse', 'terminkalender' );
		} elseif ( $screen && TK_Model::TAX_ORG === $screen->taxonomy ) {
			$cols['tk_info'] = __( 'Kontakt', 'terminkalender' );
		} elseif ( $screen && TK_Model::TAX_CAT === $screen->taxonomy ) {
			$cols['tk_info'] = __( 'Farbe', 'terminkalender' );
		}
		if ( isset( $cols['posts'] ) ) {
			$count = $cols['posts'];
			unset( $cols['posts'] );
			$cols['posts'] = __( 'Termine', 'terminkalender' );
		}
		return $cols;
	}

	/**
	 * @param string $out     Ausgabe.
	 * @param string $col     Spalte.
	 * @param int    $term_id Begriff.
	 * @return string
	 */
	public static function term_column( $out, $col, $term_id ) {
		if ( 'tk_info' !== $col ) {
			return $out;
		}
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return $out;
		}
		if ( TK_Model::TAX_PLACE === $term->taxonomy ) {
			$parts = array_filter(
				[
					get_term_meta( $term_id, 'tk_strasse', true ),
					trim( get_term_meta( $term_id, 'tk_plz', true ) . ' ' . get_term_meta( $term_id, 'tk_stadt', true ) ),
				]
			);
			return esc_html( implode( ', ', $parts ) );
		}
		if ( TK_Model::TAX_ORG === $term->taxonomy ) {
			$parts = array_filter(
				[
					get_term_meta( $term_id, 'tk_telefon', true ),
					get_term_meta( $term_id, 'tk_email', true ),
				]
			);
			return esc_html( implode( ' · ', $parts ) );
		}
		foreach ( TK_Model::categories() as $cat ) {
			if ( (int) $cat['id'] === (int) $term_id ) {
				return '<span class="tk-swatch" style="background:' . esc_attr( $cat['color'] ) . '"></span> ' . esc_html( $cat['color'] );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Einstellungsseite
	 * ------------------------------------------------------------------ */

	public static function create_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'terminkalender' ) );
		}
		check_admin_referer( 'tk_create_page' );

		$page_id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Veranstaltungskalender', 'terminkalender' ),
				'post_name'    => 'kalender',
				'post_content' => '<!-- wp:shortcode -->[terminkalender]<!-- /wp:shortcode -->',
			],
			true
		);

		if ( ! is_wp_error( $page_id ) ) {
			TK_Settings::update( [ 'page_id' => (int) $page_id ] );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . TK_Model::CPT . '&page=tk-settings' ) );
		exit;
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page_id = (int) TK_Settings::get( 'page_id' );
		$page    = $page_id ? get_post( $page_id ) : null;
		$opt     = TK_Settings::OPTION;
		?>
		<div class="wrap tk-settings">
			<h1><?php esc_html_e( 'Terminkalender – Einstellungen', 'terminkalender' ); ?></h1>

			<?php TK_Import::render_section(); ?>

			<div class="tk-card">
				<h2><?php esc_html_e( 'Kalenderseite', 'terminkalender' ); ?></h2>
				<?php if ( $page && 'publish' === $page->post_status ) : ?>
					<p>
						<?php esc_html_e( 'Der Kalender ist hier erreichbar:', 'terminkalender' ); ?>
						<a href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( get_permalink( $page ) ); ?></strong></a>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'Lege eine Seite für den Kalender an (Adresse /kalender) oder füge den Shortcode [terminkalender] auf einer vorhandenen Seite ein und wähle sie unten aus.', 'terminkalender' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="tk_create_page">
						<?php wp_nonce_field( 'tk_create_page' ); ?>
						<?php submit_button( __( 'Kalenderseite anlegen', 'terminkalender' ), 'primary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>

			<form method="post" action="options.php" class="tk-card">
				<h2><?php esc_html_e( 'Darstellung', 'terminkalender' ); ?></h2>
				<?php settings_fields( 'tk_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tk-page"><?php esc_html_e( 'Seite mit dem Kalender', 'terminkalender' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								[
									'name'              => esc_attr( $opt ) . '[page_id]',
									'id'                => 'tk-page',
									'selected'          => $page_id, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									'show_option_none'  => esc_html__( '— keine —', 'terminkalender' ),
									'option_none_value' => '0',
								]
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tk-view"><?php esc_html_e( 'Startansicht', 'terminkalender' ); ?></label></th>
						<td>
							<select id="tk-view" name="<?php echo esc_attr( $opt ); ?>[default_view]">
								<option value="month" <?php selected( TK_Settings::get( 'default_view' ), 'month' ); ?>><?php esc_html_e( 'Monat', 'terminkalender' ); ?></option>
								<option value="list" <?php selected( TK_Settings::get( 'default_view' ), 'list' ); ?>><?php esc_html_e( 'Liste', 'terminkalender' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tk-accent"><?php esc_html_e( 'Akzentfarbe', 'terminkalender' ); ?></label></th>
						<td>
							<input type="text" id="tk-accent" name="<?php echo esc_attr( $opt ); ?>[accent]" value="<?php echo esc_attr( TK_Settings::get( 'accent' ) ); ?>" class="regular-text" pattern="#[0-9a-fA-F]{6}">
							<p class="description"><?php esc_html_e( 'Hex-Wert, z. B. #0b5cad. Bitte eine dunkle Farbe wählen, damit weiße Schrift darauf lesbar bleibt.', 'terminkalender' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tk-max"><?php esc_html_e( 'Termine pro Tag im Monat', 'terminkalender' ); ?></label></th>
						<td><input type="number" id="tk-max" min="1" max="10" name="<?php echo esc_attr( $opt ); ?>[max_per_day]" value="<?php echo esc_attr( TK_Settings::get( 'max_per_day' ) ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uhrzeit', 'terminkalender' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[show_end_time]" value="1" <?php checked( (int) TK_Settings::get( 'show_end_time' ), 1 ); ?>> <?php esc_html_e( 'Endzeit auch in Monat und Liste anzeigen', 'terminkalender' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="tk-slug"><?php esc_html_e( 'Adresse der Terminseiten', 'terminkalender' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input type="text" id="tk-slug" name="<?php echo esc_attr( $opt ); ?>[single_slug]" value="<?php echo esc_attr( TK_Settings::get( 'single_slug' ) ); ?>" class="regular-text tk-slug"><code>/name-des-termins/</code>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Alte Adressen', 'terminkalender' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[redirect_tec]" value="1" <?php checked( (int) TK_Settings::get( 'redirect_tec' ), 1 ); ?>> <?php esc_html_e( 'Alte Links von The Events Calendar auf die neuen Seiten weiterleiten', 'terminkalender' ); ?></label>
							<p class="description"><?php esc_html_e( 'Wirkt, sobald The Events Calendar deaktiviert ist. Alte Termin-Links führen dann zur neuen Terminseite, die alte Übersicht zur Kalenderseite und der alte iCal-Link zum neuen Kalender-Abo.', 'terminkalender' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<div class="tk-card">
				<h2><?php esc_html_e( 'Shortcode', 'terminkalender' ); ?></h2>
				<p><code>[terminkalender]</code> – <?php esc_html_e( 'kompletter Kalender', 'terminkalender' ); ?></p>
				<p><code>[terminkalender ansicht="liste"]</code> – <?php esc_html_e( 'startet in der Listenansicht', 'terminkalender' ); ?></p>
				<p><code>[terminkalender kategorien="theater,feste"]</code> – <?php esc_html_e( 'nur diese Kategorien', 'terminkalender' ); ?></p>
			</div>
		</div>
		<?php
	}
}
