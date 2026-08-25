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
	 *
	 * Alignment, devices, paragraph, and manual-embed panels are derived from
	 * the localized position config so the script never hardcodes keys.
	 */
	function getPositionConfig( key ) {
		const positions = window.adPlacrAdmin && window.adPlacrAdmin.positions ? window.adPlacrAdmin.positions : {};
		return Object.prototype.hasOwnProperty.call( positions, key ) ? positions[ key ] : null;
	}

	function getPositionInput() {
		return document.querySelector( '[data-ad-placr-position-input]' );
	}

	function syncMinimap( key ) {
		document.querySelectorAll( '.ad-placr-wf-slot[data-pos]' ).forEach( function( slot ) {
			const pos = slot.getAttribute( 'data-pos' );
			const active = pos === key || ( 'in_content' === pos && /^in_content_/.test( key ) );
			slot.classList.toggle( 'is-active', active );
		} );
	}

	function setWfContext( ctx ) {
		document.querySelectorAll( '.ad-placr-wf-slot[data-pos]' ).forEach( function( slot ) {
			const pos = slot.getAttribute( 'data-pos' ) || '';
			if ( /^(front_page|blog_index|archive)_(top|bottom)$/.test( pos ) ) {
				slot.hidden = true;
			}
		} );

		const listingPrefix = {
			front_page: 'front_page_',
			blog_index: 'blog_index_',
			archive: 'archive_'
		}[ ctx ];

		if ( listingPrefix ) {
			[ 'top', 'bottom' ].forEach( function( end ) {
				const el = document.querySelector( '.ad-placr-wf-slot[data-pos="' + listingPrefix + end + '"]' );
				if ( el ) {
					el.hidden = false;
				}
			} );
		}

		[ 'before_post_content', 'after_post_content', 'in_content' ].forEach( function( pos ) {
			const el = document.querySelector( '.ad-placr-wf-slot[data-pos="' + pos + '"]' );
			if ( el ) {
				el.hidden = 'single' !== ctx;
			}
		} );

		const widget = document.querySelector( '.ad-placr-wf-slot[data-pos="sidebar_widget"]' );
		if ( widget ) {
			widget.hidden = 'single' !== ctx;
		}
	}

	function updateLocationControls() {
		const input = getPositionInput();

		if ( ! input ) {
			return;
		}

		const key = input.value;
		const config = getPositionConfig( key );
		let visibleControls = 0;

		document.querySelectorAll( '[data-ad-placr-location-control]' ).forEach( function( control ) {
			const token = control.getAttribute( 'data-ad-placr-location-control' ) || '';
			let show = false;

			if ( '__align__' === token ) {
				show = ! ! ( config && config.align );
			} else if ( '__devices__' === token ) {
				show = ! ! ( config && 'sticky' === config.group );
			} else {
				show = token.split( /\s+/ ).includes( key );
			}

			control.hidden = ! show;
			if ( show ) {
				visibleControls += 1;
			}
		} );

		const psettings = document.querySelector( '[data-ad-placr-psettings]' );
		if ( psettings ) {
			psettings.hidden = 0 === visibleControls;
		}

		const paraLabel = document.querySelector( '[data-ad-placr-para-label]' );
		if ( paraLabel && config && config.para ) {
			paraLabel.textContent = 'before' === config.para
				? ( window.adPlacrAdmin && window.adPlacrAdmin.insertBefore ? window.adPlacrAdmin.insertBefore : 'Insert before paragraph' )
				: ( window.adPlacrAdmin && window.adPlacrAdmin.insertAfter ? window.adPlacrAdmin.insertAfter : 'Insert after paragraph' );
		}

		const note = document.querySelector( '[data-ad-placr-scope-note]' );
		if ( note ) {
			const notes = window.adPlacrAdmin && window.adPlacrAdmin.scopeNotes ? window.adPlacrAdmin.scopeNotes : {};
			let text = '';

			if ( config ) {
				if ( 'global' === config.context ) {
					text = notes.global || '';
				} else if ( 'singular' === config.context ) {
					text = notes.singular || '';
				} else if ( 'manual' === config.context || 'widget' === config.context ) {
					text = notes.manual || '';
				} else {
					text = notes.listing || '';
				}
			}

			note.textContent = text;
			note.hidden = '' === text;
		}

		document.querySelectorAll( '[data-ad-placr-rule]' ).forEach( function( field ) {
			const rule = field.getAttribute( 'data-ad-placr-rule' );
			const always = 'visitors' === rule || 'schedule' === rule;
			const listed = config && Array.isArray( config.rules ) && config.rules.indexOf( rule ) !== -1;
			field.hidden = ! ( always || listed );
		} );

		const rulesHint = document.querySelector( '[data-ad-placr-rules-hint]' );
		if ( rulesHint ) {
			const hints = window.adPlacrAdmin && window.adPlacrAdmin.ruleHints ? window.adPlacrAdmin.ruleHints : {};
			const hintContext = config && config.context ? config.context : '';
			if ( hintContext && hints[ hintContext ] ) {
				rulesHint.textContent = hints[ hintContext ];
			}
		}

		syncMinimap( key );
	}

	function showArea( group ) {
		document.querySelectorAll( '[data-ad-placr-areas] [data-area]' ).forEach( function( btn ) {
			btn.classList.toggle( 'is-active', btn.getAttribute( 'data-area' ) === group );
		} );

		const spots = document.querySelector( '[data-ad-placr-spots]' );
		if ( ! spots ) {
			return;
		}

		spots.hidden = '' === group;
		spots.querySelectorAll( '[data-pos]' ).forEach( function( btn ) {
			btn.hidden = btn.getAttribute( 'data-group' ) !== group;
		} );
	}

	function setPosition( key ) {
		const input = getPositionInput();
		if ( ! input ) {
			return;
		}

		input.value = key;
		const config = getPositionConfig( key );
		showArea( config && config.group ? config.group : '' );

		document.querySelectorAll( '[data-ad-placr-spots] [data-pos]' ).forEach( function( btn ) {
			btn.classList.toggle( 'is-active', btn.getAttribute( 'data-pos' ) === key );
		} );

		updateLocationControls();
	}

	function initializePlacementPicker( root ) {
		root.addEventListener( 'click', function( event ) {
			const areaBtn = event.target.closest( '[data-ad-placr-areas] [data-area]' );
			if ( areaBtn ) {
				showArea( areaBtn.getAttribute( 'data-area' ) );
				return;
			}

			const spotBtn = event.target.closest( '[data-ad-placr-spots] [data-pos], .ad-placr-wf-slot[data-pos]' );
			if ( spotBtn ) {
				let key = spotBtn.getAttribute( 'data-pos' );
				if ( 'in_content' === key ) {
					const current = getPositionInput() ? getPositionInput().value : '';
					key = /^in_content_/.test( current ) ? current : 'in_content_after_paragraph';
				}
				setPosition( key );
				return;
			}

			const alignBtn = event.target.closest( '.ad-placr-seg [data-al]' );
			if ( alignBtn ) {
				const hidden = root.querySelector( '[data-ad-placr-alignment-input]' );
				if ( hidden ) {
					hidden.value = alignBtn.getAttribute( 'data-al' );
				}
				root.querySelectorAll( '.ad-placr-seg [data-al]' ).forEach( function( btn ) {
					btn.classList.toggle( 'is-active', btn === alignBtn );
				} );
				return;
			}

			const step = event.target.closest( '[data-ad-placr-para-step]' );
			if ( step ) {
				const para = document.getElementById( 'ad-placr-paragraph' );
				if ( para ) {
					const next = Math.max( 1, Math.min( 100, ( parseInt( para.value, 10 ) || 1 ) + parseInt( step.getAttribute( 'data-ad-placr-para-step' ), 10 ) ) );
					para.value = String( next );
				}
			}
		} );

		root.addEventListener( 'change', function( event ) {
			const chip = event.target.closest( '.ad-placr-chip' );
			if ( chip && event.target.matches( 'input[type="checkbox"]' ) ) {
				chip.classList.toggle( 'is-active', event.target.checked );
			}

			if ( event.target.matches( '[data-ad-placr-wf-context]' ) ) {
				setWfContext( event.target.value );
			}
		} );

		const input = getPositionInput();
		if ( input && '' !== input.value ) {
			setPosition( input.value );
		} else {
			updateLocationControls();
		}

		const wfContext = root.querySelector( '[data-ad-placr-wf-context]' );
		if ( wfContext ) {
			setWfContext( wfContext.value );
		}
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
		const placement = document.querySelector( '.ad-placr-placement' );
		if ( placement ) {
			initializePlacementPicker( placement );
		} else {
			const location = document.querySelector( '[data-ad-placr-location]' );
			if ( location ) {
				location.addEventListener( 'change', updateLocationControls );
				updateLocationControls();
			}
		}

		document.querySelectorAll( '[data-ad-placr-versions]' ).forEach( initializeVersions );
	} );
}() );
