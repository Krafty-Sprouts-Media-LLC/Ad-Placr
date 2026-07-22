/**
 * In-content slot repeater (Settings screen).
 *
 * @package AdPlacr
 * @since 1.1.0
 */
( function ( $ ) {
	'use strict';

	function reindexSlots() {
		var $wrap = $( '#ad-placr-ic-slots' );
		$wrap.children( '.ad-placr-ic-slot' ).each( function ( i ) {
			var $slot = $( this );
			var nid = 'ad-placr-ic-title-' + i;
			$slot.find( 'input[id^="ad-placr-ic-title-"]' ).attr( 'id', nid );
			$slot.find( 'label[for^="ad-placr-ic-title-"]' ).attr( 'for', nid );
			$slot.find( 'input, textarea, select' ).each( function () {
				var $el = $( this );
				var n = $el.attr( 'name' );
				if ( n && n.indexOf( '[in_content_slots]' ) !== -1 ) {
					$el.attr(
						'name',
						n.replace(
							/\[in_content_slots\]\[\d+\]/,
							'[in_content_slots][' + i + ']'
						)
					);
				}
			} );
		} );
	}

	function newSlotId() {
		return (
			'ic_' +
			Math.random().toString( 36 ).substring( 2, 12 ) +
			Math.random().toString( 36 ).substring( 2, 6 )
		);
	}

	$( function () {
		var $slots = $( '#ad-placr-ic-slots' );
		var maxSlots =
			typeof adPlacrIcSlots !== 'undefined' && adPlacrIcSlots.maxSlots
				? parseInt( adPlacrIcSlots.maxSlots, 10 )
				: 30;

		$( '#ad-placr-add-ic-slot' ).on( 'click', function () {
			var count = $slots.children( '.ad-placr-ic-slot' ).length;
			if ( count >= maxSlots ) {
				return;
			}
			var $last = $slots.children( '.ad-placr-ic-slot' ).last();
			if ( ! $last.length ) {
				return;
			}
			var $clone = $last.clone( false );
			$clone.find( 'input[type="text"], textarea' ).val( '' );
			$clone.find( 'input[type="checkbox"]' ).prop( 'checked', false );
			$clone.find( 'input[type="radio"][value="after"]' ).prop( 'checked', true );
			$clone.find( 'input[type="number"]' ).val( '2' );
			$clone.find( 'input[name*="[id]"]' ).val( newSlotId() );
			$clone
				.find( 'input[name*="[post_types]"]' )
				.prop( 'checked', true );
			$slots.append( $clone );
			reindexSlots();
		} );

		$slots.on( 'click', '.ad-placr-ic-slot-remove', function () {
			var $rows = $slots.children( '.ad-placr-ic-slot' );
			if ( $rows.length <= 1 ) {
				return;
			}
			if (
				typeof adPlacrIcSlots !== 'undefined' &&
				adPlacrIcSlots.confirmRemove &&
				! window.confirm( adPlacrIcSlots.confirmRemove )
			) {
				return;
			}
			$( this ).closest( '.ad-placr-ic-slot' ).remove();
			reindexSlots();
		} );
	} );
} )( jQuery );
