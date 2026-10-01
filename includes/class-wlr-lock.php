<?php
/**
 * Connection-scoped locks: serialise writes without leaving stale option locks.
 *
 * @package Woo_Legal_Returns
 */

defined( 'ABSPATH' ) || exit;

final class WLR_Lock {
	public static function acquire( string $lock_key ): string|WP_Error {
		global $wpdb;
		$name = 'wlr_' . substr( hash( 'sha256', $wpdb->prefix . $lock_key ), 0, 48 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic database lock, released on connection close.
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
		if ( '1' !== (string) $locked ) {
			return new WP_Error( 'wlr_busy', __( 'Operazione temporaneamente occupata. Riprova tra qualche secondo.', 'woo-legal-returns' ) );
		}
		return $name;
	}

	public static function release( string $name ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release only the lock acquired on this connection.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}
}
