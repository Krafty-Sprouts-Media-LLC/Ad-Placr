<?php

use PHPUnit\Framework\TestCase;

final class SanityTest extends TestCase {

	public function test_constants_are_defined(): void {
		$this->assertTrue( defined( 'AD_PLACR_VERSION' ) );
		$this->assertSame( 'test', AD_PLACR_VERSION );
	}
}
