<?php
/**
 * Frontend: Kalender (Shortcode), Datenschnittstelle, Terminseite, iCal, Google-Daten.
 */

defined( 'ABSPATH' ) || exit;

class TK_Frontend {

	const REST_NS = 'tk/v1';
	const HANDLE  = 'tk-kalender';
	const MAX_RANGE = 75;

	/** @var int */
	private static $count = 0;

	public static function init() {
		add_action( 'init', [ __CLASS__, 'shortcodes' ], 20 );
		add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'template_redirect', [ __CLASS__, 'ics' ], 0 );
		add_filter( 'the_content', [ __CLASS__, 'single_content' ], 20 );
		add_filter( 'the_content', [ __CLASS__, 'clickable_images' ], 25 );
		add_filter( 'post_thumbnail_html', [ __CLASS__, 'clickable_thumbnail' ], 20, 3 );
		add_action( 'wp_head', [ __CLASS__, 'json_ld' ] );
	}

	public static function shortcodes() {
		add_shortcode( 'terminkalender', [ __CLASS__, 'shortcode' ] );
		// Kompatibel zur bisherigen Kalenderseite, sobald das alte Plugin deaktiviert ist.
		if ( ! shortcode_exists( 'veranstaltungskalender' ) ) {
			add_shortcode( 'veranstaltungskalender', [ __CLASS__, 'shortcode' ] );
		}
	}

	public static function register_assets() {
		wp_register_style( self::HANDLE, TK_URL . 'assets/css/kalender.css', [], TK_VERSION );
		wp_register_script( self::HANDLE, TK_URL . 'assets/js/kalender.js', [], TK_VERSION, true );
		wp_register_style( 'tk-termin', TK_URL . 'assets/css/termin.css', [], TK_VERSION );
		wp_register_style( 'tk-lightbox', TK_URL . 'assets/css/lightbox.css', [], TK_VERSION );
		wp_register_script( 'tk-lightbox', TK_URL . 'assets/js/lightbox.js', [], TK_VERSION, true );
		wp_localize_script(
			'tk-lightbox',
			'tkLightboxL10n',
			[
				'close' => __( 'Schließen', 'terminkalender' ),
				'prev'  => __( 'Vorheriges Bild', 'terminkalender' ),
				'next'  => __( 'Nächstes Bild', 'terminkalender' ),
			]
		);

		$page_id = (int) TK_Settings::get( 'page_id' );
		if ( $page_id && is_page( $page_id ) ) {
			wp_enqueue_style( self::HANDLE );
		}
		if ( is_singular( TK_Model::CPT ) ) {
			self::enqueue_lightbox();
			wp_enqueue_style( 'tk-termin' );
			$accent = sanitize_hex_color( (string) TK_Settings::get( 'accent' ) );
			wp_add_inline_style( 'tk-termin', '.tk-info{--tk-accent:' . ( $accent ? $accent : '#0b5cad' ) . ';}' );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Daten
	 * ------------------------------------------------------------------ */

	public static function routes() {
		register_rest_route(
			self::REST_NS,
			'/events',
			[
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => [ __CLASS__, 'rest_events' ],
				'args'                => [
					'start' => [
						'required'          => true,
						'validate_callback' => [ __CLASS__, 'is_date' ],
					],
					'end'   => [
						'required'          => true,
						'validate_callback' => [ __CLASS__, 'is_date' ],
					],
				],
			]
		);
	}

	/**
	 * @param mixed $v Wert.
	 * @return bool
	 */
	public static function is_date( $v ) {
		return is_string( $v ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v );
	}

	/**
	 * @param WP_REST_Request $request Anfrage.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_events( $request ) {
		$start = (string) $request->get_param( 'start' );
		$end   = (string) $request->get_param( 'end' );
		$days  = ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS;
		if ( $days < 0 || $days > self::MAX_RANGE ) {
			return new WP_Error( 'tk_range', 'Ungültiger Zeitraum.', [ 'status' => 400 ] );
		}

		$response = rest_ensure_response(
			[
				'start'  => $start,
				'end'    => $end,
				'events' => self::events( $start, $end ),
			]
		);
		$response->header( 'Cache-Control', 'public, max-age=300' );

		return $response;
	}

	/**
	 * Alle Vorkommen im Zeitraum als kompakte Liste für den Kalender.
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return array<int,array<string,mixed>>
	 */
	public static function events( $from, $to ) {
		$key    = 'tk_ev_' . TK_Model::generation() . '_' . $from . '_' . $to;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$out = [];
		foreach ( TK_Model::query_ids( $from, $to ) as $id ) {
			$data = TK_Model::get( $id );
			$occs = TK_Model::occurrences( $data, $from, $to );
			if ( ! $occs ) {
				continue;
			}

			$base  = self::base_info( $id );
			$multi = (bool) $data['rule'];

			foreach ( $occs as $occ ) {
				$date = substr( $occ['start'], 0, 10 );
				$item = $base;

				$item['id'] = $multi ? $id . '-' . $date : (string) $id;
				$item['u']  = $multi ? add_query_arg( 'datum', $date, $base['u'] ) : $base['u'];
				$item['s']  = str_replace( ' ', 'T', $occ['start'] );
				$item['e']  = str_replace( ' ', 'T', $occ['end'] );
				$item['ad'] = $data['allday'];
				$item['i']  = self::ics_url( $id, $multi ? $date : '' );
				$item['r']  = $multi;

				$out[] = $item;
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a['s'], $b['s'] );
			}
		);

		set_transient( $key, $out, HOUR_IN_SECONDS );

		return $out;
	}

	/**
	 * Gleichbleibende Angaben eines Termins.
	 *
	 * @param int $id Termin.
	 * @return array<string,mixed>
	 */
	private static function base_info( $id ) {
		$place = TK_Model::place( $id );
		$org   = TK_Model::organizer( $id );
		$terms = get_the_terms( $id, TK_Model::TAX_CAT );
		$cats  = ( empty( $terms ) || is_wp_error( $terms ) ) ? [] : array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );

		$city = $place ? $place['city'] : '';
		if ( $place && ! empty( $place['free'] ) ) {
			$city = $place['street']; // Frei eingegebene Adresse.
		}
		if ( $place && $city && false !== mb_stripos( $place['name'], $city ) ) {
			$city = '';
		}

		$thumb = get_post_thumbnail_id( $id );

		$post = get_post( $id );
		$text = has_excerpt( $id ) ? $post->post_excerpt : $post->post_content;

		return [
			't' => self::plain( get_the_title( $id ) ),
			'u' => (string) get_permalink( $id ),
			'v' => $place ? $place['name'] : '',
			'c' => $city,
			'o' => $org ? $org['name'] : '',
			'k' => $cats,
			'f' => false,
			'x' => wp_trim_words( self::plain( strip_shortcodes( $text ) ), 35, ' …' ),
			'm' => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'medium_large' ) : '',
			'M' => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'full' ) : '',
		];
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	private static function plain( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/* ------------------------------------------------------------------ *
	 * Kalender (Shortcode)
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string,string>|string $atts Attribute.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		global $wp_locale;

		$atts = shortcode_atts(
			[
				'ansicht'    => '',
				'kategorien' => '',
			],
			is_array( $atts ) ? $atts : [],
			'terminkalender'
		);

		self::$count++;
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
		self::enqueue_lightbox();

		$now   = current_datetime();
		$sow   = (int) get_option( 'start_of_week', 1 );
		$range = self::grid_range( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), $sow );

		$all   = TK_Model::categories();
		$limit = [];
		if ( '' !== trim( $atts['kategorien'] ) ) {
			$slugs = array_filter( array_map( 'sanitize_title', explode( ',', $atts['kategorien'] ) ) );
			foreach ( $all as $cat ) {
				if ( in_array( $cat['slug'], $slugs, true ) ) {
					$limit[] = $cat['id'];
				}
			}
		}

		$view = TK_Settings::get( 'default_view' );
		if ( in_array( $atts['ansicht'], [ 'liste', 'list' ], true ) ) {
			$view = 'list';
		} elseif ( in_array( $atts['ansicht'], [ 'woche', 'week' ], true ) ) {
			$view = 'week';
		} elseif ( in_array( $atts['ansicht'], [ 'monat', 'month' ], true ) ) {
			$view = 'month';
		}

		$months = [];
		for ( $m = 1; $m <= 12; $m++ ) {
			$months[] = $wp_locale->get_month( $m );
		}
		$weekdays = [];
		$abbr     = [];
		for ( $d = 0; $d <= 6; $d++ ) {
			$weekdays[] = $wp_locale->get_weekday( $d );
			$abbr[]     = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d ) );
		}

		$config = [
			'rest'        => esc_url_raw( rest_url( self::REST_NS . '/events' ) ),
			'today'       => $now->format( 'Y-m-d' ),
			'view'        => $view,
			'startOfWeek' => $sow,
			'maxPerDay'   => (int) TK_Settings::get( 'max_per_day' ),
			'showEnd'     => (bool) TK_Settings::get( 'show_end_time' ),
			'categories'  => $all,
			'limit'       => $limit,
			'subscribe'   => self::feed_url(),
			'initial'     => [
				'start'  => $range[0],
				'end'    => $range[1],
				'events' => self::events( $range[0], $range[1] ),
			],
			'i18n'        => self::strings( $months, $weekdays, $abbr ),
		];

		$accent = sanitize_hex_color( (string) TK_Settings::get( 'accent' ) );
		$accent = $accent ? $accent : TK_Settings::defaults()['accent'];

		return sprintf(
			'<div id="tk-cal-%1$d" class="tk-cal" style="--tk-accent:%2$s"><script type="application/json" class="tk-config">%3$s</script><div class="tk-cal__loading" aria-hidden="true"></div><noscript><p>%4$s</p></noscript></div>',
			(int) self::$count,
			esc_attr( $accent ),
			wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ),
			esc_html__( 'Für den Kalender wird JavaScript benötigt.', 'terminkalender' )
		);
	}

	/**
	 * @param string[] $months   Monatsnamen.
	 * @param string[] $weekdays Wochentage.
	 * @param string[] $abbr     Abkürzungen.
	 * @return array<string,mixed>
	 */
	private static function strings( $months, $weekdays, $abbr ) {
		return [
			'months'       => $months,
			'weekdays'     => $weekdays,
			'weekdaysAbbr' => $abbr,
			'month'        => __( 'Monat', 'terminkalender' ),
			'list'         => __( 'Liste', 'terminkalender' ),
			'view'         => __( 'Ansicht', 'terminkalender' ),
			'today'        => __( 'Heute', 'terminkalender' ),
			'prev'         => __( 'Vorheriger Monat', 'terminkalender' ),
			'next'         => __( 'Nächster Monat', 'terminkalender' ),
			'week'         => __( 'Woche', 'terminkalender' ),
			'prevWeek'     => __( 'Vorherige Woche', 'terminkalender' ),
			'nextWeek'     => __( 'Nächste Woche', 'terminkalender' ),
			'noWeek'       => __( 'In dieser Woche sind keine Termine eingetragen.', 'terminkalender' ),
			'zoom'         => __( 'Bild vergrößern', 'terminkalender' ),
			'search'       => __( 'Termin suchen', 'terminkalender' ),
			'all'          => __( 'Alle', 'terminkalender' ),
			'filter'       => __( 'Nach Kategorien filtern (mehrere möglich)', 'terminkalender' ),
			'allDay'       => __( 'Ganztägig', 'terminkalender' ),
			'until'        => __( 'bis %s', 'terminkalender' ),
			'oclock'       => __( 'Uhr', 'terminkalender' ),
			'more'         => __( '+ %d weitere', 'terminkalender' ),
			'events1'      => __( '1 Termin', 'terminkalender' ),
			'eventsN'      => __( '%d Termine', 'terminkalender' ),
			'noEvents'     => __( 'Keine Termine', 'terminkalender' ),
			'noDay'        => __( 'An diesem Tag sind keine Termine eingetragen.', 'terminkalender' ),
			'noMonth'      => __( 'Für %s sind keine Termine eingetragen.', 'terminkalender' ),
			'noMatch'      => __( 'Keine Termine passen zu deiner Auswahl.', 'terminkalender' ),
			'reset'        => __( 'Filter zurücksetzen', 'terminkalender' ),
			'nextMonth'    => __( 'Zum nächsten Monat', 'terminkalender' ),
			'showPast'     => __( 'Frühere Termine in diesem Monat anzeigen', 'terminkalender' ),
			'hidePast'     => __( 'Frühere Termine ausblenden', 'terminkalender' ),
			'details'      => __( 'Details ansehen', 'terminkalender' ),
			'addCal'       => __( 'In Kalender eintragen', 'terminkalender' ),
			'close'        => __( 'Schließen', 'terminkalender' ),
			'subscribe'    => __( 'Kalender abonnieren (iCal)', 'terminkalender' ),
			'loading'      => __( 'Termine werden geladen …', 'terminkalender' ),
			'error'        => __( 'Die Termine konnten nicht geladen werden. Bitte versuche es später noch einmal.', 'terminkalender' ),
			'retry'        => __( 'Erneut versuchen', 'terminkalender' ),
			'series'       => __( 'Regelmäßiger Termin', 'terminkalender' ),
		];
	}

	/**
	 * @param int $year  Jahr.
	 * @param int $month Monat.
	 * @param int $sow   Wochenbeginn.
	 * @return string[]
	 */
	public static function grid_range( $year, $month, $sow ) {
		$first      = new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ) );
		$offset     = ( (int) $first->format( 'w' ) - $sow + 7 ) % 7;
		$start      = $first->modify( '-' . $offset . ' days' );
		$last       = $first->modify( 'last day of this month' );
		$end_offset = ( $sow + 6 - (int) $last->format( 'w' ) + 7 ) % 7;
		$end        = $last->modify( '+' . $end_offset . ' days' );

		return [ $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ];
	}

	/* ------------------------------------------------------------------ *
	 * Bilder anklickbar (Lightbox)
	 * ------------------------------------------------------------------ */

	/**
	 * Ist WX Lightbox aktiv? Dann öffnet sie die Bilder, sonst die eigene kleine Lightbox.
	 *
	 * @return bool
	 */
	public static function external_lightbox() {
		return defined( 'SBL_VERSION' ) && apply_filters( 'sbl_enabled', true );
	}

	public static function enqueue_lightbox() {
		if ( self::external_lightbox() ) {
			return;
		}
		wp_enqueue_style( 'tk-lightbox' );
		wp_enqueue_script( 'tk-lightbox' );
	}

	/**
	 * @return bool Terminseite, Hauptinhalt.
	 */
	private static function on_single_main() {
		return is_singular( TK_Model::CPT ) && in_the_loop() && is_main_query();
	}

	/**
	 * Alle Bilder in der Beschreibung öffnen sich vergrößert.
	 *
	 * @param string $content Inhalt.
	 * @return string
	 */
	public static function clickable_images( $content ) {
		if ( ! self::on_single_main() || false === stripos( $content, '<img' ) ) {
			return $content;
		}
		return self::link_images( $content );
	}

	/**
	 * Verlinkt Bilder auf ihre Originalgröße. Vorhandene Links auf Bilddateien
	 * werden markiert, andere Links (z. B. auf Seiten) bleiben unverändert.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function link_images( $html ) {
		$parts = preg_split( '#(<a\b[^>]*>.*?</a>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}

		foreach ( $parts as $i => $part ) {
			if ( preg_match( '#^<a\b#i', $part ) ) {
				if ( preg_match( '#<img\b#i', $part )
					&& preg_match( '#^<a\b[^>]*\bhref=(["\'])[^"\']+\.(?:jpe?g|png|gif|webp|avif)(?:\?[^"\']*)?\1#i', $part )
					&& false === strpos( $part, 'tk-zoom' ) ) {
					$parts[ $i ] = preg_replace( '#^<a\b#i', '<a data-lightbox="tk-termin"', $part, 1 );
					$parts[ $i ] = preg_match( '#^<a\b[^>]*\bclass=(["\'])#i', $parts[ $i ] )
						? preg_replace( '#^(<a\b[^>]*\bclass=(["\']))#i', '$1tk-zoom ', $parts[ $i ], 1 )
						: preg_replace( '#^<a\b#i', '<a class="tk-zoom"', $parts[ $i ], 1 );
				}
				continue;
			}

			$parts[ $i ] = preg_replace_callback(
				'#<img\b[^>]*>#i',
				static function ( $m ) {
					$tag = $m[0];
					if ( preg_match( '#class=(["\'])[^"\']*\b(wp-smiley|emoji|no-lightbox)\b#i', $tag ) ) {
						return $tag;
					}
					$full = '';
					if ( preg_match( '#\bwp-image-(\d+)\b#', $tag, $id ) ) {
						$full = (string) wp_get_attachment_image_url( (int) $id[1], 'full' );
					}
					if ( ! $full && preg_match( '#\bsrc=(["\'])([^"\']+)\1#i', $tag, $src ) ) {
						$full = html_entity_decode( $src[2], ENT_QUOTES, 'UTF-8' );
					}
					if ( ! $full ) {
						return $tag;
					}
					return '<a href="' . esc_url( $full ) . '" class="tk-zoom" data-lightbox="tk-termin">' . $tag . '</a>';
				},
				$part
			);
		}

		return implode( '', $parts );
	}

	/**
	 * Beitragsbild auf der Terminseite vergrößerbar machen.
	 *
	 * @param string $html     HTML.
	 * @param int    $post_id  Beitrag.
	 * @param int    $thumb_id Bild.
	 * @return string
	 */
	public static function clickable_thumbnail( $html, $post_id, $thumb_id ) {
		if ( ! $html || ! $thumb_id || ! is_singular( TK_Model::CPT ) || (int) $post_id !== (int) get_queried_object_id() ) {
			return $html;
		}
		if ( false !== strpos( $html, '<a ' ) ) {
			return $html;
		}
		$full = wp_get_attachment_image_url( $thumb_id, 'full' );
		if ( ! $full ) {
			return $html;
		}
		self::enqueue_lightbox();
		return '<a href="' . esc_url( $full ) . '" class="tk-zoom" data-lightbox="tk-termin">' . $html . '</a>';
	}

	/* ------------------------------------------------------------------ *
	 * Terminseite
	 * ------------------------------------------------------------------ */

	/**
	 * Welches Vorkommen auf der Terminseite gezeigt wird.
	 *
	 * @param int $id Termin.
	 * @return array{start:string,end:string}|null
	 */
	public static function shown_occurrence( $id ) {
		$data = TK_Model::get( $id );
		if ( ! $data['start'] ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wanted = isset( $_GET['datum'] ) ? sanitize_text_field( wp_unslash( $_GET['datum'] ) ) : '';
		if ( $data['rule'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $wanted ) ) {
			$hit = TK_Model::occurrences( $data, $wanted, $wanted );
			foreach ( $hit as $occ ) {
				if ( substr( $occ['start'], 0, 10 ) === $wanted ) {
					return $occ;
				}
			}
		}

		if ( $data['rule'] ) {
			$next = TK_Model::upcoming( $id, 1 );
			if ( $next ) {
				return $next[0];
			}
		}

		return [
			'start' => $data['start'],
			'end'   => $data['end'],
		];
	}

	/**
	 * „Samstag, 7. November 2026 · 20:00 – 23:00 Uhr“
	 *
	 * @param array{start:string,end:string} $occ    Vorkommen.
	 * @param bool                           $allday Ganztägig.
	 * @return string
	 */
	public static function when_text( $occ, $allday ) {
		$s_ts  = TK_Model::ts( $occ['start'] );
		$e_ts  = TK_Model::ts( $occ['end'] );
		$s_day = substr( $occ['start'], 0, 10 );
		$e_day = substr( $occ['end'], 0, 10 );

		if ( $s_day !== $e_day ) {
			if ( $allday ) {
				return TK_Model::fmt( 'l, j. F Y', $s_ts ) . ' – ' . TK_Model::fmt( 'l, j. F Y', $e_ts );
			}
			/* translators: 1: Beginn, 2: Ende. */
			return sprintf( __( '%1$s – %2$s', 'terminkalender' ), TK_Model::fmt( 'l, j. F Y, H:i', $s_ts ), TK_Model::fmt( 'l, j. F Y, H:i \U\h\r', $e_ts ) );
		}
		if ( $allday ) {
			return TK_Model::fmt( 'l, j. F Y', $s_ts ) . ' · ' . __( 'ganztägig', 'terminkalender' );
		}
		$time = substr( $occ['start'], 11, 5 );
		if ( substr( $occ['end'], 11, 5 ) !== $time ) {
			$time .= ' – ' . substr( $occ['end'], 11, 5 );
		}
		return TK_Model::fmt( 'l, j. F Y', $s_ts ) . ' · ' . $time . ' ' . __( 'Uhr', 'terminkalender' );
	}

	/**
	 * Infobox vor der Beschreibung auf der Terminseite.
	 *
	 * @param string $content Inhalt.
	 * @return string
	 */
	public static function single_content( $content ) {
		if ( ! self::on_single_main() ) {
			return $content;
		}

		$id   = get_the_ID();
		$data = TK_Model::get( $id );
		$occ  = self::shown_occurrence( $id );
		if ( ! $occ ) {
			return $content;
		}

		$place = TK_Model::place( $id );
		$org   = TK_Model::organizer( $id );
		$terms = get_the_terms( $id, TK_Model::TAX_CAT );
		$cats  = [];
		if ( $terms && ! is_wp_error( $terms ) ) {
			$colors = [];
			foreach ( TK_Model::categories() as $c ) {
				$colors[ $c['id'] ] = $c['color'];
			}
			foreach ( $terms as $t ) {
				$cats[] = '<span class="tk-tag" style="--c:' . esc_attr( isset( $colors[ $t->term_id ] ) ? $colors[ $t->term_id ] : '' ) . '">' . esc_html( $t->name ) . '</span>';
			}
		}

		$date    = substr( $occ['start'], 0, 10 );
		$ics     = self::ics_url( $id, $data['rule'] ? $date : '' );
		$page_id = (int) TK_Settings::get( 'page_id' );
		$back    = $page_id ? add_query_arg( 'monat', substr( $date, 0, 7 ), get_permalink( $page_id ) ) : '';

		ob_start();
		?>
		<div class="tk-info">
			<dl class="tk-info__list">
				<div class="tk-info__row">
					<dt><?php esc_html_e( 'Wann', 'terminkalender' ); ?></dt>
					<dd>
						<?php echo esc_html( self::when_text( $occ, $data['allday'] ) ); ?>
						<?php if ( $data['rule'] ) : ?>
							<br><span class="tk-info__muted"><?php echo esc_html( ucfirst( TK_Model::rule_text( $data ) ) ); ?></span>
						<?php endif; ?>
					</dd>
				</div>
				<?php if ( $place ) : ?>
					<?php
					$addr  = trim( $place['street'] . ', ' . trim( $place['zip'] . ' ' . $place['city'] ), ', ' );
					$query = trim( $place['name'] . ', ' . $addr, ', ' );
					?>
					<div class="tk-info__row">
						<dt><?php esc_html_e( 'Wo', 'terminkalender' ); ?></dt>
						<dd>
							<strong><?php echo esc_html( $place['name'] ); ?></strong>
							<?php if ( $addr ) : ?>
								<br><?php echo esc_html( $addr ); ?>
								<br><a href="<?php echo esc_url( 'https://www.openstreetmap.org/search?query=' . rawurlencode( $query ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Auf der Karte anzeigen', 'terminkalender' ); ?></a>
							<?php endif; ?>
						</dd>
					</div>
				<?php endif; ?>
				<?php if ( $org ) : ?>
					<div class="tk-info__row">
						<dt><?php esc_html_e( 'Veranstalter', 'terminkalender' ); ?></dt>
						<dd>
							<strong><?php echo esc_html( $org['name'] ); ?></strong>
							<?php if ( $org['phone'] ) : ?>
								<br><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $org['phone'] ) ); ?>"><?php echo esc_html( $org['phone'] ); ?></a>
							<?php endif; ?>
							<?php if ( $org['email'] ) : ?>
								<br><a href="<?php echo esc_url( 'mailto:' . antispambot( $org['email'] ) ); ?>"><?php echo esc_html( antispambot( $org['email'] ) ); ?></a>
							<?php endif; ?>
							<?php if ( $org['web'] ) : ?>
								<br><a href="<?php echo esc_url( $org['web'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $org['web'] ) ) ); ?></a>
							<?php endif; ?>
						</dd>
					</div>
				<?php endif; ?>
				<?php if ( $cats ) : ?>
					<div class="tk-info__row">
						<dt><?php esc_html_e( 'Kategorie', 'terminkalender' ); ?></dt>
						<dd class="tk-info__tags"><?php echo implode( ' ', $cats ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escaped. ?></dd>
					</div>
				<?php endif; ?>
			</dl>

			<?php if ( $data['rule'] ) : ?>
				<?php
				$next = array_filter(
					TK_Model::upcoming( $id, 7 ),
					static function ( $o ) use ( $occ ) {
						return $o['start'] !== $occ['start'];
					}
				);
				$next = array_slice( $next, 0, 6 );
				?>
				<?php if ( $next ) : ?>
					<div class="tk-info__next">
						<span class="tk-info__label"><?php esc_html_e( 'Weitere Termine', 'terminkalender' ); ?></span>
						<ul>
							<?php foreach ( $next as $o ) : ?>
								<li><a href="<?php echo esc_url( add_query_arg( 'datum', substr( $o['start'], 0, 10 ), get_permalink( $id ) ) ); ?>"><?php echo esc_html( TK_Model::fmt( 'D, j. M Y', TK_Model::ts( $o['start'] ) ) ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="tk-info__actions">
				<a class="tk-info__btn tk-info__btn--primary" href="<?php echo esc_url( $ics ); ?>" rel="nofollow"><?php esc_html_e( 'In Kalender eintragen', 'terminkalender' ); ?></a>
				<?php if ( $back ) : ?>
					<a class="tk-info__btn" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Zum Kalender', 'terminkalender' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean() . $content;
	}

	/**
	 * Strukturierte Daten für Suchmaschinen (schema.org/Event).
	 */
	public static function json_ld() {
		if ( ! is_singular( TK_Model::CPT ) ) {
			return;
		}
		$id   = get_queried_object_id();
		$data = TK_Model::get( $id );
		$occ  = self::shown_occurrence( $id );
		if ( ! $occ ) {
			return;
		}

		$tz    = wp_timezone();
		$start = new DateTimeImmutable( $occ['start'], $tz );
		$end   = new DateTimeImmutable( $occ['end'], $tz );
		$post  = get_post( $id );

		$ld = [
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => self::plain( get_the_title( $id ) ),
			'startDate'           => $data['allday'] ? $start->format( 'Y-m-d' ) : $start->format( 'c' ),
			'endDate'             => $data['allday'] ? $end->format( 'Y-m-d' ) : $end->format( 'c' ),
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'url'                 => get_permalink( $id ),
			'description'         => wp_trim_words( self::plain( strip_shortcodes( $post->post_content ) ), 60, ' …' ),
		];

		$place = TK_Model::place( $id );
		if ( $place ) {
			$ld['location'] = [
				'@type'   => 'Place',
				'name'    => $place['name'],
				'address' => [
					'@type'           => 'PostalAddress',
					'streetAddress'   => $place['street'],
					'postalCode'      => $place['zip'],
					'addressLocality' => $place['city'],
					'addressCountry'  => 'DE',
				],
			];
		}
		$org = TK_Model::organizer( $id );
		if ( $org ) {
			$ld['organizer'] = array_filter(
				[
					'@type' => 'Organization',
					'name'  => $org['name'],
					'url'   => $org['web'],
				]
			);
		}
		$img = get_the_post_thumbnail_url( $id, 'large' );
		if ( $img ) {
			$ld['image'] = [ $img ];
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n";
	}

	/* ------------------------------------------------------------------ *
	 * iCal
	 * ------------------------------------------------------------------ */

	/**
	 * @param int    $id   Termin.
	 * @param string $date Vorkommen (Y-m-d) oder leer.
	 * @return string
	 */
	public static function ics_url( $id, $date = '' ) {
		$args = [ 'tk_ics' => (int) $id ];
		if ( $date ) {
			$args['datum'] = $date;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * @return string
	 */
	public static function feed_url() {
		return add_query_arg( 'tk_ics', 'alle', home_url( '/' ) );
	}

	/**
	 * Liefert .ics für einen Termin oder alle kommenden Termine (Abo).
	 */
	public static function ics() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tk_ics'] ) ) {
			return;
		}
		$what = sanitize_key( wp_unslash( $_GET['tk_ics'] ) );
		$date = isset( $_GET['datum'] ) ? sanitize_text_field( wp_unslash( $_GET['datum'] ) ) : '';
		// phpcs:enable

		$events = [];
		$name   = 'termine';

		if ( 'alle' === $what ) {
			$from = current_datetime()->modify( '-30 days' )->format( 'Y-m-d' );
			$to   = current_datetime()->modify( '+365 days' )->format( 'Y-m-d' );
			foreach ( TK_Model::query_ids( $from, $to ) as $id ) {
				foreach ( TK_Model::occurrences( TK_Model::get( $id ), $from, $to ) as $occ ) {
					$events[] = [ $id, $occ ];
				}
			}
		} else {
			$id   = absint( $what );
			$post = get_post( $id );
			if ( ! $post || TK_Model::CPT !== $post->post_type || 'publish' !== $post->post_status ) {
				return;
			}
			$data = TK_Model::get( $id );
			if ( $data['rule'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				foreach ( TK_Model::occurrences( $data, $date, $date ) as $occ ) {
					if ( substr( $occ['start'], 0, 10 ) === $date ) {
						$events[] = [ $id, $occ ];
					}
				}
			} elseif ( $data['rule'] ) {
				foreach ( TK_Model::occurrences( $data, substr( $data['start'], 0, 10 ), $data['rule']['until'] ) as $occ ) {
					$events[] = [ $id, $occ ];
				}
			} else {
				$events[] = [
					$id,
					[
						'start' => $data['start'],
						'end'   => $data['end'],
					],
				];
			}
			$name = $post->post_name;
		}

		$tz    = wp_timezone();
		$utc   = new DateTimeZone( 'UTC' );
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$stamp = gmdate( 'Ymd\THis\Z' );
		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//' . $host . '//Terminkalender//DE',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::ics_text( get_bloginfo( 'name' ) ),
		];

		foreach ( $events as $pair ) {
			list( $id, $occ ) = $pair;
			$data  = TK_Model::get( $id );
			$place = TK_Model::place( $id );
			$post  = get_post( $id );

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . $id . '-' . substr( $occ['start'], 0, 10 ) . '@' . $host;
			$lines[] = 'DTSTAMP:' . $stamp;
			if ( $data['allday'] ) {
				$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', substr( $occ['start'], 0, 10 ) );
				$lines[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', TK_Model::ts( substr( $occ['end'], 0, 10 ) . ' 00:00' ) + DAY_IN_SECONDS );
			} else {
				$s       = new DateTimeImmutable( $occ['start'], $tz );
				$e       = new DateTimeImmutable( $occ['end'], $tz );
				$lines[] = 'DTSTART:' . $s->setTimezone( $utc )->format( 'Ymd\THis\Z' );
				$lines[] = 'DTEND:' . $e->setTimezone( $utc )->format( 'Ymd\THis\Z' );
			}
			$lines[] = 'SUMMARY:' . self::ics_text( self::plain( get_the_title( $id ) ) );
			$desc    = wp_trim_words( self::plain( strip_shortcodes( $post->post_content ) ), 80, ' …' );
			if ( $desc ) {
				$lines[] = 'DESCRIPTION:' . self::ics_text( $desc );
			}
			if ( $place ) {
				$lines[] = 'LOCATION:' . self::ics_text( trim( $place['name'] . ', ' . $place['street'] . ', ' . trim( $place['zip'] . ' ' . $place['city'] ), ', ' ) );
			}
			$lines[] = 'URL:' . get_permalink( $id );
			$lines[] = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: ' . ( 'alle' === $what ? 'inline' : 'attachment' ) . '; filename="' . sanitize_file_name( $name ) . '.ics"' );

		$out = '';
		foreach ( $lines as $line ) {
			$out .= self::ics_fold( $line ) . "\r\n";
		}
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal-Text, oben maskiert.
		exit;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	private static function ics_text( $text ) {
		return str_replace( [ '\\', ';', ',', "\r\n", "\n" ], [ '\\\\', '\;', '\,', '\n', '\n' ], (string) $text );
	}

	/**
	 * Lange Zeilen nach 75 Bytes umbrechen (RFC 5545).
	 *
	 * @param string $line Zeile.
	 * @return string
	 */
	private static function ics_fold( $line ) {
		$out = '';
		while ( strlen( $line ) > 75 ) {
			$cut = 75;
			// Nicht mitten in einem UTF-8-Zeichen trennen.
			while ( $cut > 0 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
				$cut--;
			}
			$out .= substr( $line, 0, $cut ) . "\r\n ";
			$line = substr( $line, $cut );
		}
		return $out . $line;
	}
}
