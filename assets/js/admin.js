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
		const tabs = root.querySelector( '[data-ad-placr-version-tabs]' );
		const weightGroup = root.querySelector( '[data-ad-placr-weight-group]' );
		const slider = root.querySelector( '[data-ad-placr-weight-slider]' );
		const sliderVal = root.querySelector( '[data-ad-placr-weight-val]' );
		let nextIndex = root.querySelectorAll( '[data-ad-placr-version-row]' ).length;
		let activeIndex = 0;

		if ( ! list || ! template || ! addButton ) {
			return;
		}

		function rows() {
			return Array.from( root.querySelectorAll( '[data-ad-placr-version-list] > [data-ad-placr-version-row]' ) );
		}

		function rowWeight( row ) {
			const weight = row.querySelector( '[data-ad-placr-version-weight]' );
			return Math.max( 1, Number.parseInt( weight ? weight.value : '1', 10 ) || 1 );
		}

		function showPanel( index ) {
			const all = rows();
			activeIndex = Math.max( 0, Math.min( index, all.length - 1 ) );
			all.forEach( function( row, i ) {
				row.hidden = i !== activeIndex;
			} );
			if ( tabs ) {
				tabs.querySelectorAll( '[data-ad-placr-version-tab]' ).forEach( function( tab, i ) {
					tab.classList.toggle( 'is-active', i === activeIndex );
				} );
			}
			syncSlider();
			root.dispatchEvent( new CustomEvent( 'ad-placr-version-change', { bubbles: true } ) );
		}

		function renderTabs() {
			const all = rows();
			const multiple = all.length > 1;
			root.dataset.multiple = multiple ? '1' : '0';
			if ( tabs ) {
				tabs.hidden = ! multiple;
				tabs.innerHTML = '';
				all.forEach( function( row, i ) {
					const nameInput = row.querySelector( '[data-ad-placr-version-name]' );
					const name = nameInput && nameInput.value.trim() ? nameInput.value.trim() : ( ( window.adPlacrAdmin && window.adPlacrAdmin.versionLabel ? window.adPlacrAdmin.versionLabel : 'Version' ) + ' ' + alphabeticLabel( i ) );
					const tab = document.createElement( 'button' );
					tab.type = 'button';
					tab.className = 'ad-placr-version-tab' + ( i === activeIndex ? ' is-active' : '' );
					tab.setAttribute( 'data-ad-placr-version-tab', String( i ) );
					tab.innerHTML = name + ' <span class="ad-placr-version-tab-pct">' + rowWeight( row ) + '%</span>';
					tab.addEventListener( 'click', function() {
						showPanel( i );
					} );
					tabs.appendChild( tab );
				} );
			}
			if ( weightGroup ) {
				weightGroup.hidden = ! multiple;
			}
		}

		function syncSlider() {
			if ( ! slider || ! sliderVal ) {
				return;
			}
			const all = rows();
			const current = all[ activeIndex ];
			if ( ! current ) {
				return;
			}
			const value = Math.min( 99, Math.max( 1, rowWeight( current ) ) );
			slider.value = String( value );
			sliderVal.textContent = value + '%';
		}

		function rebalanceFromSlider( raw ) {
			const all = rows();
			const current = all[ activeIndex ];
			if ( ! current ) {
				return;
			}
			const value = Math.min( 99, Math.max( 1, Number.parseInt( raw, 10 ) || 1 ) );
			const currentInput = current.querySelector( '[data-ad-placr-version-weight]' );
			if ( currentInput ) {
				currentInput.value = String( value );
			}
			const others = all.length - 1;
			const rest = 100 - value;
			let restWeights = 0;
			all.forEach( function( row, i ) {
				if ( i !== activeIndex ) {
					restWeights += rowWeight( row );
				}
			} );
			all.forEach( function( row, i ) {
				if ( i === activeIndex ) {
					return;
				}
				const input = row.querySelector( '[data-ad-placr-version-weight]' );
				if ( ! input ) {
					return;
				}
				input.value = String( others ? Math.max( 1, Math.round( rest * rowWeight( row ) / restWeights ) ) : rest );
			} );
			if ( sliderVal ) {
				sliderVal.textContent = value + '%';
			}
			updateShares( root );
			renderTabs();
			showPanel( activeIndex );
		}

		fillEmptyNames( root );
		updateShares( root );
		renderTabs();
		showPanel( 0 );

		if ( slider ) {
			slider.addEventListener( 'input', function() {
				rebalanceFromSlider( slider.value );
			} );
		}

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
			fillEmptyNames( root );
			updateShares( root );
			renderTabs();
			showPanel( rows().length - 1 );

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

			const all = rows();
			if ( all.length <= 1 ) {
				return;
			}

			const row = removeButton.closest( '[data-ad-placr-version-row]' );
			if ( row ) {
				row.remove();
			}

			fillEmptyNames( root );
			updateShares( root );
			renderTabs();
			showPanel( Math.min( activeIndex, rows().length - 1 ) );
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
				renderTabs();
			}

			updateShares( root );
		} );

		root.addEventListener( 'change', function( event ) {
			if ( event.target.matches( '[data-ad-placr-mobile-toggle]' ) ) {
				const row = event.target.closest( '[data-ad-placr-version-row]' );
				const wrap = row ? row.querySelector( '[data-ad-placr-mobile-wrap]' ) : null;
				const mobile = row ? row.querySelector( '[data-ad-placr-version-mobile]' ) : null;
				if ( wrap ) {
					wrap.hidden = ! event.target.checked;
				}
				if ( ! event.target.checked && mobile ) {
					mobile.value = '';
				}
			}

			updateShares( root );
		} );
	}

	function initializePreview( root ) {
		const frame = root.querySelector( '[data-ad-placr-preview-frame]' );
		if ( ! frame ) {
			return;
		}

		let device = 'desktop';

		function activeCode() {
			const panel = document.querySelector( '[data-ad-placr-version-list] > [data-ad-placr-version-row]:not([hidden])' ) ||
				document.querySelector( '[data-ad-placr-version-row]' );
			if ( ! panel ) {
				return '';
			}
			const code = panel.querySelector( '[data-ad-placr-version-code]' );
			const mobile = panel.querySelector( '[data-ad-placr-version-mobile]' );
			if ( 'mobile' === device && mobile && '' !== mobile.value.trim() ) {
				return mobile.value;
			}
			return code ? code.value : '';
		}

		function composeSrcdoc() {
			const input = getPositionInput();
			const key = input ? input.value : '';
			const config = getPositionConfig( key );
			const alignInput = document.querySelector( '[data-ad-placr-alignment-input]' );
			const align = alignInput ? alignInput.value : 'none';
			const paraInput = document.getElementById( 'ad-placr-paragraph' );
			const para = Math.max( 1, Math.min( 100, parseInt( paraInput ? paraInput.value : '1', 10 ) || 1 ) );
			const code = activeCode();
			const justify = { left: 'flex-start', center: 'center', right: 'flex-end' }[ align ] || 'center';
			const slotStyle = 'none' === align ? 'width:100%' : 'justify-content:' + justify;
			const slot = '' === code.trim()
				? '<div class="empty">Paste ad code to preview it here.</div>'
				: '<div class="slot" style="' + slotStyle + '"><div class="unit">' + code + '</div></div>';

			let body = '<div class="bar"></div>';
			if ( config && config.para ) {
				let i;
				const before = 'before' === config.para;
				for ( i = 1; i <= 6; i++ ) {
					if ( before && i === para ) {
						body += slot;
					}
					body += '<div class="p"></div>';
					if ( ! before && i === para ) {
						body += slot;
					}
				}
			} else if ( config && 'sticky' === config.group ) {
				body += '<div class="p"></div><div class="p"></div><div class="p"></div>';
				body += slot;
			} else if ( key ) {
				body += slot;
				body += '<div class="p"></div><div class="p"></div><div class="p"></div><div class="p"></div>';
			} else {
				body += slot;
			}

			return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' +
				'html,body{margin:0;background:#fff;color:#1d2327;font:13px/1.5 sans-serif}' +
				'body.mobile{max-width:380px;margin:0 auto;border-left:1px solid #dcdcde;border-right:1px solid #dcdcde}' +
				'.bar{height:28px;background:#e2e4e7}' +
				'.p{height:10px;margin:10px 16px;background:#e3e5e9;border-radius:4px}' +
				'.slot{display:flex;width:calc(100% - 32px);margin:12px 16px;max-width:100%}' +
				'.unit{max-width:100%;overflow:auto}' +
				'.empty{margin:24px 16px;padding:32px;text-align:center;color:#646970;border:1px dashed #c3c4c7}' +
				'</style></head><body class="' + ( 'mobile' === device ? 'mobile' : '' ) + '">' +
				body + '</body></html>';
		}

		function rebuild() {
			frame.srcdoc = composeSrcdoc();
		}

		root.addEventListener( 'click', function( event ) {
			const btn = event.target.closest( '[data-ad-placr-preview-device]' );
			if ( ! btn ) {
				return;
			}
			device = btn.getAttribute( 'data-ad-placr-preview-device' ) || 'desktop';
			root.querySelectorAll( '[data-ad-placr-preview-device]' ).forEach( function( el ) {
				el.classList.toggle( 'is-active', el === btn );
			} );
			rebuild();
		} );

		document.addEventListener( 'input', function( event ) {
			if ( event.target.closest( '[data-ad-placr-versions], .ad-placr-placement' ) ) {
				rebuild();
			}
		} );
		document.addEventListener( 'change', function( event ) {
			if ( event.target.closest( '[data-ad-placr-versions], .ad-placr-placement' ) ) {
				rebuild();
			}
		} );
		document.addEventListener( 'click', function( event ) {
			if ( event.target.closest( '[data-ad-placr-spots], [data-ad-placr-areas], .ad-placr-wf-slot, .ad-placr-seg [data-al]' ) ) {
				window.setTimeout( rebuild, 0 );
			}
		} );
		document.addEventListener( 'ad-placr-version-change', rebuild );

		rebuild();
	}

	function initializeSubmitbox() {
		if ( ! document.body || ! document.body.classList.contains( 'post-type-ad_placr_ad' ) ) {
			return;
		}

		const publish = document.getElementById( 'publish' );
		const save = document.getElementById( 'save-post' );
		const i18n = window.adPlacrAdmin || {};

		if ( publish ) {
			publish.value = i18n.saveActivate || 'Save & Activate';
		}
		if ( save ) {
			save.value = i18n.savePause || 'Save & Pause';
		}

		const box = document.getElementById( 'submitdiv' );
		if ( ! box || box.querySelector( '[data-ad-placr-checklist]' ) ) {
			return;
		}

		const help = document.createElement( 'p' );
		help.className = 'ad-placr-submit-help';
		const tipBtn = document.createElement( 'button' );
		tipBtn.type = 'button';
		tipBtn.className = 'tip';
		tipBtn.setAttribute( 'aria-label', 'Help' );
		const tipBox = document.createElement( 'span' );
		tipBox.className = 'tip-box';
		tipBox.textContent = i18n.publishHelp || '';
		tipBtn.appendChild( tipBox );
		tipBtn.appendChild( document.createTextNode( '?' ) );
		help.appendChild( tipBtn );

		const list = document.createElement( 'ul' );
		list.className = 'ad-placr-checklist';
		list.setAttribute( 'data-ad-placr-checklist', '1' );
		list.innerHTML = '<li data-ad-placr-check="code" class="bad"></li><li data-ad-placr-check="pos" class="bad"></li>';
		const codeItem = list.querySelector( '[data-ad-placr-check="code"]' );
		const posItem = list.querySelector( '[data-ad-placr-check="pos"]' );
		if ( codeItem ) {
			codeItem.textContent = i18n.checklistCode || 'Ad code added';
		}
		if ( posItem ) {
			posItem.textContent = i18n.checklistPos || 'Location chosen';
		}

		const inside = box.querySelector( '.inside' );
		if ( inside ) {
			inside.appendChild( help );
			inside.appendChild( list );
		}

		function hasCode() {
			return Array.from( document.querySelectorAll( '[data-ad-placr-version-code]' ) ).some( function( area ) {
				return area.value.trim().length > 10;
			} );
		}

		function updateChecklist() {
			const input = getPositionInput();
			function setItem( el, ok ) {
				if ( ! el ) {
					return;
				}
				el.className = ok ? 'ok' : 'bad';
				el.textContent = ( ok ? '\u2713 ' : '\u2717 ' ) + el.textContent.replace( /^[\u2713\u2717]\s*/, '' );
			}
			setItem( list.querySelector( '[data-ad-placr-check="code"]' ), hasCode() );
			setItem( list.querySelector( '[data-ad-placr-check="pos"]' ), ! ! ( input && input.value ) );
		}

		document.addEventListener( 'input', updateChecklist );
		document.addEventListener( 'change', updateChecklist );
		document.addEventListener( 'click', function() {
			window.setTimeout( updateChecklist, 0 );
		} );
		updateChecklist();
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

		const preview = document.querySelector( '[data-ad-placr-preview]' );
		if ( preview ) {
			initializePreview( preview );
		}

		initializeSubmitbox();
	} );
}() );
