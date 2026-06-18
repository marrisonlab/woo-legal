/* Woo Legal Returns – Admin JS */
/* global wlrAdmin, jQuery */

( function ( $ ) {
	'use strict';

	$( '#wlr-update-status-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		var $btn     = $( '#wlr-save-status-btn' );
		var $msg     = $( '#wlr-admin-messages' );
		var returnId = $( 'input[name=return_id]', this ).val();
		var status   = $( '#wlr_new_status' ).val();
		var note     = $( '#wlr_admin_note' ).val();

		$btn.prop( 'disabled', true ).text( 'Salvataggio…' );
		$msg.text( '' ).removeClass( 'success error' );

		$.post(
			wlrAdmin.ajaxUrl,
			{
				action   : 'wlr_update_status',
				nonce    : wlrAdmin.nonce,
				return_id: returnId,
				status   : status,
				note     : note,
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Salva modifiche' );

				if ( response.success ) {
					$msg.addClass( 'success' ).text( response.data.message );

					// Aggiorna il badge stato in pagina senza ricaricare.
					var $badge = $( '.wlr-badge' ).first();
					$badge
						.text( response.data.status_label )
						.removeClass( 'wlr-badge--pending wlr-badge--success wlr-badge--error wlr-badge--info wlr-badge--muted' );

					var classMap = {
						'wlr-requested': 'wlr-badge--pending',
						'wlr-approved' : 'wlr-badge--success',
						'wlr-rejected' : 'wlr-badge--error',
						'wlr-refunded' : 'wlr-badge--info',
						'wlr-cancelled': 'wlr-badge--muted',
					};
					if ( classMap[ response.data.new_status ] ) {
						$badge.addClass( classMap[ response.data.new_status ] );
					}

					$( '#wlr_admin_note' ).val( '' );
				} else {
					$msg.addClass( 'error' ).text( response.data.message );
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Salva modifiche' );
			$msg.addClass( 'error' ).text( 'Errore di connessione. Riprova.' );
		} );
	} );

} )( jQuery );
