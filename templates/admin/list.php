<?php
/**
 * Template admin: Elenco richieste di reso.
 *
 * Variabili disponibili: $posts, $total, $paged, $status_filter, $search.
 */

defined( 'ABSPATH' ) || exit;

$per_page   = 20;
$total_pages = ceil( $total / $per_page );
?>

<div class="wrap wlr-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Richieste di Reso UE', 'woo-legal-returns' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Stato aggiornato con successo.', 'woo-legal-returns' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Richiesta spostata nel cestino.', 'woo-legal-returns' ); ?></p></div>
	<?php endif; ?>

	<!-- Filtri status -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wlr-returns' ) ); ?>"
				class="<?php echo ! $status_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'Tutti', 'woo-legal-returns' ); ?>
				<span class="count">(<?php echo esc_html( $total ); ?>)</span>
			</a> |
		</li>
		<?php foreach ( WLR_Post_Type::STATUSES as $slug => $label ) :
			$count = wp_count_posts( WLR_Post_Type::POST_TYPE )->{$slug} ?? 0;
		?>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wlr-returns&status=' . $slug ) ); ?>"
				class="<?php echo $status_filter === $slug ? 'current' : ''; ?>">
				<?php echo esc_html( $label ); ?>
				<span class="count">(<?php echo esc_html( $count ); ?>)</span>
			</a>
			<?php echo $slug !== array_key_last( WLR_Post_Type::STATUSES ) ? ' | ' : ''; ?>
		</li>
		<?php endforeach; ?>
	</ul>

	<!-- Ricerca -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-form">
		<input type="hidden" name="page" value="wlr-returns">
		<?php if ( $status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
		<?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
			placeholder="<?php esc_attr_e( 'Cerca per # ordine o cliente…', 'woo-legal-returns' ); ?>">
		<?php submit_button( __( 'Cerca', 'woo-legal-returns' ), 'button', '', false ); ?>
	</form>

	<table class="wp-list-table widefat fixed striped posts">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Richiesta #', 'woo-legal-returns' ); ?></th>
				<th><?php esc_html_e( 'Ordine', 'woo-legal-returns' ); ?></th>
				<th><?php esc_html_e( 'Cliente', 'woo-legal-returns' ); ?></th>
				<th><?php esc_html_e( 'Motivo', 'woo-legal-returns' ); ?></th>
				<th><?php esc_html_e( 'Data', 'woo-legal-returns' ); ?></th>
				<th><?php esc_html_e( 'Stato', 'woo-legal-returns' ); ?></th>
				<th><?php esc_html_e( 'Azioni', 'woo-legal-returns' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $posts ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'Nessuna richiesta trovata.', 'woo-legal-returns' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $posts as $post ) :
					$order_id    = (int) get_post_meta( $post->ID, '_wlr_order_id', true );
					$customer_id = (int) get_post_meta( $post->ID, '_wlr_customer_id', true );
					$reason      = get_post_meta( $post->ID, '_wlr_reason', true );
					$created_at  = get_post_meta( $post->ID, '_wlr_created_at', true );
					$order       = wc_get_order( $order_id );
					$customer    = get_userdata( $customer_id );
					$detail_url  = admin_url( 'admin.php?page=wlr-returns&action=view&id=' . $post->ID );
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( $detail_url ); ?>">
							<strong>#<?php echo esc_html( $post->ID ); ?></strong>
						</a>
					</td>
					<td>
						<?php if ( $order ) : ?>
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" target="_blank">
								#<?php echo esc_html( $order->get_order_number() ); ?>
							</a>
						<?php else : ?>
							#<?php echo esc_html( $order_id ); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $customer ) : ?>
							<a href="<?php echo esc_url( get_edit_user_link( $customer_id ) ); ?>">
								<?php echo esc_html( $customer->display_name ); ?>
							</a><br>
							<small><?php echo esc_html( $customer->user_email ); ?></small>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $reason ); ?></td>
					<td>
						<?php echo esc_html( $created_at ? date_i18n( get_option( 'date_format' ), strtotime( $created_at ) ) : '—' ); ?>
					</td>
					<td>
						<span class="wlr-badge <?php echo esc_attr( WLR_Post_Type::get_status_class( $post->post_status ) ); ?>">
							<?php echo esc_html( WLR_Post_Type::get_status_label( $post->post_status ) ); ?>
						</span>
					</td>
					<td>
						<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small">
							<?php esc_html_e( 'Gestisci', 'woo-legal-returns' ); ?>
						</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Paginazione -->
	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<?php
			echo wp_kses_post( paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'total'     => $total_pages,
				'current'   => $paged,
			] ) );
			?>
		</div>
	</div>
	<?php endif; ?>
</div>
