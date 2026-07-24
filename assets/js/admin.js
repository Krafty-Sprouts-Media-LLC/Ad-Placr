( function() {
	'use strict';

	/**
	 * Return an alphabetic label for a zero-based row index.
	 *
	 * @param {number} index Zero-based index.
	 * @return {string} A, B, … AA.
	 */
	function alphabeticLabel( index ) {
		let number = index + 1;
		let label = '';

		while ( number > 0 ) {
			number -= 1;
			label = String.fromCharCode( 65 + ( number % 26 ) ) + label;
			number = Math.floor( number / 26 );
		}

		return label;
	}

	/**
	 * Generate a stable browser-side ID for a newly cloned row.
	 *
	 * @return {string} UUID or timestamp/random fallback.
	 */
	function generateId() {
		if ( window.crypto && 'function' === typeof window.crypto.randomUUID ) {
			return window.crypto.randomUUID();
		}

		return 'version-' + Date.now().toString( 36 ) + '-' +
			Math.random().toString( 36 ).slice( 2, 10 );
	}

	/**
	 * Announce a completed row action through WordPress core accessibility.
	 *
	 * @param {string} message Announcement.
	 */
	function speak( message ) {
		if ( window.wp && window.wp.a11y && 'function' === typeof window.wp.a11y.speak ) {
			window.wp.a11y.speak( message );
		}
	}

	/**
	 * Reveal only controls associated with the selected display location.
	 */
	function updateLocationControls() {
		const select = document.querySelector( '[data-ad-placr-location]' );

		if ( ! select ) {
			return;
		}

		document.querySelectorAll( '[data-ad-placr-location-control]' ).forEach( function( control ) {
			const locations = control.getAttribute( 'data-ad-placr-location-control' ).split( /\s+/ );
			control.hidden = ! locations.includes( select.value );
		} );
	}

	/**
	 * Keep default names predictable without overwriting user-entered names.
	 *
	 * @param {HTMLElement} root Version editor root.
	 */
	function fillEmptyNames( root ) {
		root.querySelectorAll( '[data-ad-placr-version-row]' ).forEach( function( row, index ) {
			const input = row.querySelector( '[data-ad-placr-version-name]' );
			const heading = row.querySelector( '[data-ad-placr-version-heading]' );
			const versionLabel = window.adPlacrAdmin ? window.adPlacrAdmin.versionLabel : 'Version';

			if ( input && '' === input.value.trim() ) {
				input.value = versionLabel + ' ' + alphabeticLabel( index );
			}

			if ( heading && input ) {
				heading.textContent = input.value;
			}
		} );
	}

	/**
	 * Calculate traffic share from enabled rows that contain usable code.
	 *
	 * @param {HTMLElement} root Version editor root.
	 */
	function updateShares( root ) {
		const rows = Array.from( root.querySelectorAll( '[data-ad-placr-version-row]' ) );
		const eligible = rows.filter( function( row ) {
			const enabled = row.querySelector( '[data-ad-placr-version-enabled]' );
			const code = row.querySelector( '[data-ad-placr-version-code]' );
			const mobile = row.querySelector( '[data-ad-placr-version-mobile]' );

			return enabled && enabled.checked &&
				( ( code && '' !== code.value.trim() ) || ( mobile && '' !== mobile.value.trim() ) );
		} );
		const total = eligible.reduce( function( sum, row ) {
			const weight = row.querySelector( '[data-ad-placr-version-weight]' );
			return sum + Math.max( 1, Number.parseInt( weight ? weight.value : '1', 10 ) || 1 );
		}, 0 );

		rows.forEach( function( row ) {
			const output = row.querySelector( '[data-ad-placr-version-share]' );
			const weight = row.querySelector( '[data-ad-placr-version-weight]' );
			const rowWeight = Math.max( 1, Number.parseInt( weight ? weight.value : '1', 10 ) || 1 );
			const share = total > 0 && eligible.includes( row ) ? ( rowWeight / total ) * 100 : 0;

			if ( output ) {
				output.textContent = Number.isInteger( share ) ? share + '%' : share.toFixed( 1 ) + '%';
			}
		} );
	}

	/**
	 * Initialize the progressively disclosed version editor.
	 *
	 * @param {HTMLElement} root Version editor root.
	 */
	function initializeVersions( root ) {
		const list = root.querySelector( '[data-ad-placr-version-list]' );
		const template = root.querySelector( '[data-ad-placr-version-template]' );
		const addButton = root.querySelector( '[data-ad-placr-add-version]' );
		let nextIndex = root.querySelectorAll( '[data-ad-placr-version-row]' ).length;

		if ( ! list || ! template || ! addButton ) {
			return;
		}

		fillEmptyNames( root );
		updateShares( root );

		addButton.addEventListener( 'click', function() {
			const wrapper = document.createElement( 'div' );
			wrapper.innerHTML = template.innerHTML.replaceAll( '__INDEX__', String( nextIndex ) ).trim();
			const row = wrapper.firstElementChild;

			nextIndex += 1;
			if ( ! row ) {
				return;
			}

			const idInput = row.querySelector( '[data-ad-placr-version-id]' );
			if ( idInput ) {
				idInput.value = generateId();
			}

			list.appendChild( row );
			root.dataset.multiple = '1';
			fillEmptyNames( root );
			updateShares( root );

			const firstInput = row.querySelector( 'input:not([type="hidden"]), textarea' );
			if ( firstInput ) {
				firstInput.focus();
			}

			speak( window.adPlacrAdmin ? window.adPlacrAdmin.versionAdded : 'Ad version added.' );
		} );

		root.addEventListener( 'click', function( event ) {
			const removeButton = event.target.closest( '[data-ad-placr-remove-version]' );

			if ( ! removeButton ) {
				return;
			}

			const rows = root.querySelectorAll( '[data-ad-placr-version-row]' );
			if ( rows.length <= 1 ) {
				return;
			}

			const row = removeButton.closest( '[data-ad-placr-version-row]' );
			if ( row ) {
				row.remove();
			}

			root.dataset.multiple = root.querySelectorAll( '[data-ad-placr-version-row]' ).length > 1 ? '1' : '0';
			fillEmptyNames( root );
			updateShares( root );
			addButton.focus();
			speak( window.adPlacrAdmin ? window.adPlacrAdmin.versionRemoved : 'Ad version removed.' );
		} );

		root.addEventListener( 'input', function( event ) {
			if ( event.target.matches( '[data-ad-placr-version-name]' ) ) {
				const row = event.target.closest( '[data-ad-placr-version-row]' );
				const heading = row ? row.querySelector( '[data-ad-placr-version-heading]' ) : null;
				if ( heading ) {
					heading.textContent = event.target.value;
				}
			}

			updateShares( root );
		} );

		root.addEventListener( 'change', function() {
			updateShares( root );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		const location = document.querySelector( '[data-ad-placr-location]' );
		if ( location ) {
			location.addEventListener( 'change', updateLocationControls );
			updateLocationControls();
		}

		document.querySelectorAll( '[data-ad-placr-versions]' ).forEach( initializeVersions );
	} );
}() );
