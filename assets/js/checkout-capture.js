/**
 * Cart Rebound — front-end guest checkout capture beacon.
 *
 * Posts the email/name/phone a guest types at checkout to the capture endpoint
 * so the cart row is back-filled before the order is submitted. Handles both the
 * classic checkout (`#billing_email`) and the block checkout (`#email`), whose
 * fields use different ids; the block path is also captured server-side via the
 * Store API as a no-JS fallback.
 */
( function () {
	'use strict';

	var config = window.cartReboundCheckout;

	if ( ! config || ! config.endpoint ) {
		return;
	}

	var timer = null;

	// Each identity field can live under a classic id or a block id — first hit
	// wins. On the block checkout the address fields are prefixed by the form
	// that is shown: `billing-*` only when a separate billing address is used,
	// otherwise the shared `shipping-*` form, so both are tried.
	var FIELDS = {
		email: [ 'billing_email', 'email' ],
		first_name: [ 'billing_first_name', 'billing-first_name', 'shipping-first_name' ],
		last_name: [ 'billing_last_name', 'billing-last_name', 'shipping-last_name' ],
		phone: [ 'billing_phone', 'billing-phone', 'shipping-phone' ]
	};

	function fieldValue( ids ) {
		for ( var i = 0; i < ids.length; i++ ) {
			var el = document.getElementById( ids[ i ] );

			if ( el && typeof el.value === 'string' && el.value.trim() !== '' ) {
				return el.value.trim();
			}
		}

		return '';
	}

	function collect() {
		return {
			email: fieldValue( FIELDS.email ),
			first_name: fieldValue( FIELDS.first_name ),
			last_name: fieldValue( FIELDS.last_name ),
			phone: fieldValue( FIELDS.phone )
		};
	}

	function send() {
		var data = collect();

		if ( ! data.email ) {
			return;
		}

		fetch( config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify( data )
		} ).catch( function () {} );
	}

	function schedule() {
		if ( timer ) {
			window.clearTimeout( timer );
		}

		timer = window.setTimeout( send, 600 );
	}

	// True for any classic (`billing_*`) or block (`billing-*`, `email`) identity field.
	function isIdentityField( target ) {
		if ( ! target || ! target.id ) {
			return false;
		}

		return (
			target.id === 'email' ||
			target.id.indexOf( 'billing_' ) === 0 ||
			target.id.indexOf( 'billing-' ) === 0 ||
			target.id.indexOf( 'shipping-' ) === 0
		);
	}

	document.addEventListener(
		'change',
		function ( event ) {
			if ( isIdentityField( event.target ) ) {
				schedule();
			}
		},
		true
	);

	document.addEventListener(
		'blur',
		function ( event ) {
			var target = event.target;

			if ( target && ( target.id === 'billing_email' || target.id === 'email' ) ) {
				send();
			}
		},
		true
	);
} )();
