/**
 * Re-run the checkout review whenever the cart changes beside it.
 *
 * Debounced: a scan fires the fragments refresh, and a quantity change can
 * fire two of these events in a row; one review request is enough.
 * update_checkout ends in `updated_checkout`, which is not listened to here,
 * so this cannot loop.
 */
( function ( $ ) {
	var timer;

	$( document.body ).on(
		'added_to_cart removed_from_cart updated_cart_totals wc_fragments_refreshed',
		function () {
			clearTimeout( timer );
			timer = setTimeout( function () {
				$( document.body ).trigger( 'update_checkout' );
			}, 150 );
		}
	);
} )( jQuery );
