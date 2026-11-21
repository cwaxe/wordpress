<?php
/**
 * Minimaler, fortsetzbarer TAR-Schreiber/-Leser (GNU-Format mit LongLink).
 *
 * TAR wird statt ZIP verwendet, weil sich ein TAR-Archiv in beliebig vielen
 * kurzen Requests anhängen und lesen lässt, ohne das Archiv jedes Mal neu
 * schreiben zu müssen. Das Archiv lässt sich zudem mit jedem Standardtool
 * (tar, 7-Zip, WinRAR) öffnen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPKB_Tar {

	const BLOCK = 512;

	/* ------------------------------------------------------------------ */
	/* Schreiben                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Erzeugt den/die Header-Blöcke für einen Eintrag.
	 */
	public static function header( $name, $size, $mtime = 0, $type = '0', $mode = 0644 ) {
		$out = '';
		if ( strlen( $name ) > 100 ) {
			$long = $name . "\0";
			$out .= self::raw_header( '././@LongLink', strlen( $long ), 0, 'L', 0644 );
			$out .= self::pad( $long );
			$name  = substr( $name, 0, 100 );
		}
		$out .= self::raw_header( $name, $size, $mtime, $type, $mode );
		return $out;
	}

	private static function raw_header( $name, $size, $mtime, $type, $mode ) {
		$h  = str_pad( $name, 100, "\0" );
		$h .= sprintf( '%07o', $mode & 07777 ) . "\0";
		$h .= sprintf( '%07o', 0 ) . "\0";
		$h .= sprintf( '%07o', 0 ) . "\0";
		$h .= self::size_field( $size );
		$h .= sprintf( '%011o', max( 0, (int) $mtime ) ) . "\0";
		$h .= '        '; // Prüfsumme (Platzhalter).
		$h .= $type;
		$h .= str_repeat( "\0", 100 );        // linkname
		$h .= "ustar  \0";                    // GNU-Magic
		$h .= str_pad( 'www', 32, "\0" );     // uname
		$h .= str_pad( 'www', 32, "\0" );     // gname
		$h  = str_pad( $h, self::BLOCK, "\0" );

		$sum = 0;
		for ( $i = 0; $i < self::BLOCK; $i++ ) {
			$sum += ord( $h[ $i ] );
		}
		$chk = sprintf( '%06o', $sum ) . "\0 ";
		return substr_replace( $h, $chk, 148, 8 );
	}

	private static function size_field( $size ) {
		if ( $size < 8589934592 ) { // 8 GiB
			return sprintf( '%011o', $size ) . "\0";
		}
		// GNU base-256 für sehr große Dateien.
		$bytes = '';
		for ( $i = 0; $i < 11; $i++ ) {
			$bytes = chr( (int) fmod( $size, 256 ) ) . $bytes;
			$size  = floor( $size / 256 );
		}
		return chr( 0x80 ) . $bytes;
	}

	public static function pad( $data ) {
		$rest = strlen( $data ) % self::BLOCK;
		return $rest ? $data . str_repeat( "\0", self::BLOCK - $rest ) : $data;
	}

	public static function padding_for( $size ) {
		$rest = $size % self::BLOCK;
		return $rest ? self::BLOCK - $rest : 0;
	}

	public static function end_of_archive() {
		return str_repeat( "\0", self::BLOCK * 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Lesen                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Liest den Eintrag an Position $pos.
	 *
	 * @return array|null [name, size, type, data_offset] oder null am Archivende.
	 */
	public static function read_entry( $fh, $pos ) {
		$long_name = null;
		while ( true ) {
			fseek( $fh, $pos );
			$block = fread( $fh, self::BLOCK );
			if ( false === $block || strlen( $block ) < self::BLOCK || trim( $block, "\0" ) === '' ) {
				return null;
			}
			self::verify_checksum( $block );

			$name = rtrim( substr( $block, 0, 100 ), "\0" );
			$size = self::parse_size( substr( $block, 124, 12 ) );
			$type = $block[156];
			if ( "\0" === $type ) {
				$type = '0';
			}
			$data = $pos + self::BLOCK;

			// POSIX/ustar Präfix-Feld (nur bei "ustar\0"-Magic).
			if ( substr( $block, 257, 6 ) === "ustar\0" ) {
				$prefix = rtrim( substr( $block, 345, 155 ), "\0" );
				if ( '' !== $prefix ) {
					$name = $prefix . '/' . $name;
				}
			}

			if ( 'L' === $type ) {
				fseek( $fh, $data );
				$long_name = rtrim( fread( $fh, $size ), "\0" );
				$pos       = $data + $size + self::padding_for( $size );
				continue;
			}
			if ( 'x' === $type || 'g' === $type ) { // PAX-Header überspringen.
				$pos = $data + $size + self::padding_for( $size );
				continue;
			}
			if ( null !== $long_name ) {
				$name = $long_name;
			}
			return array(
				'name'        => $name,
				'size'        => $size,
				'type'        => $type,
				'data_offset' => $data,
				'next'        => $data + $size + self::padding_for( $size ),
			);
		}
	}

	private static function parse_size( $field ) {
		if ( ord( $field[0] ) & 0x80 ) {
			$size = 0;
			for ( $i = 1; $i < 12; $i++ ) {
				$size = $size * 256 + ord( $field[ $i ] );
			}
			return $size;
		}
		return (int) octdec( trim( $field, " \0" ) );
	}

	private static function verify_checksum( $block ) {
		$stored = (int) octdec( trim( substr( $block, 148, 8 ), " \0" ) );
		$tmp    = substr_replace( $block, '        ', 148, 8 );
		$sum    = 0;
		for ( $i = 0; $i < self::BLOCK; $i++ ) {
			$sum += ord( $tmp[ $i ] );
		}
		if ( $sum !== $stored ) {
			throw new Exception( 'Archiv ist beschädigt (ungültige Header-Prüfsumme).' );
		}
	}

	/**
	 * Liest den Inhalt eines (kleinen) Eintrags vollständig.
	 */
	public static function read_data( $fh, $entry ) {
		if ( $entry['size'] <= 0 ) {
			return '';
		}
		fseek( $fh, $entry['data_offset'] );
		return fread( $fh, $entry['size'] );
	}

	/**
	 * Liest das Manifest (erster Eintrag) eines Archivs.
	 */
	public static function read_manifest( $file ) {
		$fh = @fopen( $file, 'rb' );
		if ( ! $fh ) {
			return null;
		}
		try {
			$entry = self::read_entry( $fh, 0 );
			if ( ! $entry || 'manifest.json' !== $entry['name'] || $entry['size'] > 1048576 ) {
				return null;
			}
			$data = json_decode( self::read_data( $fh, $entry ), true );
			return is_array( $data ) ? $data : null;
		} catch ( Exception $e ) {
			return null;
		} finally {
			fclose( $fh );
		}
	}
}
