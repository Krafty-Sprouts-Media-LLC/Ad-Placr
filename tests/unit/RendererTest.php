<?php
/**
 * Renderer pure HTML/CSS builders.
 *
 * @package AdPlacr
 */

use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase {

	public function test_wrapper_identifies_ad_and_stable_version(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'ad-placr-test',
			'ad-placr--pos-sticky_footer',
			'<span>code</span>',
			42,
			'11111111-1111-4111-8111-111111111111'
		);

		$this->assertStringContainsString( 'data-ad-id="42"', $html );
		$this->assertStringContainsString( 'data-version-id="11111111-1111-4111-8111-111111111111"', $html );
		$this->assertStringNotContainsString( 'data-placement-id', $html );
	}

	public function test_responsive_css_swaps_mobile_code_and_hides_unselected_tablet(): void {
		$css = Ad_Placr_Renderer::build_responsive_css(
			'#ad-placr-test',
			782,
			1024,
			array( 'desktop', 'mobile' ),
			true
		);

		$this->assertStringContainsString( '@media (max-width:782px)', $css );
		$this->assertStringContainsString( '.ad-placr__universal{display:none!important}', $css );
		$this->assertStringContainsString( '@media (min-width:783px) and (max-width:1024px)', $css );
		$this->assertStringContainsString( '#ad-placr-test{display:none!important}', $css );
	}

	public function test_wrapper_escapes_its_identifier_and_modifier(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'ad-placr-"test',
			'ad-placr--pos-test" onclick="alert(1)',
			'<span>code</span>',
			42,
			'11111111-1111-4111-8111-111111111111'
		);

		$this->assertStringContainsString( 'id="ad-placr-&quot;test"', $html );
		$this->assertStringContainsString( 'ad-placr--pos-test&quot; onclick=&quot;alert(1)', $html );
		$this->assertStringNotContainsString( 'onclick="alert(1)"', $html );
	}

	public function test_wrapper_keeps_ad_code_raw_by_design(): void {
		$html = Ad_Placr_Renderer::build_wrapper_html(
			'ad-placr-test',
			'ad-placr--pos-sticky_footer',
			'<script>window.adNetworkCode()</script>',
			42,
			'11111111-1111-4111-8111-111111111111'
		);

		$this->assertStringContainsString( '<script>window.adNetworkCode()</script>', $html );
	}
}
