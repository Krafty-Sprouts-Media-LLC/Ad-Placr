<?php

use PHPUnit\Framework\TestCase;

final class MigrationBuilderTest extends TestCase {

	public function test_footer_sticky_becomes_ad_and_placement(): void {
		$settings = array(
			'footer_sticky'    => array(
				'enabled'     => true,
				'code'        => '<ins>desktop</ins>',
				'mobile_code' => '<ins>mobile</ins>',
			),
			'in_content_slots' => array(),
		);

		$defs = Ad_Placr_Migration::build_definitions( $settings );

		$this->assertCount( 1, $defs['ads'] );
		$this->assertCount( 1, $defs['placements'] );
		$this->assertSame( '<ins>desktop</ins>', $defs['ads'][0]['code'] );
		$this->assertSame( '<ins>mobile</ins>', $defs['ads'][0]['mobile_code'] );
		$this->assertSame( 'sticky_footer', $defs['placements'][0]['position'] );
		$this->assertSame( $defs['ads'][0]['key'], $defs['placements'][0]['ad_key'] );
	}

	public function test_disabled_empty_footer_is_skipped(): void {
		$settings = array(
			'footer_sticky'    => array( 'enabled' => false, 'code' => '', 'mobile_code' => '' ),
			'in_content_slots' => array(),
		);

		$defs = Ad_Placr_Migration::build_definitions( $settings );

		$this->assertCount( 0, $defs['ads'] );
		$this->assertCount( 0, $defs['placements'] );
	}

	public function test_in_content_slot_maps_position_and_paragraph(): void {
		$settings = array(
			'footer_sticky'    => array( 'enabled' => false, 'code' => '', 'mobile_code' => '' ),
			'in_content_slots' => array(
				array(
					'id'              => 'ic_abc',
					'enabled'         => true,
					'title'           => 'Mid-article',
					'paragraph_index' => 3,
					'position'        => 'before',
					'post_types'      => array( 'post' ),
					'code'            => '<ins>a</ins>',
					'mobile_code'     => '',
				),
			),
		);

		$defs = Ad_Placr_Migration::build_definitions( $settings );

		$this->assertCount( 1, $defs['ads'] );
		$this->assertCount( 1, $defs['placements'] );
		$this->assertSame( 'in_content_before_paragraph', $defs['placements'][0]['position'] );
		$this->assertSame( 3, $defs['placements'][0]['paragraph'] );
		$this->assertSame( array( 'post' ), $defs['placements'][0]['targeting']['post_types'] );
		$this->assertSame( 'ic_abc', $defs['placements'][0]['targeting']['slot_id'] );
	}
}
