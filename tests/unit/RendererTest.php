<?php
/**
 * Renderer pure HTML/CSS builders.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase {

	public function test_wrapper_single_slot_when_no_mobile(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'ad-placr-footer-sticky',
			'ad-placr--footer-sticky',
			782,
			'<ins>desk</ins>',
			'',
			''
		);
		$this->assertStringContainsString( 'id="ad-placr-footer-sticky"', $html );
		$this->assertStringContainsString( 'ad-placr--footer-sticky', $html );
		$this->assertStringContainsString( 'ad-placr__slot--all', $html );
		$this->assertStringNotContainsString( 'ad-placr__slot--mobile', $html );
		$this->assertStringContainsString( '<ins>desk</ins>', $html );
	}

	public function test_wrapper_dual_slots_when_mobile_present(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'ad-placr-ic-1',
			'ad-placr--in-content',
			782,
			'U',
			'M',
			''
		);
		$this->assertStringContainsString( 'ad-placr__slot--universal', $html );
		$this->assertStringContainsString( 'ad-placr__slot--mobile', $html );
		$this->assertStringContainsString( '>U</div>', $html );
		$this->assertStringContainsString( '>M</div>', $html );
	}

	public function test_disclosure_rendered_when_non_empty(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'x',
			'ad-placr--footer-sticky',
			782,
			'A',
			'',
			'Advertisement'
		);
		$this->assertStringContainsString( 'ad-placr__disclosure', $html );
		$this->assertStringContainsString( 'Advertisement', $html );
	}

	public function test_mobile_css_contains_breakpoint_and_selector(): void {
		$css = Ad_Placr_Renderer::build_mobile_pair_css( '#ad-placr-footer-sticky', 782 );
		$this->assertStringContainsString( 'max-width: 782px', $css );
		$this->assertStringContainsString( 'min-width: 783px', $css );
		$this->assertStringContainsString( '#ad-placr-footer-sticky', $css );
		$this->assertStringContainsString( 'ad-placr__slot--universal', $css );
	}
}
