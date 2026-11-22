<?php
/**
 * Übernahme der Daten aus The Events Calendar und Weiterleitung alter Adressen.
 *
 * Die Daten werden direkt aus der Datenbank gelesen. Das funktioniert auch,
 * wenn The Events Calendar bereits deaktiviert ist.
 */

defined( 'ABSPATH' ) || exit;

class TK_Import {

	const MAP_OPTION   = 'tk_import_map';
	const SLUG_OPTION  = 'tk_tec_slugs';
	const DONE_OPTION  = 'tk_import_done';
	const BATCH        = 20;
	const STATUSES     = [ 'publish', 'future', 'draft', 'pending', 'private' ];

	public static function init() {
		add_action( 'wp_ajax_tk_import', [ __CLASS__, 'ajax' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_old_urls' ], 1 );
	}

	/* ------------------------------------------------------------------ *
	 * Bestand in The Events Calendar
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string,int>
	 */
	public static function counts() {
		global $wpdb;
		$in = "'" . implode( "','", self::STATUSES ) . "'";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$posts = $wpdb->get_results(
			"SELECT post_type, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type IN ('tribe_events','tribe_venue','tribe_organizer') AND post_status IN ($in) GROUP BY post_type",
			OBJECT_K
		);
		$cats  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'tribe_events_cat' ) );
		// phpcs:enable

		return [
			'events'     => isset( $posts['tribe_events'] ) ? (int) $posts['tribe_events']->n : 0,
			'venues'     => isset( $posts['tribe_venue'] ) ? (int) $posts['tribe_venue']->n : 0,
			'organizers' => isset( $posts['tribe_organizer'] ) ? (int) $posts['tribe_organizer']->n : 0,
			'categories' => $cats,
		];
	}

