( function () {
	'use strict';

	var config = window.cartReboundCheckout;

	if ( ! config || ! config.endpoint ) {
		return;
	}

	var timer = null;
	var lastSent = '';

	// Block checkout uses `billing-*` ids only with a separate billing address, else `shipping-*`.
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
		if ( timer ) {
			window.clearTimeout( timer );
			timer = null;
		}

		var data = collect();
		var body = JSON.stringify( data );

		if ( ! data.email || body === lastSent ) {
			return;
		}

		lastSent = body;

		fetch( config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: body
		} )
			.then( function ( response ) {
				// Allow a retry on the next change, e.g. after a stale nonce on a cached page.
				if ( ! response.ok ) {
					lastSent = '';
				}
			} )
			.catch( function () {
				lastSent = '';
			} );
	}

	function schedule() {
		if ( timer ) {
			window.clearTimeout( timer );
		}

		timer = window.setTimeout( send, 600 );
	}

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
