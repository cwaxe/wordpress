<?php
/**
 * Serialisierungssicheres Suchen & Ersetzen (URLs und Pfade).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPKB_Replacer {

	/** @var array alt => neu */
	private $pairs;

	public function __construct( array $pairs ) {
		$this->pairs = $pairs;
	}

	public function has_pairs() {
		return ! empty( $this->pairs );
	}

	/**
	 * Erzeugt die Ersetzungspaare für einen Umzug.
	 */
	public static function build_pairs( array $from, array $to ) {
		$pairs = array();

		$add_url = function ( $old, $new ) use ( &$pairs ) {
			$old = untrailingslashit( (string) $old );
			$new = untrailingslashit( (string) $new );
			if ( '' === $old || '' === $new ) {
				return;
			}
			$old_bare = preg_replace( '#^https?:#i', '', $old ); // //domain.tld/pfad
			$new_bare = preg_replace( '#^https?:#i', '', $new );
			$variants = array(
				'http:' . $old_bare  => $new,
				'https:' . $old_bare => $new,
				$old_bare            => $new_bare,
			);
			foreach ( $variants as $o => $n ) {
				$pairs[ $o ]                                   = $n;
				$pairs[ str_replace( '/', '\\/', $o ) ]        = str_replace( '/', '\\/', $n ); // JSON
				$pairs[ rawurlencode( $o ) ]                   = rawurlencode( $n );           // URL-kodiert
			}
		};

		$add_url( $from['home'] ?? '', $to['home'] ?? '' );
		$add_url( $from['siteurl'] ?? '', $to['siteurl'] ?? '' );

		foreach ( array( 'content_dir', 'abspath' ) as $key ) {
			$old = untrailingslashit( (string) ( $from[ $key ] ?? '' ) );
			$new = untrailingslashit( (string) ( $to[ $key ] ?? '' ) );
			if ( '' !== $old && '' !== $new && strlen( $old ) > 1 ) {
				$pairs[ $old ]                            = $new;
				$pairs[ str_replace( '/', '\\/', $old ) ] = str_replace( '/', '\\/', $new );
			}
		}

		foreach ( $pairs as $o => $n ) {
			if ( $o === $n || '' === $o ) {
				unset( $pairs[ $o ] );
			}
		}
		return $pairs;
	}

	/**
	 * Kurze Suchbegriffe für einen schnellen LIKE-Vorfilter in SQL.
	 */
	public static function build_needles( array $from ) {
		$needles = array();
		foreach ( array( 'home', 'siteurl' ) as $key ) {
			if ( ! empty( $from[ $key ] ) ) {
				$host = wp_parse_url( $from[ $key ], PHP_URL_HOST );
				if ( $host ) {
					$needles[] = $host;
				}
			}
		}
		foreach ( array( 'content_dir', 'abspath' ) as $key ) {
			if ( ! empty( $from[ $key ] ) && strlen( untrailingslashit( $from[ $key ] ) ) > 1 ) {
				$p         = untrailingslashit( $from[ $key ] );
				$needles[] = $p;
				$needles[] = str_replace( '/', '\\/', $p );
			}
		}
		return array_values( array_unique( $needles ) );
	}

	/**
	 * Ersetzt in einem beliebigen Wert (auch serialisiert, verschachtelt).
	 */
	public function replace( $data ) {
		if ( is_string( $data ) ) {
			return $this->replace_string( $data );
		}
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				$nk         = is_string( $k ) ? $this->replace_string( $k ) : $k;
				$out[ $nk ] = $this->replace( $v );
			}
			return $out;
		}
		if ( is_object( $data ) ) {
			return $this->replace_object( $data );
		}
		return $data;
	}

	private function replace_string( $str ) {
		if ( '' === $str || ! $this->contains_any( $str ) ) {
			return $str; // Unverändert lassen – nichts neu serialisieren.
		}
		if ( $this->looks_serialized( $str ) ) {
			// Keine Klassen instanziieren (Sicherheit) – unvollständige Objekte werden unten nachgebaut.
			$un = @unserialize( $str, array( 'allowed_classes' => false ) ); // phpcs:ignore
			if ( false !== $un || 'b:0;' === $str ) {
				return serialize( $this->replace( $un ) ); // phpcs:ignore
			}
		}
		return strtr( $str, $this->pairs );
	}

	public function contains_any( $str ) {
		foreach ( $this->pairs as $old => $new ) {
			if ( false !== strpos( $str, (string) $old ) ) {
				return true;
			}
		}
		return false;
	}

	private function looks_serialized( $str ) {
		$str = trim( $str );
		if ( strlen( $str ) < 4 || ':' !== $str[1] ) {
			return false;
		}
		return (bool) preg_match( '/^(?:[aOs]:\d+:|[bid]:[^;]*;|N;)/s', $str ) && in_array( substr( $str, -1 ), array( ';', '}' ), true );
	}

	/**
	 * Objekte (auch __PHP_Incomplete_Class) Eigenschaft für Eigenschaft ersetzen
	 * und den Originalklassennamen erhalten.
	 */
	private function replace_object( $obj ) {
		$props = (array) $obj; // enthält auch private/protected Eigenschaften (mangled keys)
		$class = get_class( $obj );
		if ( isset( $props['__PHP_Incomplete_Class_Name'] ) ) {
			$class = $props['__PHP_Incomplete_Class_Name'];
			unset( $props['__PHP_Incomplete_Class_Name'] );
		}
		$body = '';
		foreach ( $props as $k => $v ) {
			$body .= serialize( $k ) . serialize( $this->replace( $v ) ); // phpcs:ignore
		}
		$ser = 'O:' . strlen( $class ) . ':"' . $class . '":' . count( $props ) . ':{' . $body . '}';
		$new = @unserialize( $ser, array( 'allowed_classes' => false ) ); // phpcs:ignore
		return false === $new ? $obj : $new;
	}
}