	/**
	 * Abschnitt auf der Einstellungsseite.
	 */
	public static function render_section() {
		$c    = self::counts();
		$done = get_option( self::DONE_OPTION );

		if ( ! $c['events'] && ! $done ) {
			return;
		}
		?>
		<div class="tk-card">
			<h2><?php esc_html_e( 'Daten aus „The Events Calendar“ übernehmen', 'terminkalender' ); ?></h2>
			<p><?php esc_html_e( 'Gefunden:', 'terminkalender' ); ?></p>
			<ul class="tk-counts">
				<?php /* translators: %d: Anzahl. */ ?>
				<li><strong><?php echo esc_html( sprintf( __( '%d Termine', 'terminkalender' ), $c['events'] ) ); ?></strong></li>
				<?php /* translators: %d: Anzahl. */ ?>
				<li><?php echo esc_html( sprintf( __( '%d Kategorien', 'terminkalender' ), $c['categories'] ) ); ?></li>
				<?php /* translators: %d: Anzahl. */ ?>
				<li><?php echo esc_html( sprintf( __( '%d Orte', 'terminkalender' ), $c['venues'] ) ); ?></li>
				<?php /* translators: %d: Anzahl. */ ?>
				<li><?php echo esc_html( sprintf( __( '%d Veranstalter', 'terminkalender' ), $c['organizers'] ) ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Übernommen werden Titel, Beschreibung, Datum und Uhrzeit, ganztägig, Kategorien (mit Farben), Ort mit Adresse, Veranstalter mit Kontakt, Bild und Status. Eintritt und Veranstaltungs-Link werden als Zeile unten an die Beschreibung angehängt. Die Übernahme lässt sich gefahrlos wiederholen: Bereits übernommene Termine werden aktualisiert, nicht doppelt angelegt. In The Events Calendar wird nichts verändert.', 'terminkalender' ); ?></p>
			<?php if ( $done ) : ?>
				<?php /* translators: %s: Datum und Uhrzeit. */ ?>
				<p><em><?php echo esc_html( sprintf( __( 'Zuletzt übernommen: %s', 'terminkalender' ), wp_date( 'j. F Y, H:i', (int) $done ) ) ); ?></em></p>
			<?php endif; ?>
			<p>
				<button type="button" class="button button-primary" id="tk-import-start" <?php disabled( ! $c['events'] ); ?>>
					<?php echo esc_html( $done ? __( 'Erneut übernehmen', 'terminkalender' ) : __( 'Jetzt übernehmen', 'terminkalender' ) ); ?>
				</button>
			</p>
			<div id="tk-import-progress" hidden>
				<progress id="tk-import-bar" max="100" value="0"></progress>
				<p id="tk-import-status" role="status"></p>
			</div>
			<div id="tk-import-after" <?php echo $done ? '' : 'hidden'; ?>>
				<p><strong><?php esc_html_e( 'Nächste Schritte:', 'terminkalender' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'Unter „Termine“ stichprobenartig prüfen, ob alles stimmt.', 'terminkalender' ); ?></li>
					<li><?php esc_html_e( 'Kalenderseite anlegen bzw. auswählen (unten).', 'terminkalender' ); ?></li>
					<li><?php esc_html_e( 'The Events Calendar (und ggf. das Plugin „Veranstaltungskalender (eigene Ansicht)“) deaktivieren. Alte Links werden dann automatisch weitergeleitet. Die alten Daten bleiben in der Datenbank erhalten, bis du The Events Calendar löschst.', 'terminkalender' ); ?></li>
				</ol>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Übernahme in Schritten
	 * ------------------------------------------------------------------ */

	public static function ajax() {
		check_ajax_referer( 'tk_import', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Keine Berechtigung.', 'terminkalender' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- oben geprüft.
		$stage  = isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( $_POST['stage'] ) ) : '';
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		// phpcs:enable

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_defer_term_counting( true );

		try {
			$total = self::counts()['events'];

			if ( 'terms' === $stage ) {
				self::save_slugs();
				$map = [
					'cat'   => self::import_categories(),
					'venue' => self::import_venues(),
					'org'   => self::import_organizers(),
				];
				update_option( self::MAP_OPTION, $map, false );
				wp_defer_term_counting( false );
				wp_send_json_success(
					[
						'next'    => 'events',
						'offset'  => 0,
						'done'    => 0,
						'total'   => $total,
						/* translators: 1: Kategorien, 2: Orte, 3: Veranstalter. */
						'message' => sprintf( __( '%1$d Kategorien, %2$d Orte, %3$d Veranstalter übernommen. Jetzt die Termine …', 'terminkalender' ), count( $map['cat'] ), count( $map['venue'] ), count( $map['org'] ) ),
					]
				);
			}

			if ( 'events' === $stage ) {
				$done = self::import_events( $offset );
				$now  = $offset + $done;
				wp_defer_term_counting( false );
				wp_send_json_success(
					[
						'next'    => $done >= self::BATCH ? 'events' : 'finish',
						'offset'  => $now,
						'done'    => $now,
						'total'   => $total,
						/* translators: 1: erledigt, 2: gesamt. */
						'message' => sprintf( __( '%1$d von %2$d Terminen übernommen …', 'terminkalender' ), $now, $total ),
					]
				);
			}

			if ( 'finish' === $stage ) {
				update_option( self::DONE_OPTION, time(), false );
				TK_Model::bump();
				wp_send_json_success(
					[
						'next'  => '',
						'done'  => $offset,
						'total' => $total,
					]
				);
			}
		} catch ( Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		wp_send_json_error( 'Unbekannter Schritt.' );
	}

	/**
	 * Merkt sich die alten Adressen (z. B. /termine/ und /veranstaltung/).
	 */
	private static function save_slugs() {
		$events = '';
		$single = '';

		if ( class_exists( 'Tribe__Events__Main' ) && method_exists( 'Tribe__Events__Main', 'instance' ) ) {
			$main = Tribe__Events__Main::instance();
			if ( method_exists( $main, 'getRewriteSlug' ) ) {
				$events = (string) $main->getRewriteSlug();
			}
			if ( method_exists( $main, 'getRewriteSlugSingular' ) ) {
				$single = (string) $main->getRewriteSlugSingular();
			}
		}

		$opts = get_option( 'tribe_events_calendar_options', [] );
		if ( ! $events ) {
			$events = ! empty( $opts['eventsSlug'] ) ? $opts['eventsSlug'] : 'events';
		}
		if ( ! $single ) {
			$single = ! empty( $opts['singleEventSlug'] ) ? $opts['singleEventSlug'] : 'event';
		}

		update_option(
			self::SLUG_OPTION,
			[
				'events' => sanitize_title( $events ),
				'single' => sanitize_title( $single ),
			],
			false
		);
	}

	/**
	 * Begriff finden (über alte ID) oder anlegen.
	 *
	 * @param string $tax    Taxonomie.
	 * @param int    $old_id Alte ID.
	 * @param string $name   Name.
	 * @param string $slug   Slug.
	 * @return int
	 */
	private static function upsert_term( $tax, $old_id, $name, $slug = '' ) {
		$found = get_terms(
			[
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
				'meta_key'   => 'tk_tec_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => (string) $old_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			]
		);
		if ( ! is_wp_error( $found ) && $found ) {
			$term_id = (int) $found[0];
			wp_update_term( $term_id, $tax, [ 'name' => $name ] );
			return $term_id;
		}

		$exists = term_exists( $name, $tax );
		if ( $exists ) {
			$term_id = (int) $exists['term_id'];
		} else {
			$args    = $slug ? [ 'slug' => $slug ] : [];
			$created = wp_insert_term( $name, $tax, $args );
			if ( is_wp_error( $created ) ) {
				$created = wp_insert_term( $name, $tax );
			}
			if ( is_wp_error( $created ) ) {
				return 0;
			}
			$term_id = (int) $created['term_id'];
		}
		update_term_meta( $term_id, 'tk_tec_id', (string) $old_id );

		return $term_id;
	}

	/**
	 * @return array<int,int> alte ID → neue ID
	 */
	private static function import_categories() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s",
				'tribe_events_cat'
			)
		);

		$map = [];
		foreach ( (array) $rows as $row ) {
			$new = self::upsert_term( TK_Model::TAX_CAT, (int) $row->term_id, html_entity_decode( $row->name, ENT_QUOTES, 'UTF-8' ), $row->slug );
			if ( ! $new ) {
				continue;
			}
			$map[ (int) $row->term_id ] = $new;

			$color = sanitize_hex_color( (string) get_term_meta( (int) $row->term_id, 'tec-events-cat-colors-primary', true ) );
			if ( $color && ! get_term_meta( $new, 'tk_farbe', true ) ) {
				update_term_meta( $new, 'tk_farbe', $color );
			}
		}

		return $map;
	}

	/**
	 * @param string $type Beitragstyp.
	 * @return array<int,object>
	 */
	private static function linked_posts( $type ) {
		global $wpdb;
		$in = "'" . implode( "','", self::STATUSES ) . "'";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ($in) ORDER BY ID", $type ) );
	}

	/**
	 * @return array<int,int>
	 */
	private static function import_venues() {
		$map = [];
		foreach ( self::linked_posts( 'tribe_venue' ) as $p ) {
			$name = trim( html_entity_decode( wp_strip_all_tags( $p->post_title ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $name ) {
				continue;
			}
			$new = self::upsert_term( TK_Model::TAX_PLACE, (int) $p->ID, $name );
			if ( ! $new ) {
				continue;
			}
			$map[ (int) $p->ID ] = $new;

			$fields = [
				'tk_strasse' => '_VenueAddress',
				'tk_plz'     => '_VenueZip',
				'tk_stadt'   => '_VenueCity',
			];
			foreach ( $fields as $mine => $theirs ) {
				$value = trim( (string) get_post_meta( (int) $p->ID, $theirs, true ) );
				if ( '' !== $value ) {
					update_term_meta( $new, $mine, sanitize_text_field( $value ) );
				}
			}
		}
		return $map;
	}

	/**
	 * @return array<int,int>
	 */
	private static function import_organizers() {
		$map = [];
		foreach ( self::linked_posts( 'tribe_organizer' ) as $p ) {
			$name = trim( html_entity_decode( wp_strip_all_tags( $p->post_title ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $name ) {
				continue;
			}
			$new = self::upsert_term( TK_Model::TAX_ORG, (int) $p->ID, $name );
			if ( ! $new ) {
				continue;
			}
			$map[ (int) $p->ID ] = $new;

			$phone = trim( (string) get_post_meta( (int) $p->ID, '_OrganizerPhone', true ) );
			$email = trim( (string) get_post_meta( (int) $p->ID, '_OrganizerEmail', true ) );
			$web   = trim( (string) get_post_meta( (int) $p->ID, '_OrganizerWebsite', true ) );
			if ( $phone ) {
				update_term_meta( $new, 'tk_telefon', sanitize_text_field( $phone ) );
			}
			if ( $email ) {
				update_term_meta( $new, 'tk_email', sanitize_email( $email ) );
			}
			if ( $web ) {
				update_term_meta( $new, 'tk_web', esc_url_raw( $web ) );
			}
		}
		return $map;
	}

	/**
	 * Beschreibung von Block-Markierungen der Events-Calendar-Blöcke befreien.
	 *
	 * @param string $content Inhalt.
	 * @return string
	 */
	public static function clean_content( $content ) {
		$content = (string) $content;
		// Blöcke von The Events Calendar (Datum, Ort, Veranstalter usw.) – diese Angaben stehen jetzt in eigenen Feldern.
		$content = preg_replace( '#<!--\s*wp:tribe/[^>]*?/-->#s', '', $content );
		$content = preg_replace( '#<!--\s*wp:tribe/([a-z0-9-]+)\b[^>]*?-->.*?<!--\s*/wp:tribe/\1\s*-->#s', '', $content );
		// Übrige Block-Kommentare entfernen, der HTML-Inhalt bleibt.
		$content = preg_replace( '#<!--\s*/?wp:[^>]*?-->#s', '', $content );
		$content = preg_replace( "/\n{3,}/", "\n\n", $content );

		return trim( $content );
	}

	/**
	 * Termine ab Position $offset übernehmen.
	 *
	 * @param int $offset Position.
	 * @return int Anzahl verarbeiteter Termine.
	 */
	private static function import_events( $offset ) {
		global $wpdb;
		$in = "'" . implode( "','", self::STATUSES ) . "'";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->posts} WHERE post_type = 'tribe_events' AND post_status IN ($in) ORDER BY ID LIMIT %d, %d",
				$offset,
				self::BATCH
			)
		);

		$map = get_option( self::MAP_OPTION, [] );
		$map = is_array( $map ) ? $map : [];

		foreach ( (array) $rows as $old ) {
			self::import_event( $old, $map );
		}

		return count( (array) $rows );
	}

	/**
	 * @param object               $old Alter Beitrag (Datenbankzeile).
	 * @param array<string,mixed>  $map Zuordnungen.
	 */
	private static function import_event( $old, $map ) {
		global $wpdb;
		$old_id = (int) $old->ID;

		$existing = get_posts(
			[
				'post_type'      => TK_Model::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_tk_tec_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => (string) $old_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			]
		);

		$content = self::clean_content( $old->post_content );

		// Eintritt und Link als Zeile anhängen, damit nichts verloren geht.
		$extra = [];
		$cost  = trim( (string) get_post_meta( $old_id, '_EventCost', true ) );
		$url   = trim( (string) get_post_meta( $old_id, '_EventURL', true ) );
		if ( '' !== $cost ) {
			$symbol  = trim( (string) get_post_meta( $old_id, '_EventCurrencySymbol', true ) );
			$pos     = (string) get_post_meta( $old_id, '_EventCurrencyPosition', true );
			$cost    = is_numeric( str_replace( ',', '.', $cost ) ) && $symbol ? ( 'prefix' === $pos ? $symbol . ' ' . $cost : $cost . ' ' . $symbol ) : $cost;
			$extra[] = '<strong>' . esc_html__( 'Eintritt:', 'terminkalender' ) . '</strong> ' . esc_html( $cost );
		}
		if ( '' !== $url ) {
			$extra[] = '<strong>' . esc_html__( 'Weitere Infos:', 'terminkalender' ) . '</strong> <a href="' . esc_url( $url ) . '">' . esc_html( preg_replace( '#^https?://#', '', $url ) ) . '</a>';
		}
		if ( $extra && false === strpos( $content, 'tk-import-extra' ) ) {
			$content .= "\n\n<p class=\"tk-import-extra\">" . implode( '<br>', $extra ) . '</p>';
		}

		$postarr = [
			'post_type'      => TK_Model::CPT,
			'post_title'     => $old->post_title,
			'post_content'   => $content,
			'post_excerpt'   => $old->post_excerpt,
			'post_status'    => $old->post_status,
			'post_author'    => (int) $old->post_author,
			'post_date'      => $old->post_date,
			'post_date_gmt'  => $old->post_date_gmt,
			'post_name'      => $old->post_name,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		];

		if ( $existing ) {
			$postarr['ID'] = (int) $existing[0];
			$new_id        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$new_id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return;
		}

		update_post_meta( $new_id, '_tk_tec_id', (string) $old_id );
		update_post_meta( $new_id, '_tk_tec_slug', $old->post_name );

		// Datum.
		$start  = substr( (string) get_post_meta( $old_id, '_EventStartDate', true ), 0, 16 );
		$end    = substr( (string) get_post_meta( $old_id, '_EventEndDate', true ), 0, 16 );
		$allday = in_array( strtolower( (string) get_post_meta( $old_id, '_EventAllDay', true ) ), [ 'yes', 'true', '1' ], true );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $start ) ) {
			$start = substr( $old->post_date, 0, 16 );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $end ) ) {
			$end = $start;
		}
		// Bei ganztägigen Terminen endet TEC um 23:59:59 – oder um 00:00 des Folgetags.
		if ( $allday && '00:00' === substr( $end, 11, 5 ) && substr( $end, 0, 10 ) > substr( $start, 0, 10 ) ) {
			$end = gmdate( 'Y-m-d', TK_Model::ts( $end ) - DAY_IN_SECONDS ) . ' 23:59';
		}
		TK_Model::save( $new_id, $start, $end, $allday, null );

