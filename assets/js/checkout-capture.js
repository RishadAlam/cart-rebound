/**
 * Cart Rebound — front-end guest checkout capture beacon.
 *
 * Posts the email/name/phone a guest types at checkout to the capture endpoint
 * so the cart row is back-filled before the order is submitted. Works on the
 * classic checkout; the block checkout is captured server-side via the Store API.
 */
( function () {
	'use strict';

	var config = window.cartReboundCheckout;

	if ( ! config || ! config.endpoint ) {
		return;
	}

	var timer = null;

	function fieldValue( id ) {
		var el = document.getElementById( id );

		return el && typeof el.value === 'string' ? el.value.trim() : '';
	}

	function collect() {
		return {
			email: fieldValue( 'billing_email' ),
			first_name: fieldValue( 'billing_first_name' ),
			last_name: fieldValue( 'billing_last_name' ),
			phone: fieldValue( 'billing_phone' )
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

	document.addEventListener(
		'change',
		function ( event ) {
			var target = event.target;

			if ( target && target.id && target.id.indexOf( 'billing_' ) === 0 ) {
				schedule();
			}
		},
		true
	);

	document.addEventListener(
		'blur',
		function ( event ) {
			var target = event.target;

			if ( target && target.id === 'billing_email' ) {
				send();
			}
		},
		true
	);
} )();
