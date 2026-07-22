<?php
/**
 * Shortcode attribute resolution (pure).
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class ShortcodeTest extends TestCase {

	public function test_resolve_placement_attr(): void {
		$req = Ad_Placr_Shortcode::resolve_request(
			array(
				'placement' => '42',
				'ad'        => '',
			)
		);

		$this->assertSame(
			array(
				'type' => 'placement',
				'id'   => 42,
			),
			$req
		);
	}

	public function test_resolve_ad_attr(): void {
		$req = Ad_Placr_Shortcode::resolve_request(
			array(
				'placement' => '',
				'ad'        => '7',
			)
		);

		$this->assertSame(
			array(
				'type' => 'ad',
				'id'   => 7,
			),
			$req
		);
	}

	public function test_placement_wins_when_both_set(): void {
		$req = Ad_Placr_Shortcode::resolve_request(
			array(
				'placement' => '10',
				'ad'        => '20',
			)
		);

		$this->assertSame( 'placement', $req['type'] );
		$this->assertSame( 10, $req['id'] );
	}

	public function test_empty_when_neither_valid(): void {
		$this->assertSame( array(), Ad_Placr_Shortcode::resolve_request( array() ) );
		$this->assertSame(
			array(),
			Ad_Placr_Shortcode::resolve_request(
				array(
					'placement' => '0',
					'ad'        => 'abc',
				)
			)
		);
	}

	public function test_modifier_classes(): void {
		$this->assertSame( 'ad-placr--manual-shortcode', Ad_Placr_Shortcode::modifier_for( 'placement' ) );
		$this->assertSame( 'ad-placr--manual-ad', Ad_Placr_Shortcode::modifier_for( 'ad' ) );
		$this->assertSame( '', Ad_Placr_Shortcode::modifier_for( 'other' ) );
	}

	public function test_widget_sticky_modifier(): void {
		$this->assertSame( 'ad-placr--widget-sticky', Ad_Placr_Widget::sticky_modifier( true ) );
		$this->assertSame( '', Ad_Placr_Widget::sticky_modifier( false ) );
	}
}
