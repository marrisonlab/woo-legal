/* Woo Legal Returns - Frontend JS */
/* global wlrData, jQuery */

( function ( $ ) {
	'use strict';
	if ( ! $( '#wlr-return-form' ).length ) {
		return;
	}

	var confirmationToken = '';
	var itemsRequest = null;
	var itemsGeneration = 0;
	var nonceReady = $.post( wlrData.ajaxUrl, { action: 'wlr_refresh_nonce' } ).then( function ( response ) {
		if ( response.success ) {
			wlrData.nonce = response.data.nonce;
		}
	} );

	function resetConfirmation() {
		confirmationToken = '';
		$( '#wlr-confirmation-summary' ).remove();
		$( '#wlr-submit-btn' ).text( wlrData.i18n.submitBtn );
	}

	$( document ).on( 'change input', '#wlr-return-form :input', function () {
		if ( 'confirmation_token' !== $( this ).attr( 'name' ) ) {
			resetConfirmation();
		}
	} );

	function loadOrderItems() {
		var generation = ++itemsGeneration;
		resetConfirmation();
		if ( itemsRequest ) {
			itemsRequest.abort();
		}
		var orderId = $( '#wlr_order_id' ).val();
		var guestEmail = $( '#wlr_guest_email' ).val() || '';
		var $container = $( '#wlr-items-container' );

		if ( ! orderId ) {
			$container.empty().append(
				$( '<p class="wlr-items-placeholder"><em></em></p>' ).find( 'em' ).text( wlrData.i18n.selectOrder ).end()
			);
			return;
		}

		if ( $( '.wlr-load-order-items' ).length && ! guestEmail ) {
			$container.empty().append( $( '<p class="woocommerce-error"></p>' ).text( wlrData.i18n.guestEmail ) );
			return;
		}

		$container.empty().append(
			$( '<p><em></em></p>' ).find( 'em' ).text( wlrData.i18n.loadingItems ).end()
		);

		nonceReady.then( function () {
		if ( generation !== itemsGeneration ) {
			return;
		}
		itemsRequest = $.post(
			wlrData.ajaxUrl,
			{
				action   : 'wlr_get_order_items',
				nonce    : wlrData.nonce,
				order_id : orderId,
				order_key: $( '#wlr_order_key' ).val() || '',
				guest_email: guestEmail,
			},
			function ( response ) {
				if ( generation !== itemsGeneration ) {
					return;
				}
				if ( response.success ) {
					renderItems( response.data.items );
					return;
				}

				$container.empty().append( $( '<p class="woocommerce-error"></p>' ).text( response.data.message ) );
			}
		).fail( function ( xhr, status ) {
			if ( status === 'abort' || generation !== itemsGeneration ) {
				return;
			}
			$container.empty().append( $( '<p class="woocommerce-error"></p>' ).text( wlrData.i18n.errorGeneric ) );
		} );
		} );
	}

	$( document ).on( 'change', '#wlr_order_id', loadOrderItems );
	$( document ).on( 'click', '.wlr-load-order-items', loadOrderItems );
	$( document ).on( 'change', '#wlr_guest_email', loadOrderItems );
	$( document ).on( 'input', '#wlr_guest_email', function () {
		++itemsGeneration;
		if ( itemsRequest ) {
			itemsRequest.abort();
		}
		$( '#wlr-items-container' ).empty();
	} );

	function renderItems( items ) {
		var $container = $( '#wlr-items-container' );

		if ( ! items || ! items.length ) {
			$container.html( '<p><em>Nessun articolo con quantità disponibile per il recesso.</em></p>' );
			return;
		}

		$container.html( $( '#wlr-items-template' ).html() );

		var $tbody = $( '#wlr-items-tbody' );
		$.each( items, function ( i, item ) {
			var noReturn = !! item.no_return;
			var $row = $( '<tr></tr>' );

			if ( noReturn ) {
				$row.addClass( 'wlr-item-no-return' );
			}

			var $check = $( '<input type="checkbox" class="wlr-item-check">' )
				.attr( 'data-item-id', item.item_id ).attr( 'aria-label', 'Seleziona ' + item.name );

			if ( noReturn ) {
				$check.prop( 'disabled', true ).attr( 'title', item.no_return_reason || 'Escluso dal diritto di recesso' );
			} else {
				$check.prop( 'checked', true );
			}

			var $name = $( '<td></td>' ).text( item.name );
			if ( noReturn ) {
				$name.append( ' ' ).append(
					$( '<span class="wlr-no-return-badge"></span>' )
						.text( 'Non recedibile' )
						.attr( 'title', item.no_return_reason || '' )
				);
			}

			var $qtyCell = $( '<td></td>' );
			if ( noReturn ) {
				$qtyCell.text( '-' );
			} else {
				$qtyCell.append(
					$( '<input type="number" class="wlr-item-qty" style="width:60px;">' )
						.attr( {
							'data-item-id': item.item_id,
							min           : 1,
							max           : item.qty,
							step          : 1,
							'aria-label'  : 'Quantità da rendere: ' + item.name,
						} )
						.val( item.qty )
				);
			}

			$row
				.append( $( '<td></td>' ).append( $check ) )
				.append( $name )
				.append( $( '<td></td>' ).text( item.qty ) )
				.append( $qtyCell );

			$tbody.append( $row );
		} );
	}

	function collectItems() {
		var items = [];

		$( '#wlr-items-tbody tr' ).each( function () {
			var $row = $( this );
			var $check = $row.find( '.wlr-item-check' );

			if ( ! $check.is( ':checked' ) ) {
				return;
			}

			items.push( {
				item_id: $check.data( 'item-id' ),
				qty    : parseInt( $row.find( '.wlr-item-qty' ).val(), 10 ) || 1,
			} );
		} );

		return items;
	}

	$( document ).on( 'submit', '#wlr-return-form', function ( e ) {
		e.preventDefault();

		var $btn = $( '#wlr-submit-btn' );
		var $msg = $( '#wlr-form-messages' );
		var $fields = $( '#wlr-return-form :input:enabled' ).not( '#wlr-submit-btn' );
		$fields.prop( 'disabled', true );

		$btn.prop( 'disabled', true ).text( wlrData.i18n.submitting );
		$msg.hide().removeClass( 'success error' ).text( '' );

		nonceReady.then( function () {
		$.post(
			wlrData.ajaxUrl,
			{
				action              : 'wlr_submit_return',
				nonce               : wlrData.nonce,
				order_id            : $( '#wlr_order_id' ).val(),
				reason              : $( '#wlr_reason' ).val(),
				notes               : $( '#wlr_notes' ).val(),
				items               : JSON.stringify( collectItems() ),
				order_key           : $( '#wlr_order_key' ).val() || '',
				guest_email         : $( '#wlr_guest_email' ).val() || '',
				customer_name       : $( '#wlr_customer_name' ).val() || '',
				confirm_withdrawal  : $( '[name="confirm_withdrawal"]' ).is( ':checked' ) ? '1' : '',
				confirmation_token  : confirmationToken,
			},
			function ( response ) {
				$fields.prop( 'disabled', false );
				$btn.prop( 'disabled', false );

				if ( response.success && response.data.needs_confirmation ) {
					confirmationToken = response.data.token;
					$( '#wlr-confirmation-summary' ).remove();
					$( '#wlr-return-form' ).prepend(
						$( '<div id="wlr-confirmation-summary" tabindex="-1"></div>' ).html( response.data.summary )
					);
					$( '#wlr-confirmation-summary' ).trigger( 'focus' );
					$btn.text( wlrData.i18n.confirmBtn );
					return;
				}

				$btn.text( wlrData.i18n.submitBtn );

				if ( response.success ) {
					$msg.addClass( 'success' ).text( response.data.message ).show();
					window.setTimeout( function () {
						window.location.href = response.data.redirect;
					}, 1200 );
					return;
				}

				$msg.addClass( 'error' ).text( response.data.message ).show().trigger( 'focus' );
				resetConfirmation();
			}
		).fail( function () {
			$fields.prop( 'disabled', false );
			$btn.prop( 'disabled', false ).text( wlrData.i18n.submitBtn );
			$msg.addClass( 'error' ).text( wlrData.i18n.errorGeneric ).show();
		} );
		}, function () {
			$fields.prop( 'disabled', false );
			$btn.prop( 'disabled', false ).text( wlrData.i18n.submitBtn );
			$msg.addClass( 'error' ).text( wlrData.i18n.errorGeneric ).show();
		} );
	} );

	var $orderSelect = $( '#wlr_order_id' );
	if ( $orderSelect.val() ) {
		$orderSelect.trigger( 'change' );
	}
} )( jQuery );