		// Bild.
		$thumb = (int) get_post_meta( $old_id, '_thumbnail_id', true );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}

		// Kategorien.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$old_cats = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = %s",
				$old_id,
				'tribe_events_cat'
			)
		);
		$cats = [];
		foreach ( (array) $old_cats as $c ) {
			if ( ! empty( $map['cat'][ (int) $c ] ) ) {
				$cats[] = (int) $map['cat'][ (int) $c ];
			}
		}
		wp_set_object_terms( $new_id, $cats, TK_Model::TAX_CAT );

		// Ort.
		$venue = (int) get_post_meta( $old_id, '_EventVenueID', true );
		wp_set_object_terms( $new_id, ! empty( $map['venue'][ $venue ] ) ? [ (int) $map['venue'][ $venue ] ] : [], TK_Model::TAX_PLACE );

		// Veranstalter (TEC erlaubt mehrere – der erste wird übernommen).
		$orgs = array_filter( array_map( 'intval', (array) get_post_meta( $old_id, '_EventOrganizerID', false ) ) );
		$org  = $orgs ? reset( $orgs ) : 0;
		wp_set_object_terms( $new_id, ! empty( $map['org'][ $org ] ) ? [ (int) $map['org'][ $org ] ] : [], TK_Model::TAX_ORG );
	}

	/* ------------------------------------------------------------------ *
	 * Alte Adressen weiterleiten
	 * ------------------------------------------------------------------ */

	public static function redirect_old_urls() {
		if ( ! TK_Settings::get( 'redirect_tec' ) || class_exists( 'Tribe__Events__Main' ) || ! is_404() ) {
			return;
		}

		$slugs = get_option( self::SLUG_OPTION );
		if ( ! is_array( $slugs ) || empty( $slugs['events'] ) ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $home && 0 === strpos( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) + 1 );
		}
		$path = rawurldecode( $path );

		// Einzelner Termin: /veranstaltung/name/ (ggf. mit Datum dahinter).
		if ( ! empty( $slugs['single'] ) && preg_match( '#^' . preg_quote( $slugs['single'], '#' ) . '/([^/]+)#', $path, $m ) ) {
			$found = get_posts(
				[
					'post_type'      => TK_Model::CPT,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_tk_tec_slug', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => sanitize_title( $m[1] ), // phpcs:ignore WordPress.DB.SlowDBQuery
				]
			);
			if ( $found ) {
				wp_safe_redirect( get_permalink( $found[0] ), 301 );
				exit;
			}
		}

		// Übersicht: /termine/, /termine/monat/, /termine/kategorie/x/ …
		if ( $path === $slugs['events'] || 0 === strpos( $path, $slugs['events'] . '/' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['ical'] ) ) {
				wp_safe_redirect( TK_Frontend::feed_url(), 301 );
				exit;
			}
			$page_id = (int) TK_Settings::get( 'page_id' );
			wp_safe_redirect( $page_id ? get_permalink( $page_id ) : home_url( '/' ), 301 );
			exit;
		}
	}
}
