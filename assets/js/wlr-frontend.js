/* Woo Legal Returns – Frontend JS */
/* global wlrData, jQuery */

( function ( $ ) {
	'use strict';

	// Carica i prodotti dell'ordine selezionato via AJAX.
	$( '#wlr_order_id' ).on( 'change', function () {
		var orderId = $( this ).val();
		var $container = $( '#wlr-items-container' );

		if ( ! orderId ) {
			$container.html(
				'<p class="wlr-items-placeholder"><em>' +
				wlrData.i18n.confirmSubmit +
				'</em></p>'
			);
			return;
		}

		$container.html( '<p><em>Caricamento prodotti…</em></p>' );

		$.post(
			wlrData.ajaxUrl,
			{
				action   : 'wlr_get_order_items',
				nonce    : wlrData.nonce,
				order_id : orderId,
				order_key: $( '#wlr_order_key' ).val() || '',
			},
			function ( response ) {
				if ( response.success ) {
					renderItems( response.data.items );
				} else {
					$container.html( '<p class="woocommerce-error">' + response.data.message + '</p>' );
				}
			}
		).fail( function () {
			$container.html( '<p class="woocommerce-error">' + wlrData.i18n.errorGeneric + '</p>' );
		} );
	} );

	function renderItems( items ) {
		if ( ! items || ! items.length ) {
			$( '#wlr-items-container' ).html(
				'<p><em>Nessun prodotto fisico trovato in questo ordine.</em></p>'
			);
			return;
		}

		var tpl = $( '#wlr-items-template' ).html();
		$( '#wlr-items-container' ).html( tpl );

		var $tbody = $( '#wlr-items-tbody' );
		$.each( items, function ( i, item ) {
			var row = '<tr>' +
				'<td><input type="checkbox" class="wlr-item-check" data-item-id="' + item.item_id + '" checked></td>' +
				'<td>' + item.name + '</td>' +
				'<td>' + item.qty + '</td>' +
				'<td>' +
					'<input type="number" class="wlr-item-qty" data-item-id="' + item.item_id + '"' +
					' value="' + item.qty + '" min="1" max="' + item.qty + '" style="width:60px;">' +
				'</td>' +
			'</tr>';
			$tbody.append( row );
		} );
	}

	// Raccoglie gli item selezionati dal form.
	function collectItems() {
		var items = [];
		$( '#wlr-items-tbody tr' ).each( function () {
			var $row     = $( this );
			var $check   = $row.find( '.wlr-item-check' );
			if ( ! $check.is( ':checked' ) ) {
				return;
			}
			var itemId = $check.data( 'item-id' );
			var qty    = parseInt( $row.find( '.wlr-item-qty' ).val(), 10 ) || 1;
			items.push( { item_id: itemId, qty: qty } );
		} );
		return items;
	}

	// Submit AJAX del form di recesso.
	$( '#wlr-return-form' ).on( 'submit', function ( e ) {
		e.preventDefault();

		if ( ! confirm( wlrData.i18n.confirmSubmit ) ) {
			return;
		}

		var $btn   = $( '#wlr-submit-btn' );
		var $msg   = $( '#wlr-form-messages' );

		$btn.prop( 'disabled', true ).text( wlrData.i18n.submitting );
		$msg.hide().removeClass( 'success error' );

		// Aggiorna nonce AJAX nel campo hidden.
		$( '#wlr_ajax_nonce' ).val( wlrData.nonce );

		$.post(
			wlrData.ajaxUrl,
			{
				action      : 'wlr_submit_return',
				nonce       : wlrData.nonce,
				order_id    : $( '#wlr_order_id' ).val(),
				reason      : $( '#wlr_reason' ).val(),
				notes       : $( '#wlr_notes' ).val(),
				items       : JSON.stringify( collectItems() ),
				order_key   : $( '#wlr_order_key' ).val() || '',
				guest_email : $( '#wlr_guest_email' ).val() || '',
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( 'Invia richiesta di recesso' );

				if ( response.success ) {
					$msg.addClass( 'success' ).text( response.data.message ).show();
					setTimeout( function () {
						window.location.href = response.data.redirect;
					}, 1800 );
				} else {
					$msg.addClass( 'error' ).text( response.data.message ).show();
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Invia richiesta di recesso' );
			$msg.addClass( 'error' ).text( wlrData.i18n.errorGeneric ).show();
		} );
	} );

	// Carica i prodotti all'apertura se un ordine è preselezionato.
	var $orderSelect = $( '#wlr_order_id' );
	if ( $orderSelect.val() ) {
		$orderSelect.trigger( 'change' );
	}

} )( jQuery );
