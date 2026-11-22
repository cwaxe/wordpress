<?php
/**
 * Einstellungen (Termine → Einstellungen).
 */

defined( 'ABSPATH' ) || exit;

class TK_Settings {

	const OPTION = 'tk_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return [
			'page_id'       => 0,
			'accent'        => '#0b5cad',
			'default_view'  => 'month',
			'max_per_day'   => 3,
			'show_end_time' => 0,
			'single_slug'   => 'termin',
			'redirect_tec'  => 1,
		];
	}

	/**
	 * @param string $key Schlüssel.
	 * @return mixed
	 */
	public static function get( $key ) {
		$options  = get_option( self::OPTION, [] );
		$defaults = self::defaults();
		$options  = is_array( $options ) ? array_merge( $defaults, $options ) : $defaults;

		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	/**
	 * @param array<string,mixed> $values Neue Werte.
	 */
	public static function update( $values ) {
		$options = get_option( self::OPTION, [] );
		$options = is_array( $options ) ? $options : [];
		update_option( self::OPTION, self::sanitize( array_merge( self::defaults(), $options, $values ) ) );
	}

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
		add_action( 'update_option_' . self::OPTION, [ __CLASS__, 'after_update' ], 10, 2 );
	}

	public static function register() {
		register_setting(
			'tk_settings_group',
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				'default'           => self::defaults(),
			]
		);
	}

	/**
	 * @param mixed $input Formulardaten.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : [];
		$d      = self::defaults();
		$accent = isset( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '';
		$slug   = isset( $input['single_slug'] ) ? sanitize_title( $input['single_slug'] ) : '';

		return [
			'page_id'       => isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0,
			'accent'        => $accent ? $accent : $d['accent'],
			'default_view'  => ( isset( $input['default_view'] ) && 'list' === $input['default_view'] ) ? 'list' : 'month',
			'max_per_day'   => isset( $input['max_per_day'] ) ? max( 1, min( 10, (int) $input['max_per_day'] ) ) : 3,
			'show_end_time' => empty( $input['show_end_time'] ) ? 0 : 1,
			'single_slug'   => $slug ? $slug : $d['single_slug'],
			'redirect_tec'  => empty( $input['redirect_tec'] ) ? 0 : 1,
		];
	}

	/**
	 * Nach Änderung der Adresse der Terminseiten die Permalinks neu aufbauen.
	 *
	 * @param mixed $old Alt.
	 * @param mixed $new Neu.
	 */
	public static function after_update( $old, $new ) {
		$old_slug = is_array( $old ) && isset( $old['single_slug'] ) ? $old['single_slug'] : '';
		$new_slug = is_array( $new ) && isset( $new['single_slug'] ) ? $new['single_slug'] : '';
		if ( $old_slug !== $new_slug ) {
			update_option( 'tk_flush_rewrite', 1 );
		}
		TK_Model::bump();
	}
}
