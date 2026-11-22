<?php
/**
 * Datenmodell: Beitragstyp „Termin“, Taxonomien Kategorie/Ort/Veranstalter,
 * Termindaten, Serien und Abfragen nach Zeitraum.
 */

defined( 'ABSPATH' ) || exit;

class TK_Model {

	const CPT       = 'tk_termin';
	const TAX_CAT   = 'tk_kategorie';
	const TAX_PLACE = 'tk_ort';
	const TAX_ORG   = 'tk_veranstalter';

	// Termindaten (Ortszeit der Website, Format „Y-m-d H:i“).
	const META_START  = '_tk_start';
	const META_END    = '_tk_end';
	const META_ALLDAY = '_tk_allday';
	const META_LAST   = '_tk_last';   // Letzter Tag (Y-m-d), bei Serien das Ende der Serie.
	const META_RULE   = '_tk_rule';   // Serie (Array) oder leer.
	const META_PLACE  = '_tk_ort_frei'; // Frei eingegebener Ort: [ 'name' => …, 'address' => … ].

	const GEN_OPTION = 'tk_cache_generation';

	/** Höchstzahl erzeugter Serientermine (Schutz vor Endlosschleifen). */
	const MAX_OCCURRENCES = 400;

	/**
	 * Farben für Kategorien ohne eigene Farbe (ausreichender Kontrast auf Weiß).
	 *
	 * @var string[]
	 */
	public static $palette = [
		'#2563eb', '#c2410c', '#7c3aed', '#15803d', '#0e7490',
		'#be185d', '#a16207', '#4f46e5', '#b91c1c', '#047857',
	];

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'init', [ __CLASS__, 'maybe_flush' ], 99 );

		// Zwischenspeicher bei Änderungen verwerfen.
		add_action( 'save_post_' . self::CPT, [ __CLASS__, 'bump' ] );
		add_action( 'deleted_post', [ __CLASS__, 'bump_if_ours' ] );
		add_action( 'trashed_post', [ __CLASS__, 'bump_if_ours' ] );
		add_action( 'untrashed_post', [ __CLASS__, 'bump_if_ours' ] );
		foreach ( [ self::TAX_CAT, self::TAX_PLACE, self::TAX_ORG ] as $tax ) {
			add_action( 'created_' . $tax, [ __CLASS__, 'bump' ] );
			add_action( 'edited_' . $tax, [ __CLASS__, 'bump' ] );
			add_action( 'delete_' . $tax, [ __CLASS__, 'bump' ] );
		}
	}

	public static function register() {
		register_post_type(
			self::CPT,
			[
				'labels'              => [
					'name'               => __( 'Termine', 'terminkalender' ),
					'singular_name'      => __( 'Termin', 'terminkalender' ),
					'menu_name'          => __( 'Termine', 'terminkalender' ),
					'all_items'          => __( 'Alle Termine', 'terminkalender' ),
					'add_new'            => __( 'Neuer Termin', 'terminkalender' ),
					'add_new_item'       => __( 'Neuen Termin anlegen', 'terminkalender' ),
					'edit_item'          => __( 'Termin bearbeiten', 'terminkalender' ),
					'new_item'           => __( 'Neuer Termin', 'terminkalender' ),
					'view_item'          => __( 'Termin ansehen', 'terminkalender' ),
					'search_items'       => __( 'Termine durchsuchen', 'terminkalender' ),
					'not_found'          => __( 'Keine Termine gefunden.', 'terminkalender' ),
					'not_found_in_trash' => __( 'Keine Termine im Papierkorb.', 'terminkalender' ),
					'featured_image'     => __( 'Bild', 'terminkalender' ),
					'set_featured_image' => __( 'Bild auswählen', 'terminkalender' ),
					'remove_featured_image' => __( 'Bild entfernen', 'terminkalender' ),
				],
				'public'              => true,
				'show_in_rest'        => false, // Einfacher, klassischer Editor.
				'menu_icon'           => 'dashicons-calendar-alt',
				'menu_position'       => 5,
				'supports'            => [ 'title', 'editor', 'thumbnail' ],
				'has_archive'         => false,
				'rewrite'             => [
					'slug'       => TK_Settings::get( 'single_slug' ),
					'with_front' => false,
				],
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			]
		);

		register_taxonomy(
			self::TAX_CAT,
			self::CPT,
			[
				'labels'            => [
					'name'          => __( 'Kategorien', 'terminkalender' ),
					'singular_name' => __( 'Kategorie', 'terminkalender' ),
					'add_new_item'  => __( 'Neue Kategorie', 'terminkalender' ),
					'edit_item'     => __( 'Kategorie bearbeiten', 'terminkalender' ),
					'search_items'  => __( 'Kategorien durchsuchen', 'terminkalender' ),
					'not_found'     => __( 'Keine Kategorien gefunden.', 'terminkalender' ),
				],
				'hierarchical'      => true, // Ankreuzfelder statt Schlagwort-Eingabe.
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'rewrite'           => false,
			]
		);

		register_taxonomy(
			self::TAX_PLACE,
			self::CPT,
			[
				'labels'       => [
					'name'          => __( 'Orte', 'terminkalender' ),
					'singular_name' => __( 'Ort', 'terminkalender' ),
					'add_new_item'  => __( 'Neuer Ort', 'terminkalender' ),
					'edit_item'     => __( 'Ort bearbeiten', 'terminkalender' ),
					'search_items'  => __( 'Orte durchsuchen', 'terminkalender' ),
					'not_found'     => __( 'Keine Orte gefunden.', 'terminkalender' ),
				],
				'hierarchical' => false,
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'meta_box_cb'  => false, // Auswahl erfolgt in der Termin-Box.
				'rewrite'      => false,
			]
		);

		register_taxonomy(
			self::TAX_ORG,
			self::CPT,
			[
				'labels'       => [
					'name'          => __( 'Veranstalter', 'terminkalender' ),
					'singular_name' => __( 'Veranstalter', 'terminkalender' ),
					'add_new_item'  => __( 'Neuer Veranstalter', 'terminkalender' ),
					'edit_item'     => __( 'Veranstalter bearbeiten', 'terminkalender' ),
					'search_items'  => __( 'Veranstalter durchsuchen', 'terminkalender' ),
					'not_found'     => __( 'Keine Veranstalter gefunden.', 'terminkalender' ),
				],
				'hierarchical' => false,
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'meta_box_cb'  => false,
				'rewrite'      => false,
			]
		);
	}

	/**
	 * Permalinks neu aufbauen, wenn die Adresse der Terminseiten geändert wurde.
	 */
	public static function maybe_flush() {
		if ( get_option( 'tk_flush_rewrite' ) ) {
			delete_option( 'tk_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/* ------------------------------------------------------------------ *
	 * Zwischenspeicher
	 * ------------------------------------------------------------------ */

	public static function generation() {
		return (int) get_option( self::GEN_OPTION, 1 );
	}

	public static function bump() {
		update_option( self::GEN_OPTION, self::generation() + 1, true );
	}

	/**
	 * @param int $post_id Beitrag.
	 */
	public static function bump_if_ours( $post_id ) {
		if ( self::CPT === get_post_type( $post_id ) ) {
			self::bump();
		}
	}

	/* ------------------------------------------------------------------ *
	 * Termindaten lesen und schreiben
	 * ------------------------------------------------------------------ */

	/**
	 * Alle Termindaten eines Beitrags.
	 *
	 * @param int $post_id Termin.
	 * @return array<string,mixed>
	 */
	public static function get( $post_id ) {
		$rule = get_post_meta( $post_id, self::META_RULE, true );

		return [
			'start'  => (string) get_post_meta( $post_id, self::META_START, true ),
			'end'    => (string) get_post_meta( $post_id, self::META_END, true ),
			'allday' => (bool) get_post_meta( $post_id, self::META_ALLDAY, true ),
			'rule'   => is_array( $rule ) && ! empty( $rule['freq'] ) ? $rule : null,
		];
	}

	/**
	 * Speichert Termindaten und berechnet den letzten Tag.
	 *
	 * @param int                 $post_id Termin.
	 * @param string              $start   Y-m-d H:i.
	 * @param string              $end     Y-m-d H:i.
	 * @param bool                $allday  Ganztägig.
	 * @param array<string,mixed>|null $rule Serie.
	 */
	public static function save( $post_id, $start, $end, $allday, $rule ) {
		if ( $allday ) {
			$start = substr( $start, 0, 10 ) . ' 00:00';
			$end   = substr( $end, 0, 10 ) . ' 23:59';
		}
		if ( $end < $start ) {
			$end = $start;
		}

		update_post_meta( $post_id, self::META_START, $start );
		update_post_meta( $post_id, self::META_END, $end );
		update_post_meta( $post_id, self::META_ALLDAY, $allday ? '1' : '' );

		$rule = self::clean_rule( $rule, $start );
		if ( $rule ) {
			update_post_meta( $post_id, self::META_RULE, $rule );
			// Letzter Tag der Serie = Bis-Datum plus Dauer eines Termins.
			$days = (int) floor( ( self::ts( substr( $end, 0, 10 ) . ' 00:00' ) - self::ts( substr( $start, 0, 10 ) . ' 00:00' ) ) / DAY_IN_SECONDS );
			$last = gmdate( 'Y-m-d', self::ts( $rule['until'] . ' 00:00' ) + $days * DAY_IN_SECONDS );
		} else {
			delete_post_meta( $post_id, self::META_RULE );
			$last = substr( $end, 0, 10 );
		}

		update_post_meta( $post_id, self::META_LAST, $last );
		self::bump();
	}

	/**
	 * Prüft und vereinheitlicht eine Serie.
	 *
	 * @param mixed  $rule  Eingabe.
	 * @param string $start Beginn.
	 * @return array<string,mixed>|null
	 */
	public static function clean_rule( $rule, $start ) {
		if ( ! is_array( $rule ) || empty( $rule['freq'] ) ) {
			return null;
		}

		$freq = (string) $rule['freq'];
		if ( ! in_array( $freq, [ 'weekly', 'monthly_day', 'monthly_nth', 'monthly_last' ], true ) ) {
			return null;
		}

		$interval = isset( $rule['interval'] ) ? max( 1, min( 12, (int) $rule['interval'] ) ) : 1;
		$until    = isset( $rule['until'] ) ? (string) $rule['until'] : '';
		$first    = substr( $start, 0, 10 );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) || $until <= $first ) {
			// Ohne gültiges Ende: ein Jahr ab Beginn.
			$until = gmdate( 'Y-m-d', self::ts( $first . ' 00:00' ) + 365 * DAY_IN_SECONDS );
		}
		// Höchstens fünf Jahre.
		$max = gmdate( 'Y-m-d', self::ts( $first . ' 00:00' ) + 5 * 366 * DAY_IN_SECONDS );
		if ( $until > $max ) {
			$until = $max;
		}

		$ex = [];
		if ( ! empty( $rule['exdates'] ) && is_array( $rule['exdates'] ) ) {
			foreach ( $rule['exdates'] as $d ) {
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d ) ) {
					$ex[] = (string) $d;
				}
			}
		}
		$ex = array_values( array_unique( $ex ) );
		sort( $ex );

		$clean = [
			'freq'     => $freq,
			'interval' => $interval,
			'until'    => $until,
			'exdates'  => $ex,
		];

		// Wöchentlich: Wochentage (0 = Sonntag … 6 = Samstag). Der Tag des Beginns ist immer dabei.
		if ( 'weekly' === $freq ) {
			$days = [ (int) gmdate( 'w', self::ts( $first . ' 00:00' ) ) ];
			if ( ! empty( $rule['days'] ) && is_array( $rule['days'] ) ) {
				foreach ( $rule['days'] as $d ) {
					if ( is_numeric( $d ) && (int) $d >= 0 && (int) $d <= 6 ) {
						$days[] = (int) $d;
					}
				}
			}
			$days = array_values( array_unique( $days ) );
			sort( $days );
			$clean['days'] = $days;
		}

		return $clean;
	}

	/**
	 * Wochentage einer wöchentlichen Serie (0 = Sonntag … 6 = Samstag).
	 *
	 * @param array<string,mixed> $rule  Serie.
	 * @param string              $start Beginn.
	 * @return int[]
	 */
	public static function rule_days( $rule, $start ) {
		if ( ! empty( $rule['days'] ) && is_array( $rule['days'] ) ) {
			return array_map( 'intval', $rule['days'] );
		}
		return [ (int) gmdate( 'w', self::ts( $start ) ) ];
	}

	/* ------------------------------------------------------------------ *
	 * Serien
	 * ------------------------------------------------------------------ */

	/**
	 * Zeitstempel einer Ortszeit, gerechnet in UTC (keine Sommerzeit-Sprünge).
	 *
	 * @param string $local Y-m-d H:i.
	 * @return int
	 */
	public static function ts( $local ) {
		$d = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', substr( $local, 0, 16 ), new DateTimeZone( 'UTC' ) );

		return $d ? $d->getTimestamp() : 0;
	}

	/**
	 * Datum formatieren (Wochentage/Monate in Sprache der Website).
	 *
	 * @param string $format Format.
	 * @param int    $ts     Zeitstempel aus ts().
	 * @return string
	 */
	public static function fmt( $format, $ts ) {
		return (string) wp_date( $format, $ts, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Alle Vorkommen eines Termins, die den Zeitraum berühren.
	 *
	 * @param array<string,mixed> $data  Ergebnis von get().
	 * @param string              $from  Y-m-d.
	 * @param string              $to    Y-m-d.
	 * @return array<int,array{start:string,end:string}>
	 */
	public static function occurrences( $data, $from, $to ) {
		if ( empty( $data['start'] ) ) {
			return [];
		}

		$start = $data['start'];
		$end   = $data['end'] ? $data['end'] : $start;
		$dur   = max( 0, self::ts( $end ) - self::ts( $start ) );
		$rule  = $data['rule'];
		$utc   = new DateTimeZone( 'UTC' );
		$out   = [];

		$overlaps = static function ( $s_ts, $e_ts ) use ( $from, $to ) {
			return gmdate( 'Y-m-d', $s_ts ) <= $to && gmdate( 'Y-m-d', $e_ts ) >= $from;
		};

		if ( ! $rule ) {
			$s = self::ts( $start );
			if ( $overlaps( $s, $s + $dur ) ) {
				$out[] = [
					'start' => $start,
					'end'   => $end,
				];
			}
			return $out;
		}

		$base  = new DateTimeImmutable( '@' . self::ts( $start ) );
		$base  = $base->setTimezone( $utc );
		$until = $rule['until'];
		$ex    = isset( $rule['exdates'] ) ? $rule['exdates'] : [];
		$step  = max( 1, (int) $rule['interval'] );

		$day     = (int) $base->format( 'j' );
		$weekday = (int) $base->format( 'w' );
		$nth     = (int) ceil( $day / 7 );
		$time    = $base->format( 'H:i' );
		$first   = substr( $start, 0, 10 );

		// Wöchentlich, auch an mehreren Tagen (z. B. Dienstag und Donnerstag).
		if ( 'weekly' === $rule['freq'] ) {
			$offsets = [];
			foreach ( self::rule_days( $rule, $start ) as $d ) {
				$offsets[] = ( $d + 6 ) % 7; // Abstand zum Montag.
			}
			$offsets = array_values( array_unique( $offsets ) );
			sort( $offsets );

			$monday = $base->modify( '-' . ( ( $weekday + 6 ) % 7 ) . ' days' );

			for ( $w = 0; $w < 2000 && count( $out ) < self::MAX_OCCURRENCES; $w++ ) {
				$week = $monday->modify( '+' . ( $w * 7 * $step ) . ' days' );
				if ( $week->format( 'Y-m-d' ) > $until || $week->format( 'Y-m-d' ) > $to ) {
					break;
				}
				foreach ( $offsets as $o ) {
					$occ  = $week->modify( '+' . $o . ' days' );
					$date = $occ->format( 'Y-m-d' );
					if ( $date > $until || $date > $to ) {
						break 2;
					}
					if ( $date < $first || in_array( $date, $ex, true ) ) {
						continue;
					}
					$s_ts = $occ->getTimestamp();
					if ( $overlaps( $s_ts, $s_ts + $dur ) ) {
						$out[] = [
							'start' => gmdate( 'Y-m-d H:i', $s_ts ),
							'end'   => gmdate( 'Y-m-d H:i', $s_ts + $dur ),
						];
					}
				}
			}

			return $out;
		}

		for ( $i = 0; $i < 5000 && count( $out ) < self::MAX_OCCURRENCES; $i++ ) {
			// Monatlich.
			$occ         = null;
			$month_first = $base->modify( 'first day of this month' )->modify( '+' . ( $i * $step ) . ' months' );
			$y           = (int) $month_first->format( 'Y' );
			$m           = (int) $month_first->format( 'n' );
			$days_in     = (int) $month_first->format( 't' );

			if ( 'monthly_day' === $rule['freq'] ) {
				if ( $day <= $days_in ) {
					$occ = self::make( $y, $m, $day, $time );
				}
			} elseif ( 'monthly_nth' === $rule['freq'] ) {
				$first_wd = (int) self::make( $y, $m, 1, $time )->format( 'w' );
				$d        = 1 + ( ( $weekday - $first_wd + 7 ) % 7 ) + ( $nth - 1 ) * 7;
				if ( $d <= $days_in ) {
					$occ = self::make( $y, $m, $d, $time );
				}
			} else { // monthly_last
				$last_wd = (int) self::make( $y, $m, $days_in, $time )->format( 'w' );
				$d       = $days_in - ( ( $last_wd - $weekday + 7 ) % 7 );
				$occ     = self::make( $y, $m, $d, $time );
			}

			if ( ! $occ ) {
				if ( sprintf( '%04d-%02d-01', $y, $m ) > $until ) {
					break;
				}
				continue;
			}

			$date = $occ->format( 'Y-m-d' );
			if ( $date > $until || $date > $to ) {
				break;
			}
			if ( $date < substr( $start, 0, 10 ) || in_array( $date, $ex, true ) ) {
				continue;
			}

			$s_ts = $occ->getTimestamp();
			if ( $overlaps( $s_ts, $s_ts + $dur ) ) {
				$out[] = [
					'start' => gmdate( 'Y-m-d H:i', $s_ts ),
					'end'   => gmdate( 'Y-m-d H:i', $s_ts + $dur ),
				];
			}
		}

		return $out;
	}

	/**
	 * @param int    $y    Jahr.
	 * @param int    $m    Monat.
	 * @param int    $d    Tag.
	 * @param string $time H:i.
	 * @return DateTimeImmutable
	 */
	private static function make( $y, $m, $d, $time ) {
		return new DateTimeImmutable( sprintf( '%04d-%02d-%02d %s', $y, $m, $d, $time ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Kurze Beschreibung einer Serie, z. B. „jeden 2. Samstag im Monat bis 31.12.2027“.
	 *
	 * @param array<string,mixed> $data Termindaten.
	 * @return string
	 */
	public static function rule_text( $data ) {
		$rule = $data['rule'];
		if ( ! $rule ) {
			return '';
		}

		$ts       = self::ts( $data['start'] );
		$weekday  = self::fmt( 'l', $ts );
		$day      = (int) gmdate( 'j', $ts );
		$nth      = (int) ceil( $day / 7 );
		$interval = (int) $rule['interval'];
		$until    = self::fmt( 'j. F Y', self::ts( $rule['until'] . ' 00:00' ) );

		switch ( $rule['freq'] ) {
			case 'weekly':
				$names = self::weekday_list( self::rule_days( $rule, $data['start'] ) );
				/* translators: %s: Wochentag(e), z. B. „Dienstag und Donnerstag“. */
				$text = 1 === $interval ? sprintf( __( 'jeden %s', 'terminkalender' ), $names )
					/* translators: 1: Anzahl Wochen, 2: Wochentag(e). */
					: sprintf( __( 'alle %1$d Wochen am %2$s', 'terminkalender' ), $interval, $names );
				break;
			case 'monthly_day':
				/* translators: %d: Tag im Monat. */
				$text = sprintf( __( 'jeden Monat am %d.', 'terminkalender' ), $day );
				break;
			case 'monthly_nth':
				/* translators: 1: Nummer (1–4), 2: Wochentag. */
				$text = sprintf( __( 'jeden %1$d. %2$s im Monat', 'terminkalender' ), $nth, $weekday );
				break;
			default:
				/* translators: %s: Wochentag. */
				$text = sprintf( __( 'jeden letzten %s im Monat', 'terminkalender' ), $weekday );
		}

		/* translators: 1: Serienbeschreibung, 2: Enddatum. */
		return sprintf( __( '%1$s bis %2$s', 'terminkalender' ), $text, $until );
	}

	/**
	 * „Dienstag und Donnerstag“ bzw. „Montag, Mittwoch und Freitag“ (Montag zuerst).
	 *
	 * @param int[] $days Wochentage (0 = Sonntag).
	 * @return string
	 */
	public static function weekday_list( $days ) {
		usort(
			$days,
			static function ( $a, $b ) {
				return ( ( $a + 6 ) % 7 ) - ( ( $b + 6 ) % 7 );
			}
		);
		// 7. Januar 2024 war ein Sonntag.
		$names = array_map(
			static function ( $d ) {
				return self::fmt( 'l', self::ts( '2024-01-07 12:00' ) + $d * DAY_IN_SECONDS );
			},
			$days
		);
		if ( count( $names ) < 2 ) {
			return (string) reset( $names );
		}
		$last = array_pop( $names );
		/* translators: 1: Aufzählung, 2: letzter Eintrag. */
		return sprintf( __( '%1$s und %2$s', 'terminkalender' ), implode( ', ', $names ), $last );
	}

	/* ------------------------------------------------------------------ *
	 * Abfragen
	 * ------------------------------------------------------------------ */

	/**
	 * IDs aller veröffentlichten Termine, die den Zeitraum berühren können.
	 *
	 * @param string $from Y-m-d.
	 * @param string $to   Y-m-d.
	 * @return int[]
	 */
	public static function query_ids( $from, $to ) {
		$q = new WP_Query(
			[
				'post_type'              => self::CPT,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
				'update_post_meta_cache' => true,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					[
						'key'     => self::META_START,
						'value'   => $to . ' 23:59',
						'compare' => '<=',
					],
					[
						'key'     => self::META_LAST,
						'value'   => $from,
						'compare' => '>=',
					],
				],
			]
		);

		return array_map( 'intval', $q->posts );
	}

	/**
	 * Nächste Vorkommen ab heute (für Detailseite).
	 *
	 * @param int $post_id Termin.
	 * @param int $limit   Anzahl.
	 * @return array<int,array{start:string,end:string}>
	 */
	public static function upcoming( $post_id, $limit = 6 ) {
		$data  = self::get( $post_id );
		$today = current_datetime()->format( 'Y-m-d' );
		$to    = $data['rule'] ? $data['rule']['until'] : substr( $data['end'], 0, 10 );
		if ( $to < $today ) {
			return [];
		}

		return array_slice( self::occurrences( $data, $today, $to ), 0, $limit );
	}

	/* ------------------------------------------------------------------ *
	 * Kategorien, Orte, Veranstalter
	 * ------------------------------------------------------------------ */

	/**
	 * Alle Kategorien mit Farbe (Farbe bleibt stabil, auch wenn eine Kategorie leer ist).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function categories() {
		$terms = get_terms(
			[
				'taxonomy'   => self::TAX_CAT,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		$data = [];
		if ( is_wp_error( $terms ) ) {
			return $data;
		}

		$i = 0;
		foreach ( $terms as $term ) {
			$own    = sanitize_hex_color( (string) get_term_meta( $term->term_id, 'tk_farbe', true ) );
			$data[] = [
				'id'    => (int) $term->term_id,
				'slug'  => $term->slug,
				'name'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'color' => $own ? $own : self::$palette[ $i % count( self::$palette ) ],
				'count' => (int) $term->count,
			];
			$i++;
		}

		return $data;
	}

	/**
	 * Erster Begriff einer Taxonomie am Termin.
	 *
	 * @param int    $post_id Termin.
	 * @param string $tax     Taxonomie.
	 * @return WP_Term|null
	 */
	public static function first_term( $post_id, $tax ) {
		$terms = get_the_terms( $post_id, $tax );

		return ( empty( $terms ) || is_wp_error( $terms ) ) ? null : reset( $terms );
	}

	/**
	 * Ort mit Adresse.
	 *
	 * @param int $post_id Termin.
	 * @return array<string,string>|null
	 */
	public static function place( $post_id ) {
		$term = self::first_term( $post_id, self::TAX_PLACE );
		if ( ! $term ) {
			// Frei eingegebener Ort (nicht in der Ortsliste gespeichert).
			$free = self::free_place( $post_id );
			if ( ! $free ) {
				return null;
			}
			return [
				'name'   => $free['name'],
				'street' => $free['address'],
				'zip'    => '',
				'city'   => '',
				'free'   => true,
			];
		}

		return [
			'name'   => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			'street' => (string) get_term_meta( $term->term_id, 'tk_strasse', true ),
			'zip'    => (string) get_term_meta( $term->term_id, 'tk_plz', true ),
			'city'   => (string) get_term_meta( $term->term_id, 'tk_stadt', true ),
		];
	}

	/**
	 * Frei eingegebener Ort eines Termins.
	 *
	 * @param int $post_id Termin.
	 * @return array{name:string,address:string}|null
	 */
	public static function free_place( $post_id ) {
		$free = get_post_meta( $post_id, self::META_PLACE, true );
		if ( ! is_array( $free ) || empty( $free['name'] ) ) {
			return null;
		}
		return [
			'name'    => (string) $free['name'],
			'address' => isset( $free['address'] ) ? (string) $free['address'] : '',
		];
	}

	/**
	 * Veranstalter mit Kontakt.
	 *
	 * @param int $post_id Termin.
	 * @return array<string,string>|null
	 */
	public static function organizer( $post_id ) {
		$term = self::first_term( $post_id, self::TAX_ORG );
		if ( ! $term ) {
			return null;
		}

		return [
			'name'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			'phone' => (string) get_term_meta( $term->term_id, 'tk_telefon', true ),
			'email' => (string) get_term_meta( $term->term_id, 'tk_email', true ),
			'web'   => (string) get_term_meta( $term->term_id, 'tk_web', true ),
		];
	}
}
