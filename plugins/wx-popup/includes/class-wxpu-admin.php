<?php
/**
 * Backend: Einstellungskasten im Editor, Spalten und Vorschau in der Liste.
 */

defined( 'ABSPATH' ) || exit;

final class WXPU_Admin {

	public static function init() {
		add_action( 'add_meta_boxes_' . WXPU_Popup::TYPE, array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . WXPU_Popup::TYPE, array( __CLASS__, 'speichern' ), 10, 2 );
		add_filter( 'manage_' . WXPU_Popup::TYPE . '_posts_columns', array( __CLASS__, 'spalten' ) );
		add_action( 'manage_' . WXPU_Popup::TYPE . '_posts_custom_column', array( __CLASS__, 'spalte' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'zeilen_aktionen' ), 10, 2 );
		add_action( 'admin_head', array( __CLASS__, 'stil' ) );
	}

	public static function meta_box() {
		add_meta_box( 'wxpu-einstellungen', 'Pop-up-Einstellungen', array( __CLASS__, 'box' ), WXPU_Popup::TYPE, 'side', 'high' );
	}

	public static function box( $post ) {
		$s = WXPU_Popup::get( $post->ID );
		wp_nonce_field( 'wxpu_speichern', 'wxpu_nonce' );
		$n = 'wxpu';
		?>
		<div class="wxpu-box">
			<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[aktiv]" value="1" <?php checked( $s['aktiv'] ); ?>> <strong>Pop-up anzeigen</strong></label></p>

			<p class="wxpu-kopf">Zeitraum <span>(optional)</span></p>
			<p><label>von<br><input type="datetime-local" name="<?php echo esc_attr( $n ); ?>[von]" value="<?php echo esc_attr( $s['von'] ); ?>"></label></p>
			<p><label>bis<br><input type="datetime-local" name="<?php echo esc_attr( $n ); ?>[bis]" value="<?php echo esc_attr( $s['bis'] ); ?>"></label></p>

			<p class="wxpu-kopf">Wo erscheint es?</p>
			<p>
				<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[wo]" value="startseite" <?php checked( $s['wo'], 'startseite' ); ?>> nur Startseite</label><br>
				<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[wo]" value="alle" <?php checked( $s['wo'], 'alle' ); ?>> auf allen Seiten</label><br>
				<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[wo]" value="seiten" <?php checked( $s['wo'], 'seiten' ); ?>> auf ausgewählten Seiten:</label>
			</p>
			<div class="wxpu-seiten">
				<?php
				$seiten = get_pages( array( 'sort_column' => 'menu_order,post_title' ) );
				foreach ( $seiten as $seite ) {
					printf(
						'<label><input type="checkbox" name="%s[seiten][]" value="%d" %s> %s</label>',
						esc_attr( $n ),
						(int) $seite->ID,
						checked( in_array( (int) $seite->ID, $s['seiten'], true ), true, false ),
						esc_html( str_repeat( '– ', count( get_post_ancestors( $seite ) ) ) . ( $seite->post_title ?: '(ohne Titel)' ) )
					);
				}
				?>
			</div>

			<p class="wxpu-kopf">Verhalten</p>
			<p><label>Öffnen nach <input type="number" min="0" max="600" step="0.5" name="<?php echo esc_attr( $n ); ?>[verzoegerung]" value="<?php echo esc_attr( $s['verzoegerung'] ); ?>" class="small-text"> Sekunden</label></p>
			<p>
				<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[wiederholung]" value="immer" <?php checked( $s['wiederholung'], 'immer' ); ?>> bei jedem Seitenaufruf</label><br>
				<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[wiederholung]" value="sitzung" <?php checked( $s['wiederholung'], 'sitzung' ); ?>> einmal pro Besuch</label><br>
				<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[wiederholung]" value="tage" <?php checked( $s['wiederholung'], 'tage' ); ?>> erst wieder nach
					<input type="number" min="1" max="365" name="<?php echo esc_attr( $n ); ?>[tage]" value="<?php echo esc_attr( $s['tage'] ); ?>" class="small-text"> Tagen</label>
			</p>
			<p><label>Breite
				<select name="<?php echo esc_attr( $n ); ?>[breite]">
					<?php foreach ( array( 'klein' => 'klein (400 px)', 'mittel' => 'mittel (640 px)', 'gross' => 'groß (900 px)', 'auto' => 'automatisch (nach Inhalt)' ) as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['breite'], $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
			</label></p>
			<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[aussen]" value="1" <?php checked( $s['aussen'] ); ?>> Klick neben das Pop-up schließt es</label></p>

			<?php if ( 'auto-draft' !== $post->post_status ) : ?>
				<p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( self::vorschau_url( $post->ID ) ); ?>">Vorschau auf der Startseite</a></p>
				<p class="description">Die Vorschau zeigt den zuletzt gespeicherten Stand.</p>
			<?php endif; ?>
			<p class="description">Ein Link mit der Adresse <code>#popup-<?php echo (int) $post->ID; ?></code> öffnet dieses Pop-up auch von Hand.</p>
		</div>
		<?php
	}

	public static function vorschau_url( $id ) {
		return add_query_arg( 'wxpu_vorschau', (int) $id, home_url( '/' ) );
	}

	public static function speichern( $post_id, $post ) {
		if ( ! isset( $_POST['wxpu_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wxpu_nonce'] ), 'wxpu_speichern' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['wxpu'] ) ? wp_unslash( (array) $_POST['wxpu'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- in bereinigen()
		update_post_meta( $post_id, WXPU_Popup::META, WXPU_Popup::bereinigen( $in ) );
	}

	public static function spalten( $cols ) {
		$neu = array();
		foreach ( $cols as $k => $v ) {
			$neu[ $k ] = $v;
			if ( 'title' === $k ) {
				$neu['wxpu_status'] = 'Status';
				$neu['wxpu_zeit']   = 'Zeitraum';
				$neu['wxpu_wo']     = 'Wo';
			}
		}
		return $neu;
	}

	public static function spalte( $col, $id ) {
		$s = WXPU_Popup::get( $id );
		if ( 'wxpu_status' === $col ) {
			$st = WXPU_Popup::status( $id );
			$t  = array(
				'aus'        => 'aus',
				'geplant'    => 'geplant',
				'aktiv'      => 'wird angezeigt',
				'abgelaufen' => 'abgelaufen',
			);
			printf( '<span class="wxpu-status wxpu-%s">%s</span>', esc_attr( $st ), esc_html( $t[ $st ] ) );
		} elseif ( 'wxpu_zeit' === $col ) {
			$f = function ( $v ) {
				return $v ? wp_date( 'd.m.Y H:i', WXPU_Popup::ts( $v ) ) : '';
			};
			if ( ! $s['von'] && ! $s['bis'] ) {
				echo '—';
			} else {
				echo esc_html( trim( ( $s['von'] ? 'ab ' . $f( $s['von'] ) : '' ) . ' ' . ( $s['bis'] ? 'bis ' . $f( $s['bis'] ) : '' ) ) );
			}
		} elseif ( 'wxpu_wo' === $col ) {
			$t = array(
				'startseite' => 'Startseite',
				'alle'       => 'alle Seiten',
				'seiten'     => count( $s['seiten'] ) . ' ausgewählte Seite(n)',
			);
			echo esc_html( $t[ $s['wo'] ] );
		}
	}

	public static function zeilen_aktionen( $actions, $post ) {
		if ( WXPU_Popup::TYPE === $post->post_type && 'trash' !== $post->post_status ) {
			unset( $actions['inline hide-if-no-js'] );
			$actions['wxpu_vorschau'] = '<a target="_blank" rel="noopener" href="' . esc_url( self::vorschau_url( $post->ID ) ) . '">Vorschau</a>';
		}
		return $actions;
	}

	public static function stil() {
		$screen = get_current_screen();
		if ( ! $screen || WXPU_Popup::TYPE !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			.wxpu-box .wxpu-kopf { font-weight: 600; margin: 14px 0 4px; }
			.wxpu-box .wxpu-kopf span { font-weight: 400; color: #646970; }
			.wxpu-box input[type="datetime-local"] { width: 100%; }
			.wxpu-seiten { max-height: 150px; overflow: auto; border: 1px solid #dcdcde; padding: 6px 8px; margin: 0 0 8px 22px; }
			.wxpu-seiten label { display: block; margin: 2px 0; }
			.wxpu-status { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; background: #f0f0f1; color: #50575e; }
			.wxpu-aktiv { background: #d1f0dc; color: #0a5c2d; }
			.wxpu-geplant { background: #fcf0d1; color: #7a5600; }
			.wxpu-abgelaufen { background: #fbe0e0; color: #8a1f1f; }
		</style>
		<?php
	}
}
