<?php
/**
 * Wizard di configurazione iniziale del plugin.
 *
 * Accessibile da WooCommerce → Resi UE → Configurazione.
 * Può essere rieseguito in qualsiasi momento.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

class WLR_Setup_Wizard {

	const OPTION_KEY  = 'wlr_setup_options';
	const WIZARD_SLUG = 'wlr-setup-wizard';

	private static ?WLR_Setup_Wizard $instance = null;

	/** Passi del wizard: slug => etichetta. */
	private array $steps = [
		'precontractual'  => 'Informativa precontrattuale',
		'menu'            => 'Menu di navigazione',
		'checkout_notice' => 'Avviso al checkout',
		'complete'        => 'Completato',
	];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks(): void {
		add_action( 'admin_menu',    [ $this, 'register_page' ] );
		add_action( 'admin_init',    [ $this, 'handle_post' ] );
		add_action( 'admin_notices', [ $this, 'show_setup_notice' ] );
	}

	// -------------------------------------------------------------------------
	// Registrazione menu
	// -------------------------------------------------------------------------

	public function register_page(): void {
		add_submenu_page(
			null,
			__( 'Configurazione Woo Legal Returns', 'woo-legal-returns' ),
			'',
			'manage_options',
			self::WIZARD_SLUG,
			[ $this, 'render' ]
		);
	}

	// -------------------------------------------------------------------------
	// Gestione POST
	// -------------------------------------------------------------------------

	public function handle_post(): void {
		if ( empty( $_POST['wlr_wizard_step'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'woo-legal-returns' ) );
		}

		$step = sanitize_key( $_POST['wlr_wizard_step'] );
		check_admin_referer( 'wlr_wizard_' . $step );

		match ( $step ) {
			'precontractual'  => $this->save_precontractual(),
			'menu'            => $this->save_menu(),
			'checkout_notice' => $this->save_checkout_notice(),
			default           => null,
		};
	}

	private function save_precontractual(): void {
		$mode = sanitize_key( $_POST['page_mode'] ?? 'new' );

		if ( 'existing' === $mode ) {
			$page_id = absint( $_POST['existing_page_id'] ?? 0 );
		} else {
			$title   = sanitize_text_field(
				$_POST['new_page_title'] ?? __( 'Informativa sul Diritto di Recesso', 'woo-legal-returns' )
			);
			$page_id = wp_insert_post( [
				'post_title'   => $title,
				'post_content' => $this->get_default_page_content(),
				'post_status'  => 'publish',
				'post_type'    => 'page',
			] );
		}

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$this->save_option( 'precontractual_page_id', (int) $page_id );
		}

		wp_safe_redirect( $this->wizard_url( 'menu' ) );
		exit;
	}

	private function save_menu(): void {
		$page_id  = (int) $this->get_option( 'precontractual_page_id' );
		$menu_ids = array_map( 'absint', (array) ( $_POST['menus'] ?? [] ) );
		$added    = [];

		foreach ( $menu_ids as $menu_id ) {
			if ( ! $menu_id ) {
				continue;
			}

			// Evita duplicati: non aggiungere se il link è già presente.
			if ( $page_id ) {
				$existing = wp_get_nav_menu_items( $menu_id, [ 'nopaging' => true ] ) ?: [];
				foreach ( $existing as $nav_item ) {
					if ( 'post_type' === $nav_item->type && (int) $nav_item->object_id === $page_id ) {
						$added[] = $menu_id;
						continue 2;
					}
				}
			}

			$item_id = wp_update_nav_menu_item(
				$menu_id,
				0,
				[
					'menu-item-title'     => $page_id
						? get_the_title( $page_id )
						: __( 'Informativa Recesso', 'woo-legal-returns' ),
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page_id,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				]
			);
			if ( $item_id && ! is_wp_error( $item_id ) ) {
				$added[] = $menu_id;
			}
		}

		$this->save_option( 'menu_ids', $added );

		wp_safe_redirect( $this->wizard_url( 'checkout_notice' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	public function render(): void {
		$step = sanitize_key( $_GET['step'] ?? 'precontractual' );
		if ( ! array_key_exists( $step, $this->steps ) ) {
			$step = 'precontractual';
		}

		$this->render_styles();
		?>
		<div class="wrap wlr-wizard">
			<h1 class="wlr-wizard-title">
				<?php esc_html_e( 'Configurazione guidata – Woo Legal Returns', 'woo-legal-returns' ); ?>
			</h1>
			<?php $this->render_step_indicator( $step ); ?>
			<div class="wlr-wizard-card">
				<?php
				switch ( $step ) {
					case 'menu':     $this->render_step_menu();     break;
					case 'checkout_notice': $this->render_step_checkout_notice(); break;
					case 'complete': $this->render_step_complete(); break;
					default:         $this->render_step_precontractual();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_step_indicator( string $current ): void {
		$order = array_keys( $this->steps );
		$pos   = (int) array_search( $current, $order, true );
		echo '<ol class="wlr-wizard-steps">';
		foreach ( $this->steps as $slug => $label ) {
			$idx = (int) array_search( $slug, $order, true );
			if ( $idx < $pos ) {
				$cls = 'done';
			} elseif ( $idx === $pos ) {
				$cls = 'active';
			} else {
				$cls = '';
			}
			echo '<li class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</li>';
		}
		echo '</ol>';
	}

	private function render_step_precontractual(): void {
		$saved_id = (int) $this->get_option( 'precontractual_page_id' );
		$mode     = $saved_id ? 'existing' : 'new';
		$pages    = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] );
		?>
		<h2><?php esc_html_e( 'Pagina Informativa Precontrattuale', 'woo-legal-returns' ); ?></h2>
		<p class="wlr-step-desc">
			<?php esc_html_e( 'Crea o associa una pagina del sito contenente le informazioni obbligatorie sul diritto di recesso (artt. 49–51 D.Lgs. 206/2005, Direttiva 2023/2673). Il contenuto della nuova pagina sarà precompilato con il testo standard aggiornato.', 'woo-legal-returns' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'wlr_wizard_precontractual' ); ?>
			<input type="hidden" name="wlr_wizard_step" value="precontractual">

			<div class="wlr-radio-group">
				<label class="wlr-radio-label">
					<input type="radio" name="page_mode" value="new" <?php checked( $mode, 'new' ); ?>>
					<span>
						<strong><?php esc_html_e( 'Crea una nuova pagina', 'woo-legal-returns' ); ?></strong><br>
						<small><?php esc_html_e( 'Il contenuto verrà precompilato con il testo standard D.Lgs. 209/2025', 'woo-legal-returns' ); ?></small>
					</span>
				</label>
				<div class="wlr-sub-fields" id="wlr-new-fields">
					<label for="wlr_new_page_title"><?php esc_html_e( 'Titolo pagina', 'woo-legal-returns' ); ?></label>
					<input type="text" id="wlr_new_page_title" name="new_page_title" class="regular-text"
						value="<?php echo esc_attr( __( 'Informativa sul Diritto di Recesso', 'woo-legal-returns' ) ); ?>">
				</div>

				<label class="wlr-radio-label">
					<input type="radio" name="page_mode" value="existing" <?php checked( $mode, 'existing' ); ?>>
					<span>
						<strong><?php esc_html_e( 'Usa una pagina già esistente', 'woo-legal-returns' ); ?></strong><br>
						<small><?php esc_html_e( 'Seleziona una pagina già presente nel sito', 'woo-legal-returns' ); ?></small>
					</span>
				</label>
				<div class="wlr-sub-fields" id="wlr-existing-fields">
					<select name="existing_page_id">
						<option value=""><?php esc_html_e( '— Seleziona una pagina —', 'woo-legal-returns' ); ?></option>
						<?php foreach ( $pages as $p ) : ?>
						<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $saved_id, $p->ID ); ?>>
							<?php echo esc_html( $p->post_title ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="wlr-wizard-actions">
				<div></div>
				<div class="wlr-actions-right">
					<a href="<?php echo esc_url( $this->wizard_url( 'menu' ) ); ?>" class="wlr-skip">
						<?php esc_html_e( 'Salta questo passaggio', 'woo-legal-returns' ); ?>
					</a>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Avanti →', 'woo-legal-returns' ); ?>
					</button>
				</div>
			</div>
		</form>

		<script>
		(function () {
			var radios   = document.querySelectorAll('[name="page_mode"]');
			var newDiv   = document.getElementById('wlr-new-fields');
			var existDiv = document.getElementById('wlr-existing-fields');
			function sync() {
				var v = document.querySelector('[name="page_mode"]:checked').value;
				newDiv.style.display   = ( v === 'new' )      ? '' : 'none';
				existDiv.style.display = ( v === 'existing' ) ? '' : 'none';
			}
			radios.forEach( function (r) { r.addEventListener( 'change', sync ); } );
			sync();
		}());
		</script>
		<?php
	}

	private function render_step_menu(): void {
		$saved_ids = (array) $this->get_option( 'menu_ids', [] );
		$nav_menus = wp_get_nav_menus();
		$page_id   = (int) $this->get_option( 'precontractual_page_id' );
		?>
		<h2><?php esc_html_e( 'Menu di Navigazione', 'woo-legal-returns' ); ?></h2>
		<p class="wlr-step-desc">
			<?php esc_html_e( 'Seleziona i menu in cui aggiungere il link alla pagina dell\'informativa. Puoi saltare e farlo manualmente da Aspetto → Menu.', 'woo-legal-returns' ); ?>
		</p>

		<?php if ( $page_id ) : ?>
		<div class="wlr-info-box">
			<?php
			printf(
				/* translators: %s: nome pagina con link */
				esc_html__( 'Pagina configurata: %s', 'woo-legal-returns' ),
				'<strong><a href="' . esc_url( get_edit_post_link( $page_id ) ) . '" target="_blank">'
					. esc_html( get_the_title( $page_id ) ) . '</a></strong>'
			);
			?>
		</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'wlr_wizard_menu' ); ?>
			<input type="hidden" name="wlr_wizard_step" value="menu">

			<?php if ( $nav_menus ) : ?>
				<div class="wlr-checkbox-group">
				<?php foreach ( $nav_menus as $menu ) : ?>
					<label class="wlr-checkbox-label">
						<input type="checkbox" name="menus[]"
							value="<?php echo esc_attr( $menu->term_id ); ?>"
							<?php checked( in_array( $menu->term_id, $saved_ids, true ) ); ?>>
						<span>
							<?php echo esc_html( $menu->name ); ?>
							<small style="color:#a7aaad;">
								(<?php
								printf(
									/* translators: %d: numero voci menu */
									esc_html__( '%d voci', 'woo-legal-returns' ),
									$menu->count
								);
								?>)
							</small>
						</span>
					</label>
				<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Nessun menu trovato. Crea prima un menu da Aspetto → Menu, poi riesegui la configurazione.', 'woo-legal-returns' ); ?>
				</p>
			<?php endif; ?>

			<div class="wlr-wizard-actions">
				<a href="<?php echo esc_url( $this->wizard_url( 'precontractual' ) ); ?>" class="button">
					← <?php esc_html_e( 'Indietro', 'woo-legal-returns' ); ?>
				</a>
				<div class="wlr-actions-right">
					<a href="<?php echo esc_url( $this->wizard_url( 'checkout_notice' ) ); ?>" class="wlr-skip">
						<?php esc_html_e( 'Salta questo passaggio', 'woo-legal-returns' ); ?>
					</a>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Avanti →', 'woo-legal-returns' ); ?>
					</button>
				</div>
			</div>
		</form>
		<?php
	}

	private function save_checkout_notice(): void {
		$enabled = ! empty( $_POST['checkout_notice_enabled'] );
		$text    = sanitize_textarea_field( $_POST['checkout_notice_text'] ?? '' );

		$this->save_option( 'checkout_notice_enabled', $enabled );
		$this->save_option( 'checkout_notice_text', $text );
		$this->save_option( 'setup_complete', true );

		wp_safe_redirect( $this->wizard_url( 'complete' ) );
		exit;
	}

	private function render_step_checkout_notice(): void {
		$enabled  = (bool) $this->get_option( 'checkout_notice_enabled', true );
		$page_id  = (int) $this->get_option( 'precontractual_page_id' );
		$page_url = $page_id ? get_permalink( $page_id ) : '#';
		$default  = sprintf(
			/* translators: %1$d: giorni, %2$s: URL pagina informativa */
			__( 'Hai diritto di recedere dal contratto entro %1$d giorni senza fornire alcuna motivazione (art. 52 Cod. Consumo). <a href="%2$s">Maggiori informazioni</a>', 'woo-legal-returns' ),
			WLR_RETURN_DAYS,
			esc_url( $page_url )
		);
		$text = (string) $this->get_option( 'checkout_notice_text', $default );
		?>
		<h2><?php esc_html_e( 'Avviso al Checkout', 'woo-legal-returns' ); ?></h2>
		<p class="wlr-step-desc">
			<?php esc_html_e( 'Visualizza un avviso informativo sul diritto di recesso nella pagina di checkout, prima del tasto di conferma ordine. Raccomandato per la conformità all\u2019art. 49 D.Lgs. 206/2005.', 'woo-legal-returns' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'wlr_wizard_checkout_notice' ); ?>
			<input type="hidden" name="wlr_wizard_step" value="checkout_notice">

			<div class="wlr-checkbox-group" style="margin-bottom:20px;">
				<label class="wlr-checkbox-label">
					<input type="checkbox" name="checkout_notice_enabled" value="1" <?php checked( $enabled ); ?>>
					<span>
						<strong><?php esc_html_e( 'Mostra avviso nella pagina di checkout', 'woo-legal-returns' ); ?></strong><br>
						<small><?php esc_html_e( 'Appare appena sopra il tasto "Conferma ordine"', 'woo-legal-returns' ); ?></small>
					</span>
				</label>
			</div>

			<div id="wlr-notice-fields">
				<table class="form-table" style="margin:0;">
					<tr>
						<th style="width:140px;"><?php esc_html_e( 'Testo avviso', 'woo-legal-returns' ); ?></th>
						<td>
							<textarea name="checkout_notice_text" rows="3" style="width:100%;max-width:500px;"><?php echo esc_textarea( $text ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Puoi usare tag HTML di base (a, strong, em). Il link viene aggiornato automaticamente se cambi la pagina informativa.', 'woo-legal-returns' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="wlr-wizard-actions">
				<a href="<?php echo esc_url( $this->wizard_url( 'menu' ) ); ?>" class="button">
					&larr; <?php esc_html_e( 'Indietro', 'woo-legal-returns' ); ?>
				</a>
				<div class="wlr-actions-right">
					<a href="<?php echo esc_url( $this->wizard_url( 'complete' ) ); ?>" class="wlr-skip">
						<?php esc_html_e( 'Salta questo passaggio', 'woo-legal-returns' ); ?>
					</a>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Salva e Completa →', 'woo-legal-returns' ); ?>
					</button>
				</div>
			</div>
		</form>

		<script>
		jQuery( function ( $ ) {
			var $check = $( '[name="checkout_notice_enabled"]' );
			var $fields = $( '#wlr-notice-fields' );
			function sync() { $fields.toggle( $check.is( ':checked' ) ); }
			$check.on( 'change', sync );
			sync();
		} );
		</script>
		<?php
	}

	private function render_step_complete(): void {
		$this->save_option( 'setup_complete', true );
		$page_id = (int) $this->get_option( 'precontractual_page_id' );
		?>
		<div style="text-align:center;padding:20px 0 10px;">
			<div style="font-size:3rem;line-height:1;margin-bottom:16px;">✅</div>
			<h2 style="font-size:1.4rem;margin:0 0 12px;">
				<?php esc_html_e( 'Configurazione completata!', 'woo-legal-returns' ); ?>
			</h2>
			<p class="wlr-step-desc">
				<?php esc_html_e( 'Il plugin è operativo. Puoi rieseguire questa configurazione in qualsiasi momento dalla dashboard Resi UE.', 'woo-legal-returns' ); ?>
			</p>
			<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wlr-returns' ) ); ?>"
					class="button button-primary button-large">
					<?php esc_html_e( 'Vai alla dashboard Resi', 'woo-legal-returns' ); ?>
				</a>
				<?php if ( $page_id ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>"
					class="button button-large" target="_blank">
					<?php esc_html_e( 'Modifica pagina informativa', 'woo-legal-returns' ); ?>
				</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Admin notice
	// -------------------------------------------------------------------------

	public function show_setup_notice(): void {
		if ( $this->get_option( 'setup_complete' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && str_contains( $screen->id, self::WIZARD_SLUG ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Woo Legal Returns', 'woo-legal-returns' ); ?></strong>
				<?php esc_html_e( '— Configurazione iniziale non completata.', 'woo-legal-returns' ); ?>
				&nbsp;<a href="<?php echo esc_url( $this->wizard_url( 'precontractual' ) ); ?>" class="button button-small">
					<?php esc_html_e( 'Avvia configurazione guidata →', 'woo-legal-returns' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// CSS
	// -------------------------------------------------------------------------

	private function render_styles(): void {
		?>
		<style>
		.wlr-wizard { max-width: 780px; }
		.wlr-wizard-title { margin-bottom: 24px !important; }
		.wlr-wizard-steps { display: flex; list-style: none; margin: 0 0 28px; padding: 0; counter-reset: step; gap: 0; }
		.wlr-wizard-steps li { flex: 1; text-align: center; font-size: .82rem; color: #a7aaad; padding-bottom: 14px; border-bottom: 3px solid #dcdcde; }
		.wlr-wizard-steps li::before { counter-increment: step; content: counter(step); display: block; width: 28px; height: 28px; line-height: 28px; border-radius: 50%; background: #dcdcde; color: #646970; font-weight: 700; margin: 0 auto 8px; font-size: .85rem; }
		.wlr-wizard-steps li.active { color: #2271b1; border-bottom-color: #2271b1; }
		.wlr-wizard-steps li.active::before { background: #2271b1; color: #fff; }
		.wlr-wizard-steps li.done { color: #1d8348; border-bottom-color: #1d8348; }
		.wlr-wizard-steps li.done::before { background: #1d8348; color: #fff; content: "✓"; }
		.wlr-wizard-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 32px 36px; }
		.wlr-wizard-card h2 { font-size: 1.15rem; margin: 0 0 8px; }
		.wlr-step-desc { color: #646970; margin: 0 0 24px; }
		.wlr-radio-group, .wlr-checkbox-group { display: flex; flex-direction: column; gap: 4px; }
		.wlr-radio-label, .wlr-checkbox-label { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid #dcdcde; border-radius: 4px; cursor: pointer; background: #fafafa; }
		.wlr-radio-label:hover, .wlr-checkbox-label:hover { background: #f0f6ff; border-color: #2271b1; }
		.wlr-radio-label input, .wlr-checkbox-label input { margin-top: 3px; flex-shrink: 0; }
		.wlr-sub-fields { margin: 0 0 12px 26px; padding: 12px 16px; background: #f8f8f8; border-radius: 4px; }
		.wlr-sub-fields label { display: block; font-weight: 600; margin-bottom: 6px; font-size: .85rem; }
		.wlr-info-box { background: #e8f4fd; border-left: 4px solid #2271b1; padding: 12px 16px; border-radius: 2px; margin-bottom: 20px; font-size: .875rem; }
		.wlr-wizard-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 28px; padding-top: 20px; border-top: 1px solid #f0f0f1; }
		.wlr-actions-right { display: flex; align-items: center; gap: 12px; }
		.wlr-skip { color: #646970; font-size: .875rem; text-decoration: none; }
		.wlr-skip:hover { color: #2271b1; }
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	public function wizard_url( string $step ): string {
		return admin_url( 'admin.php?page=' . self::WIZARD_SLUG . '&step=' . rawurlencode( $step ) );
	}

	public static function get_options(): array {
		return (array) get_option( self::OPTION_KEY, [] );
	}

	public function get_option( string $key, $default = null ): mixed {
		return self::get_options()[ $key ] ?? $default;
	}

	private function save_option( string $key, mixed $value ): void {
		$opts         = self::get_options();
		$opts[ $key ] = $value;
		update_option( self::OPTION_KEY, $opts );
	}

	// -------------------------------------------------------------------------
	// Contenuto default pagina informativa
	// -------------------------------------------------------------------------

	private function get_default_page_content(): string {
		$site = get_bloginfo( 'name' );
		$date = date_i18n( get_option( 'date_format' ) );

		// Dati store da WooCommerce.
		$addr1     = get_option( 'woocommerce_store_address', '' );
		$addr2     = get_option( 'woocommerce_store_address_2', '' );
		$city      = get_option( 'woocommerce_store_city', '' );
		$postcode  = get_option( 'woocommerce_store_postcode', '' );
		$raw_cc    = get_option( 'woocommerce_default_country', '' );
		$cc        = $raw_cc ? explode( ':', $raw_cc )[0] : '';
		$country   = ( $cc && function_exists( 'WC' ) )
			? ( WC()->countries->get_countries()[ $cc ] ?? $cc )
			: $cc;
		$email     = get_option( 'woocommerce_email_from_address', '' )
					?: get_option( 'admin_email', '' );

		// Compone indirizzo su una riga; lascia placeholder solo se mancante.
		$addr_parts = array_filter( [
			$addr1,
			$addr2,
			trim( $postcode . ' ' . $city ),
			$country,
		] );
		$address = $addr_parts ? implode( ', ', $addr_parts ) : '[INDIRIZZO COMPLETO]';
		$email   = $email ?: '[EMAIL ASSISTENZA]';

		return "<!-- wp:paragraph -->
<p><strong>Informativa sul Diritto di Recesso</strong><br>
Ai sensi degli artt. 49&#x2013;59 del D.Lgs. 206/2005 (Codice del Consumo), come modificato dal D.Lgs. 209/2025 (recepimento Direttiva UE 2023/2673)</p>
<!-- /wp:paragraph -->

<!-- wp:heading {\"level\":3} -->
<h3>Venditore</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>{$site}</strong><br>
Sede legale: {$address}<br>
Email: {$email}<br>
Telefono: [NUMERO DI TELEFONO]</p>
<!-- /wp:paragraph -->

<!-- wp:heading {\"level\":3} -->
<h3>Diritto di recesso</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Hai il diritto di recedere dal presente contratto entro <strong>14 giorni</strong> senza dover fornire alcuna motivazione.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Il periodo di recesso scade dopo 14 giorni dalla data in cui tu, o un terzo da te designato diverso dal vettore, entri in possesso fisico dei beni.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {\"level\":3} -->
<h3>Come esercitare il diritto di recesso</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>Tramite la <strong>funzione digitale di recesso</strong> disponibile nell&#x27;area personale del sito (Ordini &rarr; Recedere dal contratto) &#x2014; riceverai conferma immediata via email;</li>
<li>Inviando una dichiarazione scritta a: <strong>{$email}</strong>;</li>
<li>Tramite raccomandata A/R a: <strong>{$address}</strong>.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {\"level\":3} -->
<h3>Effetti del recesso</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Rimborseremo tutti i pagamenti ricevuti entro <strong>14 giorni</strong> dalla comunicazione di recesso, utilizzando lo stesso mezzo di pagamento usato per la transazione iniziale, salvo accordo diverso.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {\"level\":3} -->
<h3>Restituzione dei beni</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Dovrai restituire i beni a <strong>{$address}</strong> entro 14 giorni dalla comunicazione di recesso. I costi diretti di restituzione sono a tuo carico, salvo diversa indicazione.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {\"level\":3} -->
<h3>Eccezioni al diritto di recesso (art. 59 D.Lgs. 206/2005)</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>Beni confezionati su misura o personalizzati;</li>
<li>Beni che rischiano di deteriorarsi o scadere rapidamente;</li>
<li>Beni sigillati aperti dopo la consegna non idonei alla restituzione per motivi igienici;</li>
<li>Contenuti digitali su supporto non materiale la cui esecuzione &egrave; iniziata con il previo consenso del consumatore.</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p><em>Aggiornato: {$date} &mdash; D.Lgs. 209/2025 (art. 54-bis Codice del Consumo)</em></p>
<!-- /wp:paragraph -->";
	}
}
