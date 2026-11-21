<?php
/**
 * Datenbank-Export, -Import, Suchen & Ersetzen sowie Tabellentausch.
 *
 * Wichtig: Der Import erfolgt zuerst in temporäre Tabellen (Präfix wpkbtmp_).
 * Die Live-Tabellen werden erst ganz am Ende in einem einzigen, atomaren
 * RENAME TABLE ausgetauscht. Bricht die Wiederherstellung vorher ab, bleibt
 * die bestehende Datenbank unverändert.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPKB_Database {

	const PLACEHOLDER   = '{{WPKB_PREFIX}}';
	const TMP_PREFIX    = 'wpkbtmp_';
	const OLD_PREFIX    = 'wpkbold_';
	const ROWS_PER_READ = 300;
	const MAX_STMT      = 524288; // 512 KB pro INSERT (unter max_allowed_packet)

	/* ------------------------------------------------------------------ */
	/* Low-Level                                                           */
	/* ------------------------------------------------------------------ */

	private static function dbh() {
		global $wpdb;
		return ( isset( $wpdb->dbh ) && $wpdb->dbh instanceof mysqli ) ? $wpdb->dbh : null;
	}

	/**
	 * Führt SQL ohne die Zeichensatz-Filter von $wpdb aus (die bei großen
	 * oder binären Statements fehlschlagen bzw. sehr langsam sind).
	 */
	public static function exec( $sql ) {
		global $wpdb;
		$dbh = self::dbh();
		if ( $dbh ) {
			try {
				$res = mysqli_query( $dbh, $sql );
			} catch ( mysqli_sql_exception $e ) {
				return array( 'ok' => false, 'errno' => $e->getCode(), 'error' => $e->getMessage() );
			}
			if ( false === $res ) {
				return array( 'ok' => false, 'errno' => mysqli_errno( $dbh ), 'error' => mysqli_error( $dbh ) );
			}
			if ( $res instanceof mysqli_result ) {
				mysqli_free_result( $res );
			}
			return array( 'ok' => true );
		}
		$res = $wpdb->query( $sql ); // phpcs:ignore
		return false === $res
			? array( 'ok' => false, 'errno' => 0, 'error' => $wpdb->last_error )
			: array( 'ok' => true );
	}

	public static function must( $sql ) {
		$r = self::exec( $sql );
		if ( ! $r['ok'] ) {
			throw new Exception( 'Datenbankfehler: ' . $r['error'] . ' – SQL: ' . self::short( $sql ) );
		}
	}

	public static function esc( $value ) {
		global $wpdb;
		$dbh = self::dbh();
		if ( $dbh ) {
			return mysqli_real_escape_string( $dbh, (string) $value );
		}
		return esc_sql( (string) $value );
	}

	public static function quote_ident( $name ) {
		return '`' . str_replace( '`', '``', $name ) . '`';
	}

	private static function short( $sql ) {
		return strlen( $sql ) > 300 ? substr( $sql, 0, 300 ) . ' …' : $sql;
	}

	public static function tables_with_prefix( $prefix ) {
		global $wpdb;
		$like = $wpdb->esc_like( $prefix ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SHOW FULL TABLES LIKE %s', $like ), ARRAY_N ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			if ( ! isset( $r[1] ) || 'BASE TABLE' === strtoupper( $r[1] ) ) {
				$out[] = $r[0];
			}
		}
		sort( $out );
		return $out;
	}

	public static function drop_with_prefix( $prefix ) {
		foreach ( self::tables_with_prefix( $prefix ) as $t ) {
			self::must( 'DROP TABLE IF EXISTS ' . self::quote_ident( $t ) );
		}
	}

	/**
	 * Spalten und Primärschlüssel einer Tabelle.
	 */
	public static function table_info( $table ) {
		global $wpdb;
		$cols = $wpdb->get_results( 'SHOW COLUMNS FROM ' . self::quote_ident( $table ), ARRAY_A ); // phpcs:ignore
		$keys = $wpdb->get_results( 'SHOW KEYS FROM ' . self::quote_ident( $table ) . " WHERE Key_name = 'PRIMARY'", ARRAY_A ); // phpcs:ignore
		usort(
			$keys,
			function ( $a, $b ) {
				return (int) $a['Seq_in_index'] - (int) $b['Seq_in_index'];
			}
		);
		$info = array(
			'cols'   => array(),
			'binary' => array(),
			'text'   => array(),
			'pk'     => array(),
		);
		foreach ( (array) $cols as $c ) {
			$type           = strtolower( $c['Type'] );
			$info['cols'][] = $c['Field'];
			if ( preg_match( '/blob|binary|^bit|geometry|point|linestring|polygon/', $type ) ) {
				$info['binary'][] = $c['Field'];
			} elseif ( preg_match( '/char|text|json/', $type ) ) {
				$info['text'][] = $c['Field'];
			}
		}
		foreach ( (array) $keys as $k ) {
			$info['pk'][] = $k['Column_name'];
		}
		return $info;
	}

	/* ------------------------------------------------------------------ */
	/* Export                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Exportiert Tabellen in Häppchen. Jede SQL-Anweisung steht in genau
	 * einer Zeile (Zeilenumbrüche in Werten werden maskiert).
	 *
	 * @return bool true, wenn fertig.
	 */
	public static function export_step( array &$st, $file, $deadline ) {
		global $wpdb;
		$fh = fopen( $file, 'ab' );
		if ( ! $fh ) {
			throw new Exception( 'SQL-Datei kann nicht geschrieben werden: ' . $file );
		}

		while ( $st['t'] < count( $st['tables'] ) ) {
			$table = $st['tables'][ $st['t'] ];

			if ( empty( $st['started'] ) ) {
				$row = $wpdb->get_row( 'SHOW CREATE TABLE ' . self::quote_ident( $table ), ARRAY_N ); // phpcs:ignore
				if ( empty( $row[1] ) ) {
					throw new Exception( 'CREATE TABLE für ' . $table . ' konnte nicht gelesen werden.' );
				}
				$base   = substr( $table, strlen( $st['prefix'] ) );
				$create = str_replace( array( "\r\n", "\n", "\r" ), ' ', $row[1] );
				$create = preg_replace(
					'/^CREATE TABLE `' . preg_quote( $table, '/' ) . '`/',
					'CREATE TABLE `' . self::PLACEHOLDER . $base . '`',
					$create
				);
				$create = str_replace( 'REFERENCES `' . $st['prefix'], 'REFERENCES `' . self::PLACEHOLDER, $create );

				fwrite( $fh, "-- Tabelle {$table}\n" );
				fwrite( $fh, 'DROP TABLE IF EXISTS `' . self::PLACEHOLDER . $base . "`;\n" );
				fwrite( $fh, $create . ";\n" );

				$st['started'] = true;
				$st['info']    = self::table_info( $table );
				$st['last']    = null;
				$st['offset']  = 0;
				$st['rows']    = 0;
			}

			$info   = $st['info'];
			$keyset = 1 === count( $info['pk'] );
			$q      = 'SELECT * FROM ' . self::quote_ident( $table );
			if ( $keyset ) {
				$pk = self::quote_ident( $info['pk'][0] );
				if ( null !== $st['last'] ) {
					$q .= " WHERE {$pk} > '" . self::esc( $st['last'] ) . "'";
				}
				$q .= " ORDER BY {$pk} ASC LIMIT " . self::ROWS_PER_READ;
			} else {
				$order = $info['pk'] ? ' ORDER BY ' . implode( ',', array_map( array( __CLASS__, 'quote_ident' ), $info['pk'] ) ) : '';
				$q    .= $order . ' LIMIT ' . (int) $st['offset'] . ', ' . self::ROWS_PER_READ;
			}
			$rows = $wpdb->get_results( $q, ARRAY_N ); // phpcs:ignore
			if ( $wpdb->last_error ) {
				throw new Exception( 'Fehler beim Lesen von ' . $table . ': ' . $wpdb->last_error );
			}

			if ( empty( $rows ) ) {
				fwrite( $fh, "-- Ende {$table} ({$st['rows']} Zeilen)\n" );
				$st['t']++;
				$st['started'] = false;
				continue;
			}

			$bin_idx = array();
			foreach ( $info['binary'] as $bc ) {
				$bin_idx[ array_search( $bc, $info['cols'], true ) ] = true;
			}
			$pk_idx = $keyset ? array_search( $info['pk'][0], $info['cols'], true ) : null;

			$head   = 'INSERT INTO `' . self::PLACEHOLDER . substr( $table, strlen( $st['prefix'] ) ) . '` VALUES ';
			$values = array();
			$len    = 0;
			foreach ( $rows as $r ) {
				$vals = array();
				foreach ( $r as $i => $v ) {
					$vals[] = self::sql_value( $v, isset( $bin_idx[ $i ] ) );
				}
				$tuple = '(' . implode( ',', $vals ) . ')';
				if ( $values && $len + strlen( $tuple ) > self::MAX_STMT ) {
					fwrite( $fh, $head . implode( ',', $values ) . ";\n" );
					$values = array();
					$len    = 0;
				}
				$values[] = $tuple;
				$len     += strlen( $tuple ) + 1;
				if ( $keyset ) {
					$st['last'] = $r[ $pk_idx ];
				}
			}
			if ( $values ) {
				fwrite( $fh, $head . implode( ',', $values ) . ";\n" );
			}
			$st['offset'] += count( $rows );
			$st['rows']   += count( $rows );

			if ( microtime( true ) > $deadline ) {
				fclose( $fh );
				return false;
			}
		}
		fclose( $fh );
		return true;
	}

	private static function sql_value( $v, $binary ) {
		if ( null === $v ) {
			return 'NULL';
		}
		if ( $binary ) {
			return '' === $v ? "''" : '0x' . bin2hex( $v );
		}
		return "'" . strtr(
			$v,
			array(
				'\\'   => '\\\\',
				"\0"   => '\\0',
				"\n"   => '\\n',
				"\r"   => '\\r',
				"'"    => "\\'",
				"\x1a" => '\\Z',
			)
		) . "'";
	}

	/* ------------------------------------------------------------------ */
	/* Import                                                              */
	/* ------------------------------------------------------------------ */

	private static function session_setup( $charset ) {
		$dbh = self::dbh();
		if ( $dbh && $charset && preg_match( '/^[a-z0-9_]+$/i', $charset ) ) {
			@mysqli_set_charset( $dbh, $charset ); // phpcs:ignore
		}
		self::exec( "SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'" );
		self::exec( 'SET SESSION FOREIGN_KEY_CHECKS = 0' );
		self::exec( 'SET SESSION UNIQUE_CHECKS = 0' );
	}

	/**
	 * @return bool true, wenn fertig.
	 */
	public static function import_step( array &$st, $file, $charset, $deadline ) {
		$fh = fopen( $file, 'rb' );
		if ( ! $fh ) {
			throw new Exception( 'SQL-Datei nicht lesbar.' );
		}
		self::session_setup( $charset );
		fseek( $fh, $st['pos'] );

		while ( ( $line = fgets( $fh ) ) !== false ) {
			$next = ftell( $fh );
			$stmt = rtrim( $line, "\r\n" );
			if ( '' !== $stmt && 0 !== strpos( $stmt, '--' ) ) {
				self::run_import_statement( self::retarget( $stmt ) );
				$st['count'] = ( $st['count'] ?? 0 ) + 1;
			}
			$st['pos'] = $next;
			if ( microtime( true ) > $deadline ) {
				fclose( $fh );
				return false;
			}
		}
		fclose( $fh );
		return true;
	}

	private static function retarget( $stmt ) {
		$ins = 'INSERT INTO `' . self::PLACEHOLDER;
		if ( 0 === strpos( $stmt, $ins ) ) {
			return 'INSERT INTO `' . self::TMP_PREFIX . substr( $stmt, strlen( $ins ) );
		}
		if ( 0 === strpos( $stmt, 'CREATE TABLE' ) || 0 === strpos( $stmt, 'DROP TABLE' ) ) {
			return str_replace( '`' . self::PLACEHOLDER, '`' . self::TMP_PREFIX, $stmt );
		}
		return $stmt;
	}

	private static function run_import_statement( $sql ) {
		$r = self::exec( $sql );
		if ( $r['ok'] ) {
			return;
		}
		// MySQL-8-Kollationen (utf8mb4_0900_*) sind z. B. auf MariaDB unbekannt.
		if ( 0 === strpos( $sql, 'CREATE TABLE' ) && in_array( (int) $r['errno'], array( 1273, 1115 ), true ) ) {
			$fixed = preg_replace( '/utf8mb4_0900_[a-z_]+/i', 'utf8mb4_unicode_520_ci', $sql );
			$r     = self::exec( $fixed );
			if ( ! $r['ok'] && in_array( (int) $r['errno'], array( 1273, 1115 ), true ) ) {
				$fixed = preg_replace( '/utf8mb4_unicode_520_ci/i', 'utf8mb4_unicode_ci', $fixed );
				$r     = self::exec( $fixed );
			}
			if ( ! $r['ok'] && in_array( (int) $r['errno'], array( 1273, 1115 ), true ) ) {
				$fixed = str_ireplace( 'utf8mb4', 'utf8', $fixed );
				$r     = self::exec( $fixed );
			}
			if ( $r['ok'] ) {
				return;
			}
		}
		throw new Exception( 'Import fehlgeschlagen: ' . $r['error'] . ' – SQL: ' . self::short( $sql ) );
	}

	/* ------------------------------------------------------------------ */
	/* Suchen & Ersetzen                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * @return bool true, wenn fertig.
	 */
	public static function replace_step( array &$st, WPKB_Replacer $rep, array $needles, $deadline ) {
		global $wpdb;

		while ( $st['t'] < count( $st['tables'] ) ) {
			$table = $st['tables'][ $st['t'] ];
			if ( empty( $st['started'] ) ) {
				$st['info']    = self::table_info( $table );
				$st['last']    = null;
				$st['offset']  = 0;
				$st['started'] = true;
			}
			$info = $st['info'];
			if ( empty( $info['text'] ) ) {
				$st['t']++;
				$st['started'] = false;
				continue;
			}

			$t      = self::quote_ident( $table );
			$keyset = 1 === count( $info['pk'] );

			if ( $keyset ) {
				$pk    = $info['pk'][0];
				$qpk   = self::quote_ident( $pk );
				$likes = array();
				foreach ( $info['text'] as $c ) {
					foreach ( $needles as $n ) {
						$likes[] = self::quote_ident( $c ) . " LIKE '%" . self::esc( $wpdb->esc_like( $n ) ) . "%'";
					}
				}
				$cols  = array_unique( array_merge( array( $pk ), $info['text'] ) );
				$q     = 'SELECT ' . implode( ',', array_map( array( __CLASS__, 'quote_ident' ), $cols ) ) . " FROM {$t} WHERE ";
				$q    .= $likes ? '(' . implode( ' OR ', $likes ) . ')' : '1=1';
				if ( null !== $st['last'] ) {
					$q .= " AND {$qpk} > '" . self::esc( $st['last'] ) . "'";
				}
				$q .= " ORDER BY {$qpk} ASC LIMIT 200";
			} else {
				$order = $info['pk'] ? ' ORDER BY ' . implode( ',', array_map( array( __CLASS__, 'quote_ident' ), $info['pk'] ) ) : '';
				$q     = "SELECT * FROM {$t}{$order} LIMIT " . (int) $st['offset'] . ', 200';
			}

			$rows = $wpdb->get_results( $q, ARRAY_A ); // phpcs:ignore
			if ( empty( $rows ) ) {
				$st['t']++;
				$st['started'] = false;
				continue;
			}

			foreach ( $rows as $row ) {
				$set = array();
				foreach ( $info['text'] as $c ) {
					if ( null === $row[ $c ] || ! $rep->contains_any( $row[ $c ] ) ) {
						continue;
					}
					$new = $rep->replace( $row[ $c ] );
					if ( $new !== $row[ $c ] ) {
						$set[] = self::quote_ident( $c ) . " = '" . self::esc( $new ) . "'";
					}
				}
				if ( $set ) {
					if ( $keyset ) {
						$where = $qpk . " = '" . self::esc( $row[ $pk ] ) . "'";
					} else {
						$w = array();
						foreach ( $row as $c => $v ) {
							$w[] = self::quote_ident( $c ) . ( null === $v ? ' IS NULL' : " <=> '" . self::esc( $v ) . "'" );
						}
						$where = implode( ' AND ', $w );
					}
					self::must( "UPDATE {$t} SET " . implode( ', ', $set ) . " WHERE {$where} LIMIT 1" );
					$st['changed'] = ( $st['changed'] ?? 0 ) + 1;
				}
				if ( $keyset ) {
					$st['last'] = $row[ $pk ];
				}
			}
			$st['offset'] += count( $rows );

			if ( microtime( true ) > $deadline ) {
				return false;
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Abschluss                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Präfix-abhängige Schlüssel anpassen, Caches leeren.
	 */
	public static function prepare_tmp_tables( $src_prefix, $dst_prefix ) {
		global $wpdb;
		$tmp     = self::TMP_PREFIX;
		$options = self::quote_ident( $tmp . 'options' );
		$umeta   = self::quote_ident( $tmp . 'usermeta' );
		$have    = self::tables_with_prefix( $tmp );

		if ( in_array( $tmp . 'options', $have, true ) ) {
			if ( $src_prefix !== $dst_prefix ) {
				self::must( "UPDATE {$options} SET option_name = '" . self::esc( $dst_prefix . 'user_roles' ) . "' WHERE option_name = '" . self::esc( $src_prefix . 'user_roles' ) . "'" );
			}
			self::must( "DELETE FROM {$options} WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'" );
			self::must( "UPDATE {$options} SET option_value = '' WHERE option_name = 'rewrite_rules'" );
		}
		if ( $src_prefix !== $dst_prefix && in_array( $tmp . 'usermeta', $have, true ) ) {
			$like = self::esc( $wpdb->esc_like( $src_prefix ) ) . '%';
			self::must(
				"UPDATE {$umeta} SET meta_key = CONCAT('" . self::esc( $dst_prefix ) . "', SUBSTRING(meta_key, " . ( strlen( $src_prefix ) + 1 ) . ")) WHERE meta_key LIKE '{$like}'"
			);
		}
	}

	/**
	 * Tauscht temporäre und Live-Tabellen atomar aus.
	 */
	public static function swap_tables( array $bases, $dst_prefix ) {
		self::drop_with_prefix( self::OLD_PREFIX );
		$existing = self::tables_with_prefix( $dst_prefix );
		$tmp_have = self::tables_with_prefix( self::TMP_PREFIX );
		$parts    = array();

		foreach ( $bases as $base ) {
			$tmp  = self::TMP_PREFIX . $base;
			$live = $dst_prefix . $base;
			if ( ! in_array( $tmp, $tmp_have, true ) ) {
				continue;
			}
			if ( in_array( $live, $existing, true ) ) {
				$parts[] = self::quote_ident( $live ) . ' TO ' . self::quote_ident( self::OLD_PREFIX . $base );
			}
			$parts[] = self::quote_ident( $tmp ) . ' TO ' . self::quote_ident( $live );
		}
		if ( ! $parts ) {
			throw new Exception( 'Keine importierten Tabellen gefunden – Abbruch, Live-Datenbank unverändert.' );
		}
		self::must( 'RENAME TABLE ' . implode( ', ', $parts ) );
		self::drop_with_prefix( self::OLD_PREFIX );
	}
}
