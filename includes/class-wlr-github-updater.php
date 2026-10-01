<?php
/**
 * Aggiornamenti automatici da GitHub Releases.
 *
 * Gestisce:
 * - Rilevamento nuova versione tramite GitHub API (con strip del prefisso "v").
 * - Popup "Dettagli plugin" nella dashboard WordPress.
 * - Rinomina della cartella estratta in "woo-legal" per evitare che WordPress
 *   la interpreti come un plugin diverso (GitHub la chiama {repo}-{tag}/).
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_GitHub_Updater {

	const GITHUB_USER = 'marrisonlab';
	const GITHUB_REPO = 'woo-legal';

	private string $plugin_basename;
	private string $plugin_version;
	private string $plugin_slug;

	public function __construct() {
		$this->plugin_basename = plugin_basename( WLR_PLUGIN_FILE );
		$this->plugin_version  = WLR_VERSION;
		$this->plugin_slug     = 'woo-legal';
	}

	public function hooks(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_folder_name' ), 10, 4 );
	}

	// -------------------------------------------------------------------------
	// Controllo aggiornamento
	// -------------------------------------------------------------------------

	/**
	 * Inietta la risposta di aggiornamento nel transient di WordPress.
	 *
	 * @param object $transient Transient degli aggiornamenti plugin.
	 * @return object
	 */
	public function check_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release['tag_name'], 'vV' );

		if ( version_compare( $remote_version, $this->plugin_version, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'slug'         => $this->plugin_slug,
				'plugin'       => $this->plugin_basename,
				'new_version'  => $remote_version,
				'url'          => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
				'package'      => $release['zipball_url'],
				'icons'        => array(),
				'banners'      => array(),
				'requires_php' => '8.0',
			);
		} else {
			unset( $transient->response[ $this->plugin_basename ] );
			$transient->no_update[ $this->plugin_basename ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $remote_version,
				'url'         => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
				'package'     => '',
			);
		}

		return $transient;
	}

	// -------------------------------------------------------------------------
	// Info popup
	// -------------------------------------------------------------------------

	/**
	 * Popola il popup "Dettagli plugin" in Plugins → Aggiornamenti.
	 *
	 * @param false|object|array $result Risultato corrente.
	 * @param string             $action Tipo di richiesta API.
	 * @param object             $args   Argomenti richiesta.
	 * @return false|object
	 */
	public function plugin_info( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release['tag_name'], 'vV' );
		$changelog      = ! empty( $release['body'] ) ? nl2br( esc_html( $release['body'] ) ) : '';

		return (object) array(
			'name'          => 'Woo Legal Returns – EU Directive',
			'slug'          => $this->plugin_slug,
			'version'       => $remote_version,
			'author'        => '<a href="https://marrisonlab.com">Marrisonlab</a>',
			'homepage'      => 'https://marrisonlab.com',
			'download_link' => $release['zipball_url'],
			'requires'      => '6.0',
			'requires_php'  => '8.0',
			'last_updated'  => $release['published_at'] ?? '',
			'sections'      => array(
				'description' => 'Plugin per la gestione dei resi e del diritto di recesso ai sensi della Direttiva UE 2023/2673 e del D.Lgs. 209/2025 (art. 54-bis Codice del Consumo).',
				'changelog'   => '' !== $changelog ? $changelog : '<p>Vedi <a href="https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases">GitHub Releases</a>.</p>',
			),
		);
	}

	// -------------------------------------------------------------------------
	// Rinomina cartella
	// -------------------------------------------------------------------------

	/**
	 * Rinomina la cartella estratta dallo zip GitHub in "woo-legal".
	 *
	 * GitHub genera cartelle come "marrisonlab-woo-legal-{shorthash}/" o
	 * "woo-legal-1.0/"; WordPress le considera plugin diversi se il nome
	 * non corrisponde esattamente alla cartella originale.
	 *
	 * @param string       $source        Percorso cartella estratta.
	 * @param string       $remote_source Percorso directory temporanea.
	 * @param \WP_Upgrader $upgrader     Istanza upgrader.
	 * @param array        $hook_extra    Dati contestuali (contiene 'plugin').
	 * @return string|\WP_Error
	 */
	public function fix_folder_name( string $source, string $remote_source, $upgrader, array $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		$corrected = trailingslashit( $remote_source ) . $this->plugin_slug . '/';

		if ( $source === $corrected ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return new WP_Error( 'wlr_filesystem_unavailable', __( 'Filesystem non disponibile: impossibile verificare la cartella del plugin.', 'woo-legal-returns' ) );
		}
		if ( ! $wp_filesystem->exists( trailingslashit( $source ) . 'woo-legal-returns.php' ) ) {
			return new WP_Error( 'wlr_invalid_package', __( 'Il pacchetto non contiene il file principale di Woo Legal Returns.', 'woo-legal-returns' ) );
		}

		if ( $wp_filesystem->move( $source, $corrected ) ) {
			return $corrected;
		}

		return new \WP_Error(
			'wlr_rename_failed',
			sprintf(
				/* translators: 1: nome cartella sorgente, 2: nome cartella destinazione */
				__( 'Impossibile rinominare la cartella del plugin da "%1$s" a "%2$s".', 'woo-legal-returns' ),
				basename( rtrim( $source, '/' ) ),
				$this->plugin_slug
			)
		);
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	/**
	 * Recupera l'ultima release da GitHub API con cache di 1 ora.
	 *
	 * @return array|null Dati release o null in caso di errore.
	 */
	private function get_latest_release(): ?array {
		$cache_key = 'wlr_github_release_' . WLR_VERSION;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return is_array( $cached ) && $this->is_valid_release( $cached ) ? $cached : null;
		}

		$url      = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_USER,
			self::GITHUB_REPO
		);
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, array(), 5 * MINUTE_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! $this->is_valid_release( $data ) ) {
			set_transient( $cache_key, array(), 5 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( $cache_key, $data, HOUR_IN_SECONDS );

		return $data;
	}

	private function is_valid_release( array $data ): bool {
		$tag = $data['tag_name'] ?? '';
		$url = $data['zipball_url'] ?? '';
		return is_string( $tag ) && (bool) preg_match( '/^[vV]?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $tag )
			&& is_string( $url ) && 'https' === wp_parse_url( $url, PHP_URL_SCHEME )
			&& in_array( wp_parse_url( $url, PHP_URL_HOST ), array( 'api.github.com', 'github.com', 'codeload.github.com' ), true );
	}
}
