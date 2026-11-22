<?php
/**
 * Automatische Updates aus den GitHub-Releases dieses Repos.
 *
 * Gemeinsame Datei für alle Plugins im Repo. Die Vorlage liegt in lib/github-updater.php,
 * `./build.sh --sync` kopiert sie nach plugins/<paket>/includes/github-updater.php.
 * Bitte nur die Vorlage bearbeiten.
 *
 * Einbau im Plugin:
 *   - im Kopf:   Update URI: https://github.com/cwaxe/wordpress
 *   - im Code:   require_once __DIR__ . '/includes/github-updater.php';
 *                WX_GitHub_Updater::register( __FILE__ );
 *
 * Ablauf: WordPress prüft zweimal täglich auf Updates. Für Plugins mit obiger Update URI
 * fragt es den Filter update_plugins_github.com. Der Updater liest dann die Releases
 * des Repos (einmal für alle Plugins, 6 Stunden zwischengespeichert), sucht das neueste
 * Release <ordner>-v<version> mit der ZIP <ordner>-<version>.zip und meldet es als Update.
 * Ab da läuft alles über die normale Update-Funktion von WordPress, inklusive
 * „Automatische Aktualisierungen aktivieren“ in der Plugin-Liste.
 *
 * Optional in wp-config.php:
 *   define( 'WX_GITHUB_TOKEN', '…' );            mehr API-Abfragen (Token ohne Rechte reicht)
 *   define( 'WX_GITHUB_AUTO_UPDATE', false );     automatische Updates nicht vorab einschalten
 *
 * Benötigt WordPress 5.8 (Update URI) und PHP 7.4.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WX_GitHub_Updater' ) ) {

	final class WX_GitHub_Updater {

		const VERSION      = '1.0.0';
		const DEFAULT_REPO = 'cwaxe/wordpress';
		const CACHE_TTL    = 6 * HOUR_IN_SECONDS;
		const ERROR_TTL    = HOUR_IN_SECONDS;
		const STATE_OPTION = 'wx_github_updater';

		/** @var array<string,string> plugin_basename => repo */
		private static $plugins = array();

		/** @var array<string,array> Releases je Repo, nur für diesen Seitenaufruf. */
		private static $memo = array();

		/** @var bool */
		private static $hooked = false;

		/**
		 * Plugin für Updates aus GitHub anmelden.
		 *
		 * @param string $file __FILE__ der Hauptdatei des Plugins.
		 * @param string $repo owner/name des Repos.
		 */
		public static function register( $file, $repo = self::DEFAULT_REPO ) {
			self::$plugins[ plugin_basename( $file ) ] = $repo;

			if ( self::$hooked ) {
				return;
			}
			self::$hooked = true;

			add_filter( 'update_plugins_github.com', array( __CLASS__, 'filter_update' ), 10, 3 );
			add_filter( 'plugins_api', array( __CLASS__, 'filter_plugins_api' ), 20, 3 );
			add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_dir' ), 10, 4 );
			add_action( 'upgrader_process_complete', array( __CLASS__, 'after_upgrade' ), 10, 2 );
		}

		/* ------------------------------------------------------------------
		 * Update-Prüfung
		 * ---------------------------------------------------------------- */

		/**
		 * Antwort auf die Update-Prüfung von WordPress für ein Plugin.
		 *
		 * @param array|false $update      Bisheriges Ergebnis.
		 * @param array       $plugin_data Kopfdaten des installierten Plugins.
		 * @param string      $plugin_file plugin_basename.
		 * @return array|false
		 */
		public static function filter_update( $update, $plugin_data, $plugin_file ) {
			if ( $update || ! isset( self::$plugins[ $plugin_file ] ) ) {
				return $update;
			}

			self::maybe_enable_auto_update( $plugin_file );

			$release = self::latest_release( $plugin_file );
			if ( ! $release ) {
				return $update;
			}

			$slug = self::slug( $plugin_file );
			return array(
				'slug'         => $slug,
				'version'      => $release['version'],
				'url'          => $release['html_url'],
				'package'      => $release['package'],
				'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
			);
		}

		/**
		 * Fenster „Details ansehen“ in der Plugin-Liste und bei Updates.
		 *
		 * @param false|object|array $result Bisheriges Ergebnis.
		 * @param string             $action Art der Anfrage.
		 * @param object             $args   Anfrage, enthält slug.
		 * @return false|object|array
		 */
		public static function filter_plugins_api( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
				return $result;
			}
			$plugin_file = self::file_for_slug( $args->slug );
			if ( ! $plugin_file ) {
				return $result;
			}

			$release = self::latest_release( $plugin_file );
			if ( ! $release ) {
				return $result;
			}

			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, true );
			$repo = self::$plugins[ $plugin_file ];

			$changelog = '';
			foreach ( self::releases_for( $plugin_file ) as $r ) {
				$changelog .= '<h4>' . esc_html( $r['version'] ) . ' <small>(' . esc_html( mysql2date( get_option( 'date_format' ), $r['published'] ) ) . ')</small></h4>';
				$changelog .= self::markdown( $r['body'] );
			}

			return (object) array(
				'name'          => $data['Name'],
				'slug'          => $args->slug,
				'version'       => $release['version'],
				'author'        => $data['Author'],
				'homepage'      => $data['PluginURI'] ? $data['PluginURI'] : 'https://github.com/' . $repo . '/tree/main/plugins/' . $args->slug,
				'requires'      => $data['RequiresWP'],
				'requires_php'  => $data['RequiresPHP'],
				'last_updated'  => $release['published'],
				'download_link' => $release['package'],
				'sections'      => array(
					'description' => '<p>' . esc_html( wp_strip_all_tags( $data['Description'] ) ) . '</p>'
						. '<p>Updates kommen aus den Releases von <a href="https://github.com/' . esc_attr( $repo ) . '/releases">github.com/' . esc_html( $repo ) . '</a>.</p>',
					'changelog'   => $changelog ? $changelog : '<p>Keine Angaben.</p>',
				),
			);
		}

		/**
		 * Sorgt dafür, dass der entpackte Ordner wie das installierte Plugin heißt.
		 * Die ZIPs aus build.sh haben das schon richtig, das ist nur eine Absicherung.
		 */
		public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
			global $wp_filesystem;

			if ( empty( $hook_extra['plugin'] ) || ! isset( self::$plugins[ $hook_extra['plugin'] ] ) ) {
				return $source;
			}
			$slug   = self::slug( $hook_extra['plugin'] );
			$wanted = trailingslashit( $remote_source ) . $slug . '/';
			if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) || ! $wp_filesystem ) {
				return $source;
			}
			if ( $wp_filesystem->move( $source, $wanted, true ) ) {
				return $wanted;
			}
			return new WP_Error( 'wx_github_updater_rename', 'Der Plugin-Ordner im Update konnte nicht umbenannt werden.' );
		}

		/** Nach einem Update Zwischenspeicher leeren, damit keine alte Version mehr angeboten wird. */
		public static function after_upgrade( $upgrader, $options ) {
			if ( isset( $options['type'] ) && 'plugin' === $options['type'] ) {
				foreach ( array_unique( self::$plugins ) as $repo ) {
					// Nur den kurzen Cache verwerfen, ETag bleibt erhalten.
					delete_site_transient( self::cache_key( $repo ) );
				}
			}
		}

		/* ------------------------------------------------------------------
		 * Automatische Updates
		 * ---------------------------------------------------------------- */

		/**
		 * Schaltet „Automatische Aktualisierungen“ für das Plugin einmalig ein.
		 * Schaltet man es danach in der Plugin-Liste aus, bleibt es aus.
		 */
		private static function maybe_enable_auto_update( $plugin_file ) {
			if ( defined( 'WX_GITHUB_AUTO_UPDATE' ) && ! WX_GITHUB_AUTO_UPDATE ) {
				return;
			}
			$state = get_site_option( self::STATE_OPTION, array() );
			$state = is_array( $state ) ? $state : array();
			if ( ! empty( $state['auto'][ $plugin_file ] ) ) {
				return;
			}

			$auto = (array) get_site_option( 'auto_update_plugins', array() );
			if ( ! in_array( $plugin_file, $auto, true ) ) {
				$auto[] = $plugin_file;
				update_site_option( 'auto_update_plugins', array_values( $auto ) );
			}
			$state['auto'][ $plugin_file ] = time();
			update_site_option( self::STATE_OPTION, $state );
		}

		/* ------------------------------------------------------------------
		 * Releases von GitHub
		 * ---------------------------------------------------------------- */

		/** Neuestes passendes Release für ein Plugin oder null. */
		private static function latest_release( $plugin_file ) {
			$list = self::releases_for( $plugin_file );
			return $list ? $list[0] : null;
		}

		/**
		 * Alle Releases eines Plugins, neueste Version zuerst.
		 *
		 * @return array<int,array{version:string,package:string,html_url:string,published:string,body:string}>
		 */
		private static function releases_for( $plugin_file ) {
			$slug   = self::slug( $plugin_file );
			$prefix = $slug . '-v';
			$found  = array();

			foreach ( self::releases( self::$plugins[ $plugin_file ] ) as $r ) {
				if ( ! empty( $r['draft'] ) || ! empty( $r['prerelease'] ) || empty( $r['tag_name'] ) ) {
					continue;
				}
				if ( 0 !== strpos( $r['tag_name'], $prefix ) ) {
					continue;
				}
				$version = substr( $r['tag_name'], strlen( $prefix ) );
				if ( ! preg_match( '/^\d+(\.\d+)*$/', $version ) ) {
					continue; // Kein reines Versionsschema, also nicht von build.sh.
				}
				$package = '';
				foreach ( (array) $r['assets'] as $asset ) {
					if ( isset( $asset['name'] ) && $asset['name'] === "$slug-$version.zip" ) {
						$package = $asset['url'];
						break;
					}
				}
				if ( ! $package ) {
					continue;
				}
				$found[] = array(
					'version'   => $version,
					'package'   => $package,
					'html_url'  => $r['html_url'],
					'published' => $r['published_at'],
					'body'      => $r['body'],
				);
			}

			usort(
				$found,
				function ( $a, $b ) {
					return version_compare( $b['version'], $a['version'] );
				}
			);
			return $found;
		}

		/**
		 * Releases eines Repos, stark gekürzt und zwischengespeichert.
		 * Eine Abfrage für alle Plugins aus demselben Repo.
		 */
		private static function releases( $repo ) {
			if ( isset( self::$memo[ $repo ] ) ) {
				return self::$memo[ $repo ];
			}

			$key    = self::cache_key( $repo );
			$cached = get_site_transient( $key );
			$force  = is_admin() && isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( is_array( $cached ) && ! $force ) {
				return self::$memo[ $repo ] = $cached['releases'];
			}

			// Letzter erfolgreicher Stand, auch für den ETag.
			$store = get_site_option( $key . '_last', array() );
			$store = is_array( $store ) ? $store : array();
			$last  = isset( $store['releases'] ) ? (array) $store['releases'] : array();

			$headers = array(
				'Accept'               => 'application/vnd.github+json',
				'X-GitHub-Api-Version' => '2022-11-28',
			);
			if ( ! empty( $store['etag'] ) ) {
				$headers['If-None-Match'] = $store['etag'];
			}
			if ( defined( 'WX_GITHUB_TOKEN' ) && WX_GITHUB_TOKEN ) {
				$headers['Authorization'] = 'Bearer ' . WX_GITHUB_TOKEN;
			}

			$response = wp_remote_get(
				'https://api.github.com/repos/' . $repo . '/releases?per_page=100',
				array(
					'timeout'    => 10,
					'headers'    => $headers,
					'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ) . '; wx-github-updater/' . self::VERSION,
				)
			);
			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( 304 === $code ) {
				$releases = $last;
				$ttl      = self::CACHE_TTL;
			} elseif ( 200 === $code ) {
				$json     = json_decode( wp_remote_retrieve_body( $response ), true );
				$releases = is_array( $json ) ? self::slim( $json ) : $last;
				$etag     = wp_remote_retrieve_header( $response, 'etag' );
				update_site_option(
					$key . '_last',
					array(
						'etag'     => is_string( $etag ) ? $etag : '',
						'releases' => $releases,
					)
				);
				$ttl = self::CACHE_TTL;
			} else {
				// GitHub nicht erreichbar oder Abfragelimit: alten Stand nehmen, bald erneut versuchen.
				$releases = $last;
				$ttl      = self::ERROR_TTL;
			}

			set_site_transient( $key, array( 'releases' => $releases ), $ttl );
			return self::$memo[ $repo ] = $releases;
		}

		/** Nur die Felder behalten, die gebraucht werden. */
		private static function slim( array $json ) {
			$out = array();
			foreach ( $json as $r ) {
				if ( ! is_array( $r ) || empty( $r['tag_name'] ) ) {
					continue;
				}
				$assets = array();
				foreach ( isset( $r['assets'] ) ? (array) $r['assets'] : array() as $a ) {
					if ( isset( $a['name'], $a['browser_download_url'] ) ) {
						$assets[] = array(
							'name' => (string) $a['name'],
							'url'  => (string) $a['browser_download_url'],
						);
					}
				}
				$out[] = array(
					'tag_name'     => (string) $r['tag_name'],
					'draft'        => ! empty( $r['draft'] ),
					'prerelease'   => ! empty( $r['prerelease'] ),
					'html_url'     => isset( $r['html_url'] ) ? (string) $r['html_url'] : '',
					'published_at' => isset( $r['published_at'] ) ? (string) $r['published_at'] : '',
					'body'         => isset( $r['body'] ) ? (string) $r['body'] : '',
					'assets'       => $assets,
				);
			}
			return $out;
		}

		/* ------------------------------------------------------------------
		 * Hilfsfunktionen
		 * ---------------------------------------------------------------- */

		private static function cache_key( $repo ) {
			return 'wx_ghu_' . md5( $repo );
		}

		private static function slug( $plugin_file ) {
			$dir = dirname( $plugin_file );
			return '.' === $dir ? basename( $plugin_file, '.php' ) : $dir;
		}

		private static function file_for_slug( $slug ) {
			foreach ( self::$plugins as $file => $repo ) {
				if ( self::slug( $file ) === $slug ) {
					return $file;
				}
			}
			return null;
		}

		/** Sehr kleines Markdown für die Release-Texte: Absätze, Listen, fett, kursiv, Code, Links. */
		private static function markdown( $text ) {
			$html    = '';
			$in_list = false;
			$para    = array();

			$inline = function ( $s ) {
				$s = esc_html( $s );
				$s = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $s );
				$s = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s );
				$s = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $s );
				$s = preg_replace_callback(
					'/\[([^\]]+)\]\((https?:[^)\s]+)\)/',
					function ( $m ) {
						return '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '">' . $m[1] . '</a>';
					},
					$s
				);
				return $s;
			};
			$flush = function () use ( &$para, &$html, $inline ) {
				if ( $para ) {
					$html .= '<p>' . $inline( implode( ' ', $para ) ) . '</p>';
					$para  = array();
				}
			};

			foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
				$line = rtrim( $line );
				if ( preg_match( '/^\s*[-*]\s+(.*)$/', $line, $m ) ) {
					$flush();
					if ( ! $in_list ) {
						$html   .= '<ul>';
						$in_list = true;
					}
					$html .= '<li>' . $inline( $m[1] ) . '</li>';
					continue;
				}
				if ( $in_list && '' !== trim( $line ) && preg_match( '/^\s{2,}\S/', $line ) ) {
					// Fortsetzung eines Listenpunkts.
					$html = preg_replace( '#</li>$#', ' ' . $inline( trim( $line ) ) . '</li>', $html );
					continue;
				}
				if ( $in_list ) {
					$html   .= '</ul>';
					$in_list = false;
				}
				if ( '' === trim( $line ) ) {
					$flush();
				} elseif ( preg_match( '/^#{1,6}\s+(.*)$/', $line, $m ) ) {
					$flush();
					$html .= '<p><strong>' . $inline( $m[1] ) . '</strong></p>';
				} else {
					$para[] = trim( $line );
				}
			}
			if ( $in_list ) {
				$html .= '</ul>';
			}
			$flush();
			return $html;
		}
	}
}
