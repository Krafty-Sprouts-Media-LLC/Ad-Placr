# Ad Placr Foundations & Data Model Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the tested data foundation of the Ad Placr ad-manager rebuild — dev tooling, a filterable position registry, the `ad_placr_ad` (creative) and `ad_placr_placement` (position + rules + weighted ads) post types, and an idempotent migration that converts the existing `ad_placr_settings` footer/in-content config into the new model.

**Architecture:** Two separated CPTs — an **Ad** is a reusable creative (code), a **Placement** references a **weighted list of Ads** at one canonical position. All meta keys are class constants used for both read and write. Pure logic (registry defaults, weighted selection, migration mapping) is split from thin WordPress wrappers so it can be unit-tested without a database.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, dev-local Composer tooling (PHPUnit 9 + Brain Monkey for unit tests, PHPCS + WordPress Coding Standards, PHPStan + phpstan-wordpress). No runtime Composer dependency; no JS build.

**Scope note:** This is plan 1 of several. It does **not** render ads on the front end (that is the next plan, "Renderer & Frontend"), add admin meta-box UI, targeting evaluation, analytics, shortcodes, or new position hooks. It delivers registered post types + migration + the pure logic they depend on, all unit-tested.

## Global Constraints

- PHP floor **8.0**; WordPress floor **6.0** (from `ad-placr.php` header — do not lower).
- Coding standards: **WordPress PHP Coding Standards** — tabs (not spaces), `array()` long syntax, Yoda conditions, spaced parentheses `foo( $bar )`, one class per file.
- File naming `class-ad-placr-*.php`; class prefix `Ad_Placr_`; function/hook/meta/option prefix `ad_placr_`.
- Every function has a docblock with `@since`, `@param`, `@return`. Never edit an existing `@since`.
- Composer and `vendor/` are **dev-local only**. Runtime code must never require `vendor/autoload.php`.
- Meta keys are class constants; each key has exactly one read site and one write site referencing the same constant.
- Ad code is the single documented raw-echo exception (not exercised in this plan — no rendering here).
- Canonical position keys are the source of truth (see spec `docs/superpowers/specs/2026-07-22-ad-placr-ad-manager-design.md` §"Position taxonomy").

---

## File Structure

**Created:**
- `composer.json` — dev-only tooling manifest (require-dev, scripts).
- `phpcs.xml.dist` — WordPress standard ruleset scoped to plugin PHP.
- `phpstan.neon.dist` — PHPStan config with phpstan-wordpress.
- `tests/bootstrap.php` — defines plugin constants + `ABSPATH`, loads autoloader and classes under test.
- `tests/unit/PositionsTest.php`, `tests/unit/PlacementLogicTest.php`, `tests/unit/MigrationBuilderTest.php` — unit tests.
- `includes/class-ad-placr-positions.php` — `Ad_Placr_Positions` (filterable registry).
- `includes/class-ad-placr-ad.php` — `Ad_Placr_Ad` (creative CPT + meta constants + `is_active`).
- `includes/class-ad-placr-placement.php` — `Ad_Placr_Placement` (placement CPT + meta + weighted selection).
- `includes/class-ad-placr-migration.php` — `Ad_Placr_Migration` (option → CPT, idempotent).

**Modified:**
- `ad-placr.php` — `require_once` the four new class files.
- `includes/class-ad-placr-plugin.php` — register the new subsystems in `boot()` and run migration.
- `.gitignore` — ignore `/vendor/`.

---

### Task 1: Dev tooling & unit-test harness

**Files:**
- Create: `composer.json`
- Create: `phpcs.xml.dist`
- Create: `phpstan.neon.dist`
- Create: `tests/bootstrap.php`
- Create: `tests/unit/SanityTest.php`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: nothing.
- Produces: `composer test` (PHPUnit), `composer lint` (PHPCS), `composer analyze` (PHPStan). A `tests/bootstrap.php` that defines `ABSPATH`, `AD_PLACR_VERSION`, `AD_PLACR_PLUGIN_FILE`, `AD_PLACR_PLUGIN_DIR`, `AD_PLACR_PLUGIN_URL` and loads Composer's autoloader. Later tasks add their class `require`s to this bootstrap.

- [ ] **Step 1: Create `composer.json` (dev-only tooling)**

```json
{
	"name": "krafty-sprouts/ad-placr-dev",
	"description": "Dev-only tooling for Ad Placr. Not a runtime dependency.",
	"license": "GPL-2.0-or-later",
	"type": "project",
	"require": {
		"php": ">=8.0"
	},
	"require-dev": {
		"phpunit/phpunit": "^9.6",
		"brain/monkey": "^2.6",
		"squizlabs/php_codesniffer": "^3.9",
		"wp-coding-standards/wpcs": "^3.1",
		"dealerdirect/phpcodesniffer-composer-installer": "^1.0",
		"phpstan/phpstan": "^1.11",
		"szepeviktor/phpstan-wordpress": "^1.3"
	},
	"config": {
		"allow-plugins": {
			"dealerdirect/phpcodesniffer-composer-installer": true
		}
	},
	"scripts": {
		"test": "phpunit",
		"lint": "phpcs",
		"analyze": "phpstan analyse"
	}
}
```

- [ ] **Step 2: Install tooling**

Run: `composer install`
Expected: `vendor/` created; PHPUnit, Brain Monkey, PHPCS, WPCS, PHPStan installed; no errors.

- [ ] **Step 3: Ignore `vendor/`**

Add a line to `.gitignore` (keep existing lines):

```gitignore
/vendor/
```

- [ ] **Step 4: Create `phpcs.xml.dist`**

```xml
<?xml version="1.0"?>
<ruleset name="Ad Placr">
	<description>WordPress coding standards for Ad Placr runtime code.</description>

	<file>ad-placr.php</file>
	<file>includes</file>
	<file>uninstall.php</file>

	<exclude-pattern>*/vendor/*</exclude-pattern>
	<exclude-pattern>*/lib/*</exclude-pattern>
	<exclude-pattern>*/adsly/*</exclude-pattern>
	<exclude-pattern>*/ad-inserter/*</exclude-pattern>
	<exclude-pattern>*/tests/*</exclude-pattern>

	<arg name="extensions" value="php"/>
	<arg value="ps"/>

	<config name="minimum_supported_wp_version" value="6.0"/>
	<config name="testVersion" value="8.0-"/>

	<rule ref="WordPress">
		<exclude name="WordPress.Files.FileName.InvalidClassFileName"/>
	</rule>

	<rule ref="WordPress.WP.I18n">
		<properties>
			<property name="text_domain" type="array" value="ad-placr"/>
		</properties>
	</rule>
</ruleset>
```

- [ ] **Step 5: Create `phpstan.neon.dist`**

```neon
includes:
	- vendor/szepeviktor/phpstan-wordpress/extension.neon

parameters:
	level: 5
	paths:
		- ad-placr.php
		- includes
	excludePaths:
		- lib/*
		- adsly/*
		- ad-inserter/*
	bootstrapFiles:
		- ad-placr.php
```

- [ ] **Step 6: Create `tests/bootstrap.php`**

```php
<?php
/**
 * PHPUnit bootstrap for Ad Placr unit tests (no WordPress install required).
 *
 * @package AdPlacr
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'AD_PLACR_VERSION', 'test' );
define( 'AD_PLACR_PLUGIN_FILE', dirname( __DIR__ ) . '/ad-placr.php' );
define( 'AD_PLACR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'AD_PLACR_PLUGIN_URL', 'http://example.test/wp-content/plugins/ad-placr/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';

// Class files under test are required here as tasks add them.
```

- [ ] **Step 7: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
	bootstrap="tests/bootstrap.php"
	colors="true"
	failOnWarning="true"
	failOnRisky="true">
	<testsuites>
		<testsuite name="unit">
			<directory>tests/unit</directory>
		</testsuite>
	</testsuites>
</phpunit>
```

- [ ] **Step 8: Write a sanity test**

Create `tests/unit/SanityTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class SanityTest extends TestCase {

	public function test_constants_are_defined(): void {
		$this->assertTrue( defined( 'AD_PLACR_VERSION' ) );
		$this->assertSame( 'test', AD_PLACR_VERSION );
	}
}
```

- [ ] **Step 9: Run the test suite**

Run: `composer test`
Expected: PASS — 1 test, 2 assertions, OK.

- [ ] **Step 10: Commit**

```bash
git add composer.json phpcs.xml.dist phpstan.neon.dist phpunit.xml.dist tests/ .gitignore
git commit -m "build: add dev-local test/lint/analyze tooling"
```

---

### Task 2: Position registry (`Ad_Placr_Positions`)

**Files:**
- Create: `includes/class-ad-placr-positions.php`
- Test: `tests/unit/PositionsTest.php`
- Modify: `tests/bootstrap.php` (require the class)

**Interfaces:**
- Consumes: nothing.
- Produces:
  - Position key constants (e.g. `Ad_Placr_Positions::STICKY_FOOTER = 'sticky_footer'`, full list below).
  - `Ad_Placr_Positions::defaults(): array<string,array{label:string,group:string,context:string}>` — pure, unfiltered registry.
  - `Ad_Placr_Positions::all(): array<string,array>` — `defaults()` through the `ad_placr_positions` filter.
  - `Ad_Placr_Positions::keys(): string[]`.
  - `Ad_Placr_Positions::exists( string $key ): bool`.
  - `Ad_Placr_Positions::label( string $key ): string`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/PositionsTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class PositionsTest extends TestCase {

	public function test_defaults_include_core_positions(): void {
		$defaults = Ad_Placr_Positions::defaults();

		$this->assertArrayHasKey( 'sticky_footer', $defaults );
		$this->assertArrayHasKey( 'in_content_after_paragraph', $defaults );
		$this->assertArrayHasKey( 'before_header', $defaults );
	}

	public function test_every_position_has_complete_descriptor(): void {
		foreach ( Ad_Placr_Positions::defaults() as $key => $descriptor ) {
			$this->assertIsString( $key );
			$this->assertArrayHasKey( 'label', $descriptor, "$key missing label" );
			$this->assertArrayHasKey( 'group', $descriptor, "$key missing group" );
			$this->assertArrayHasKey( 'context', $descriptor, "$key missing context" );
			$this->assertNotSame( '', $descriptor['label'], "$key has empty label" );
		}
	}

	public function test_constants_match_registry_keys(): void {
		$defaults = Ad_Placr_Positions::defaults();
		$this->assertArrayHasKey( Ad_Placr_Positions::STICKY_FOOTER, $defaults );
		$this->assertArrayHasKey( Ad_Placr_Positions::BEFORE_HEADER, $defaults );
	}

	public function test_exists_and_label(): void {
		$this->assertTrue( Ad_Placr_Positions::exists( 'sticky_footer' ) );
		$this->assertFalse( Ad_Placr_Positions::exists( 'not_a_position' ) );
		$this->assertNotSame( '', Ad_Placr_Positions::label( 'sticky_footer' ) );
		$this->assertSame( '', Ad_Placr_Positions::label( 'not_a_position' ) );
	}
}
```

- [ ] **Step 2: Wire the class into the test bootstrap**

Append to `tests/bootstrap.php`:

```php
require dirname( __DIR__ ) . '/includes/class-ad-placr-positions.php';
```

- [ ] **Step 3: Run test to verify it fails**

Run: `composer test -- --filter PositionsTest`
Expected: FAIL — `Class "Ad_Placr_Positions" not found`.

- [ ] **Step 4: Write minimal implementation**

Create `includes/class-ad-placr-positions.php`:

```php
<?php
/**
 * Canonical ad-position registry.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for placement positions.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Positions {

	public const IN_CONTENT_BEFORE_PARAGRAPH = 'in_content_before_paragraph';
	public const IN_CONTENT_AFTER_PARAGRAPH  = 'in_content_after_paragraph';
	public const BEFORE_POST_CONTENT         = 'before_post_content';
	public const AFTER_POST_CONTENT          = 'after_post_content';
	public const BEFORE_HEADER               = 'before_header';
	public const AFTER_HEADER                = 'after_header';
	public const BEFORE_FOOTER               = 'before_footer';
	public const AFTER_FOOTER                = 'after_footer';
	public const STICKY_FOOTER               = 'sticky_footer';
	public const STICKY_LEFT_RAIL            = 'sticky_left_rail';
	public const STICKY_RIGHT_RAIL           = 'sticky_right_rail';
	public const FRONT_PAGE_TOP              = 'front_page_top';
	public const FRONT_PAGE_BOTTOM           = 'front_page_bottom';
	public const BLOG_INDEX_TOP              = 'blog_index_top';
	public const BLOG_INDEX_BOTTOM           = 'blog_index_bottom';
	public const ARCHIVE_TOP                 = 'archive_top';
	public const ARCHIVE_BOTTOM              = 'archive_bottom';
	public const SIDEBAR_WIDGET              = 'sidebar_widget';
	public const MANUAL_SHORTCODE            = 'manual_shortcode';
	public const MANUAL_BLOCK                = 'manual_block';

	/**
	 * Unfiltered registry. Pure (no WordPress calls) so it is unit-testable.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{label:string, group:string, context:string}>
	 */
	public static function defaults(): array {
		return array(
			self::IN_CONTENT_BEFORE_PARAGRAPH => array( 'label' => 'Before paragraph N', 'group' => 'in_content', 'context' => 'singular' ),
			self::IN_CONTENT_AFTER_PARAGRAPH  => array( 'label' => 'After paragraph N', 'group' => 'in_content', 'context' => 'singular' ),
			self::BEFORE_POST_CONTENT         => array( 'label' => 'Before post content', 'group' => 'content', 'context' => 'singular' ),
			self::AFTER_POST_CONTENT          => array( 'label' => 'After post content', 'group' => 'content', 'context' => 'singular' ),
			self::BEFORE_HEADER               => array( 'label' => 'Before header', 'group' => 'structure', 'context' => 'global' ),
			self::AFTER_HEADER                => array( 'label' => 'After header', 'group' => 'structure', 'context' => 'global' ),
			self::BEFORE_FOOTER               => array( 'label' => 'Before footer', 'group' => 'structure', 'context' => 'global' ),
			self::AFTER_FOOTER                => array( 'label' => 'After footer', 'group' => 'structure', 'context' => 'global' ),
			self::STICKY_FOOTER               => array( 'label' => 'Sticky footer', 'group' => 'sticky', 'context' => 'global' ),
			self::STICKY_LEFT_RAIL            => array( 'label' => 'Sticky left rail', 'group' => 'sticky', 'context' => 'global' ),
			self::STICKY_RIGHT_RAIL           => array( 'label' => 'Sticky right rail', 'group' => 'sticky', 'context' => 'global' ),
			self::FRONT_PAGE_TOP              => array( 'label' => 'Front page top', 'group' => 'listing', 'context' => 'front_page' ),
			self::FRONT_PAGE_BOTTOM           => array( 'label' => 'Front page bottom', 'group' => 'listing', 'context' => 'front_page' ),
			self::BLOG_INDEX_TOP              => array( 'label' => 'Blog index top', 'group' => 'listing', 'context' => 'blog_index' ),
			self::BLOG_INDEX_BOTTOM           => array( 'label' => 'Blog index bottom', 'group' => 'listing', 'context' => 'blog_index' ),
			self::ARCHIVE_TOP                 => array( 'label' => 'Archive top', 'group' => 'listing', 'context' => 'archive' ),
			self::ARCHIVE_BOTTOM              => array( 'label' => 'Archive bottom', 'group' => 'listing', 'context' => 'archive' ),
			self::SIDEBAR_WIDGET              => array( 'label' => 'Sidebar widget', 'group' => 'manual', 'context' => 'widget' ),
			self::MANUAL_SHORTCODE            => array( 'label' => 'Shortcode', 'group' => 'manual', 'context' => 'manual' ),
			self::MANUAL_BLOCK                => array( 'label' => 'Block', 'group' => 'manual', 'context' => 'manual' ),
		);
	}

	/**
	 * Filtered registry. Extensions add positions here without editing core.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{label:string, group:string, context:string}>
	 */
	public static function all(): array {
		/**
		 * Filter the registered ad positions.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, array> $positions Position key => descriptor.
		 */
		$positions = apply_filters( 'ad_placr_positions', self::defaults() );

		return is_array( $positions ) ? $positions : self::defaults();
	}

	/**
	 * All registered position keys.
	 *
	 * @since 2.0.0
	 *
	 * @return string[]
	 */
	public static function keys(): array {
		return array_keys( self::all() );
	}

	/**
	 * Whether a position key is registered.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Position key.
	 * @return bool
	 */
	public static function exists( string $key ): bool {
		return array_key_exists( $key, self::all() );
	}

	/**
	 * Human label for a position, or empty string if unknown.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Position key.
	 * @return string
	 */
	public static function label( string $key ): string {
		$all = self::all();

		return isset( $all[ $key ]['label'] ) ? (string) $all[ $key ]['label'] : '';
	}
}
```

Note: `exists()` and `label()` call `all()`, which calls `apply_filters()`. In the unit test the WordPress function is unavailable, so define a Brain Monkey-free shim by using Brain Monkey in this test file's `setUp`. To keep Task 2 pure, `defaults()`-based assertions run without WordPress; `exists()`/`label()` assertions need `apply_filters`. Add the shim in the next step.

- [ ] **Step 5: Add a Brain Monkey shim for `apply_filters` in the test**

Update `tests/unit/PositionsTest.php` to set up Brain Monkey so `apply_filters` returns its second argument unchanged:

```php
<?php

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class PositionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_defaults_include_core_positions(): void {
		$defaults = Ad_Placr_Positions::defaults();
		$this->assertArrayHasKey( 'sticky_footer', $defaults );
		$this->assertArrayHasKey( 'in_content_after_paragraph', $defaults );
		$this->assertArrayHasKey( 'before_header', $defaults );
	}

	public function test_every_position_has_complete_descriptor(): void {
		foreach ( Ad_Placr_Positions::defaults() as $key => $descriptor ) {
			$this->assertIsString( $key );
			$this->assertArrayHasKey( 'label', $descriptor, "$key missing label" );
			$this->assertArrayHasKey( 'group', $descriptor, "$key missing group" );
			$this->assertArrayHasKey( 'context', $descriptor, "$key missing context" );
			$this->assertNotSame( '', $descriptor['label'], "$key has empty label" );
		}
	}

	public function test_constants_match_registry_keys(): void {
		$defaults = Ad_Placr_Positions::defaults();
		$this->assertArrayHasKey( Ad_Placr_Positions::STICKY_FOOTER, $defaults );
		$this->assertArrayHasKey( Ad_Placr_Positions::BEFORE_HEADER, $defaults );
	}

	public function test_exists_and_label(): void {
		$this->assertTrue( Ad_Placr_Positions::exists( 'sticky_footer' ) );
		$this->assertFalse( Ad_Placr_Positions::exists( 'not_a_position' ) );
		$this->assertNotSame( '', Ad_Placr_Positions::label( 'sticky_footer' ) );
		$this->assertSame( '', Ad_Placr_Positions::label( 'not_a_position' ) );
	}
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `composer test -- --filter PositionsTest`
Expected: PASS — 4 tests.

- [ ] **Step 7: Lint the new file**

Run: `composer lint -- includes/class-ad-placr-positions.php`
Expected: no errors.

- [ ] **Step 8: Commit**

```bash
git add includes/class-ad-placr-positions.php tests/unit/PositionsTest.php tests/bootstrap.php
git commit -m "feat: add filterable canonical position registry"
```

---

### Task 3: Ad creative CPT (`Ad_Placr_Ad`)

**Files:**
- Create: `includes/class-ad-placr-ad.php`
- Test: `tests/unit/PlacementLogicTest.php` (shared logic test file; Ad status normalization added here)
- Modify: `tests/bootstrap.php` (require the class)

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Ad_Placr_Ad::POST_TYPE = 'ad_placr_ad'`.
  - Meta constants: `META_CODE = '_ad_placr_code'`, `META_MOBILE_CODE = '_ad_placr_mobile_code'`, `META_DEVICES = '_ad_placr_devices'`, `META_STATUS = '_ad_placr_status'`, `META_NOTES = '_ad_placr_notes'`.
  - `Ad_Placr_Ad::normalize_status( mixed $raw ): string` — pure; returns `'active'` or `'inactive'`.
  - `Ad_Placr_Ad::register(): void` — registers the CPT + meta on `init` (WordPress runtime).
  - `Ad_Placr_Ad::is_active( int $ad_id ): bool` — WordPress runtime (published + status active).

- [ ] **Step 1: Write the failing test**

Create `tests/unit/PlacementLogicTest.php`:

```php
<?php

use PHPUnit\Framework\TestCase;

final class PlacementLogicTest extends TestCase {

	public function test_ad_normalize_status_defaults_to_inactive(): void {
		$this->assertSame( 'inactive', Ad_Placr_Ad::normalize_status( '' ) );
		$this->assertSame( 'inactive', Ad_Placr_Ad::normalize_status( null ) );
		$this->assertSame( 'inactive', Ad_Placr_Ad::normalize_status( 'garbage' ) );
	}

	public function test_ad_normalize_status_accepts_active(): void {
		$this->assertSame( 'active', Ad_Placr_Ad::normalize_status( 'active' ) );
		$this->assertSame( 'active', Ad_Placr_Ad::normalize_status( 'ACTIVE' ) );
	}
}
```

- [ ] **Step 2: Wire the class into the test bootstrap**

Append to `tests/bootstrap.php`:

```php
require dirname( __DIR__ ) . '/includes/class-ad-placr-ad.php';
```

- [ ] **Step 3: Run test to verify it fails**

Run: `composer test -- --filter PlacementLogicTest`
Expected: FAIL — `Class "Ad_Placr_Ad" not found`.

- [ ] **Step 4: Write minimal implementation**

Create `includes/class-ad-placr-ad.php`:

```php
<?php
/**
 * Ad creative post type: reusable ad code.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `ad_placr_ad` post type and its meta.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Ad {

	public const POST_TYPE = 'ad_placr_ad';

	public const META_CODE        = '_ad_placr_code';
	public const META_MOBILE_CODE = '_ad_placr_mobile_code';
	public const META_DEVICES     = '_ad_placr_devices';
	public const META_STATUS      = '_ad_placr_status';
	public const META_NOTES       = '_ad_placr_notes';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the CPT and its meta.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Ads', 'ad-placr' ),
					'singular_name' => __( 'Ad', 'ad-placr' ),
					'add_new_item'  => __( 'Add New Ad', 'ad-placr' ),
					'edit_item'     => __( 'Edit Ad', 'ad-placr' ),
					'menu_name'     => __( 'Ad Placr', 'ad-placr' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-megaphone',
				'menu_position'   => 26,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);

		$string_meta = array( self::META_CODE, self::META_MOBILE_CODE, self::META_STATUS, self::META_NOTES );

		foreach ( $string_meta as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}

		register_post_meta(
			self::POST_TYPE,
			self::META_DEVICES,
			array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Normalize a raw status value to a known value. Pure.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $raw Raw status.
	 * @return string 'active' or 'inactive'.
	 */
	public static function normalize_status( $raw ): string {
		return 'active' === strtolower( (string) $raw ) ? 'active' : 'inactive';
	}

	/**
	 * Whether an ad is published and marked active.
	 *
	 * @since 2.0.0
	 *
	 * @param int $ad_id Ad post ID.
	 * @return bool
	 */
	public static function is_active( int $ad_id ): bool {
		if ( self::POST_TYPE !== get_post_type( $ad_id ) ) {
			return false;
		}

		if ( 'publish' !== get_post_status( $ad_id ) ) {
			return false;
		}

		return 'active' === self::normalize_status( get_post_meta( $ad_id, self::META_STATUS, true ) );
	}
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter PlacementLogicTest`
Expected: PASS — 2 tests.

- [ ] **Step 6: Lint**

Run: `composer lint -- includes/class-ad-placr-ad.php`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add includes/class-ad-placr-ad.php tests/unit/PlacementLogicTest.php tests/bootstrap.php
git commit -m "feat: add ad_placr_ad creative post type"
```

---

### Task 4: Placement CPT + weighted selection (`Ad_Placr_Placement`)

**Files:**
- Create: `includes/class-ad-placr-placement.php`
- Modify: `tests/unit/PlacementLogicTest.php` (add weighted-selection + normalization tests)
- Modify: `tests/bootstrap.php` (require the class)

**Interfaces:**
- Consumes: `Ad_Placr_Positions` (validates position keys), `Ad_Placr_Ad::is_active()` (runtime pick).
- Produces:
  - `Ad_Placr_Placement::POST_TYPE = 'ad_placr_placement'`.
  - Meta constants: `META_POSITION = '_ad_placr_position'`, `META_STATUS = '_ad_placr_status'`, `META_TARGETING = '_ad_placr_targeting'`, `META_ADS = '_ad_placr_ads'`.
  - `Ad_Placr_Placement::normalize_ads( mixed $raw ): array` — pure; returns list of `array{ad_id:int, weight:int}` (weight ≥ 1, ad_id > 0).
  - `Ad_Placr_Placement::choose_weighted( array $weighted, int $roll ): ?int` — pure; deterministic weighted pick given an integer roll.
  - `Ad_Placr_Placement::register(): void` — runtime CPT + meta.
  - `Ad_Placr_Placement::get_ads( int $placement_id ): array` — runtime read → `normalize_ads`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/unit/PlacementLogicTest.php` (inside the class):

```php
	public function test_normalize_ads_filters_invalid_rows(): void {
		$raw = array(
			array( 'ad_id' => '12', 'weight' => '3' ),
			array( 'ad_id' => 0 ),          // dropped: no id
			array( 'weight' => 5 ),          // dropped: no id
			'nonsense',                       // dropped: not array
			array( 'ad_id' => 7 ),           // weight defaults to 1
		);

		$out = Ad_Placr_Placement::normalize_ads( $raw );

		$this->assertSame(
			array(
				array( 'ad_id' => 12, 'weight' => 3 ),
				array( 'ad_id' => 7, 'weight' => 1 ),
			),
			$out
		);
	}

	public function test_choose_weighted_single_item(): void {
		$weighted = array( array( 'ad_id' => 5, 'weight' => 1 ) );
		$this->assertSame( 5, Ad_Placr_Placement::choose_weighted( $weighted, 0 ) );
	}

	public function test_choose_weighted_respects_bands(): void {
		$weighted = array(
			array( 'ad_id' => 1, 'weight' => 70 ),
			array( 'ad_id' => 2, 'weight' => 30 ),
		);

		$this->assertSame( 1, Ad_Placr_Placement::choose_weighted( $weighted, 0 ) );
		$this->assertSame( 1, Ad_Placr_Placement::choose_weighted( $weighted, 69 ) );
		$this->assertSame( 2, Ad_Placr_Placement::choose_weighted( $weighted, 70 ) );
		$this->assertSame( 2, Ad_Placr_Placement::choose_weighted( $weighted, 99 ) );
	}

	public function test_choose_weighted_normalizes_out_of_range_roll(): void {
		$weighted = array(
			array( 'ad_id' => 1, 'weight' => 70 ),
			array( 'ad_id' => 2, 'weight' => 30 ),
		);
		// roll 170 % 100 = 70 -> band of ad 2.
		$this->assertSame( 2, Ad_Placr_Placement::choose_weighted( $weighted, 170 ) );
	}

	public function test_choose_weighted_empty_returns_null(): void {
		$this->assertNull( Ad_Placr_Placement::choose_weighted( array(), 0 ) );
		$this->assertNull( Ad_Placr_Placement::choose_weighted( array( array( 'ad_id' => 1, 'weight' => 0 ) ), 0 ) );
	}
```

- [ ] **Step 2: Wire the class into the test bootstrap**

Append to `tests/bootstrap.php`:

```php
require dirname( __DIR__ ) . '/includes/class-ad-placr-placement.php';
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `composer test -- --filter PlacementLogicTest`
Expected: FAIL — `Class "Ad_Placr_Placement" not found`.

- [ ] **Step 4: Write minimal implementation**

Create `includes/class-ad-placr-placement.php`:

```php
<?php
/**
 * Placement post type: a position + targeting + weighted ad list.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `ad_placr_placement` post type and holds placement logic.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Placement {

	public const POST_TYPE = 'ad_placr_placement';

	public const META_POSITION  = '_ad_placr_position';
	public const META_STATUS    = '_ad_placr_status';
	public const META_TARGETING = '_ad_placr_targeting';
	public const META_ADS       = '_ad_placr_ads';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * Register the CPT and its meta.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Placements', 'ad-placr' ),
					'singular_name' => __( 'Placement', 'ad-placr' ),
					'add_new_item'  => __( 'Add New Placement', 'ad-placr' ),
					'edit_item'     => __( 'Edit Placement', 'ad-placr' ),
					'menu_name'     => __( 'Placements', 'ad-placr' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . Ad_Placr_Ad::POST_TYPE,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_POSITION,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_STATUS,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		foreach ( array( self::META_TARGETING, self::META_ADS ) as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'array',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}
	}

	/**
	 * Normalize a raw ad list into `{ad_id:int, weight:int}` rows. Pure.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $raw Raw meta value.
	 * @return array<int, array{ad_id:int, weight:int}>
	 */
	public static function normalize_ads( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['ad_id'] ) ) {
				continue;
			}

			$ad_id = (int) $row['ad_id'];
			if ( $ad_id <= 0 ) {
				continue;
			}

			$weight = isset( $row['weight'] ) ? (int) $row['weight'] : 1;
			$weight = max( 1, $weight );

			$out[] = array(
				'ad_id'  => $ad_id,
				'weight' => $weight,
			);
		}

		return $out;
	}

	/**
	 * Deterministic weighted selection. Pure.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, array{ad_id:int, weight:int}> $weighted Weighted rows.
	 * @param int                                       $roll     Non-negative roll value.
	 * @return int|null Selected ad ID, or null when no positive weight exists.
	 */
	public static function choose_weighted( array $weighted, int $roll ): ?int {
		$total = 0;
		foreach ( $weighted as $row ) {
			$total += max( 0, (int) ( $row['weight'] ?? 0 ) );
		}

		if ( $total <= 0 ) {
			return null;
		}

		$roll = ( ( $roll % $total ) + $total ) % $total;

		$cursor = 0;
		foreach ( $weighted as $row ) {
			$weight = max( 0, (int) ( $row['weight'] ?? 0 ) );
			if ( $weight <= 0 ) {
				continue;
			}

			$cursor += $weight;
			if ( $roll < $cursor ) {
				return (int) $row['ad_id'];
			}
		}

		return null;
	}

	/**
	 * Read and normalize a placement's ad list.
	 *
	 * @since 2.0.0
	 *
	 * @param int $placement_id Placement post ID.
	 * @return array<int, array{ad_id:int, weight:int}>
	 */
	public static function get_ads( int $placement_id ): array {
		return self::normalize_ads( get_post_meta( $placement_id, self::META_ADS, true ) );
	}
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test -- --filter PlacementLogicTest`
Expected: PASS — 7 tests.

- [ ] **Step 6: Lint**

Run: `composer lint -- includes/class-ad-placr-placement.php`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add includes/class-ad-placr-placement.php tests/unit/PlacementLogicTest.php tests/bootstrap.php
git commit -m "feat: add ad_placr_placement post type with weighted selection"
```

---

### Task 5: Migration (`Ad_Placr_Migration`) + wire subsystems into boot

**Files:**
- Create: `includes/class-ad-placr-migration.php`
- Test: `tests/unit/MigrationBuilderTest.php`
- Modify: `tests/bootstrap.php` (require the class)
- Modify: `ad-placr.php` (require the four new class files)
- Modify: `includes/class-ad-placr-plugin.php` (register Ad/Placement, run migration)

**Interfaces:**
- Consumes: `Ad_Placr_Ad`, `Ad_Placr_Placement`, `Ad_Placr_Positions`, `Ad_Placr_Plugin::get_settings()`.
- Produces:
  - `Ad_Placr_Migration::DB_VERSION = 1`, `Ad_Placr_Migration::OPTION_DB_VERSION = 'ad_placr_db_version'`.
  - `Ad_Placr_Migration::build_definitions( array $settings ): array` — pure; returns `array{ads: list<array{key:string,title:string,code:string,mobile_code:string}>, placements: list<array{title:string,position:string,ad_key:string,targeting:array,paragraph:int|null,slot_position:string|null}>}`.
  - `Ad_Placr_Migration::maybe_migrate(): void` — runtime; runs once, guarded by the DB-version option.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/MigrationBuilderTest.php`:

```php
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
	}
}
```

- [ ] **Step 2: Wire the class into the test bootstrap**

Append to `tests/bootstrap.php`:

```php
require dirname( __DIR__ ) . '/includes/class-ad-placr-migration.php';
```

- [ ] **Step 3: Run test to verify it fails**

Run: `composer test -- --filter MigrationBuilderTest`
Expected: FAIL — `Class "Ad_Placr_Migration" not found`.

- [ ] **Step 4: Write minimal implementation**

Create `includes/class-ad-placr-migration.php`:

```php
<?php
/**
 * One-time migration from the legacy option to Ad/Placement post types.
 *
 * @package AdPlacr
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts `ad_placr_settings` (footer sticky + in-content slots) into posts.
 *
 * @since 2.0.0
 */
final class Ad_Placr_Migration {

	public const DB_VERSION        = 1;
	public const OPTION_DB_VERSION = 'ad_placr_db_version';

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'maybe_migrate' ), 20 );
	}

	/**
	 * Run migration once when the stored DB version is behind.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		$current = (int) get_option( self::OPTION_DB_VERSION, 0 );

		if ( $current >= self::DB_VERSION ) {
			return;
		}

		self::run();

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	/**
	 * Persist the built definitions as Ad + Placement posts.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function run(): void {
		$settings = Ad_Placr_Plugin::get_settings();
		$defs     = self::build_definitions( is_array( $settings ) ? $settings : array() );

		$ad_ids = array();

		foreach ( $defs['ads'] as $ad ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => Ad_Placr_Ad::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $ad['title'],
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, Ad_Placr_Ad::META_CODE, $ad['code'] );
			update_post_meta( $post_id, Ad_Placr_Ad::META_MOBILE_CODE, $ad['mobile_code'] );
			update_post_meta( $post_id, Ad_Placr_Ad::META_STATUS, 'active' );

			$ad_ids[ $ad['key'] ] = (int) $post_id;
		}

		foreach ( $defs['placements'] as $placement ) {
			if ( ! isset( $ad_ids[ $placement['ad_key'] ] ) ) {
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => Ad_Placr_Placement::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $placement['title'],
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, Ad_Placr_Placement::META_POSITION, $placement['position'] );
			update_post_meta( $post_id, Ad_Placr_Placement::META_STATUS, 'active' );

			$targeting = $placement['targeting'];
			if ( null !== $placement['paragraph'] ) {
				$targeting['paragraph'] = $placement['paragraph'];
			}

			update_post_meta( $post_id, Ad_Placr_Placement::META_TARGETING, $targeting );
			update_post_meta(
				$post_id,
				Ad_Placr_Placement::META_ADS,
				array(
					array(
						'ad_id'  => $ad_ids[ $placement['ad_key'] ],
						'weight' => 1,
					),
				)
			);
		}
	}

	/**
	 * Build ad + placement definitions from legacy settings. Pure.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $settings Legacy `ad_placr_settings` array.
	 * @return array{ads: array<int, array<string, mixed>>, placements: array<int, array<string, mixed>>}
	 */
	public static function build_definitions( array $settings ): array {
		$defs = array(
			'ads'        => array(),
			'placements' => array(),
		);

		$fs = isset( $settings['footer_sticky'] ) && is_array( $settings['footer_sticky'] ) ? $settings['footer_sticky'] : array();

		$fs_code   = (string) ( $fs['code'] ?? '' );
		$fs_mobile = (string) ( $fs['mobile_code'] ?? '' );

		if ( ! empty( $fs['enabled'] ) && ( '' !== trim( $fs_code ) || '' !== trim( $fs_mobile ) ) ) {
			$key = 'footer_sticky';

			$defs['ads'][] = array(
				'key'         => $key,
				'title'       => 'Footer sticky',
				'code'        => $fs_code,
				'mobile_code' => $fs_mobile,
			);

			$defs['placements'][] = array(
				'title'     => 'Footer sticky',
				'position'  => Ad_Placr_Positions::STICKY_FOOTER,
				'ad_key'    => $key,
				'paragraph' => null,
				'targeting' => array(
					'post_types' => array(),
					'contexts'   => array( 'all' ),
					'devices'    => array( 'desktop', 'tablet', 'mobile' ),
				),
			);
		}

		$slots = isset( $settings['in_content_slots'] ) && is_array( $settings['in_content_slots'] ) ? $settings['in_content_slots'] : array();

		foreach ( $slots as $index => $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}

			$code   = (string) ( $slot['code'] ?? '' );
			$mobile = (string) ( $slot['mobile_code'] ?? '' );

			if ( empty( $slot['enabled'] ) || ( '' === trim( $code ) && '' === trim( $mobile ) ) ) {
				continue;
			}

			$key = 'in_content_' . (string) ( $slot['id'] ?? $index );

			$defs['ads'][] = array(
				'key'         => $key,
				'title'       => '' !== (string) ( $slot['title'] ?? '' ) ? (string) $slot['title'] : 'In-content ad',
				'code'        => $code,
				'mobile_code' => $mobile,
			);

			$position = 'before' === ( $slot['position'] ?? 'after' )
				? Ad_Placr_Positions::IN_CONTENT_BEFORE_PARAGRAPH
				: Ad_Placr_Positions::IN_CONTENT_AFTER_PARAGRAPH;

			$post_types = isset( $slot['post_types'] ) && is_array( $slot['post_types'] ) ? array_values( $slot['post_types'] ) : array();

			$defs['placements'][] = array(
				'title'     => '' !== (string) ( $slot['title'] ?? '' ) ? (string) $slot['title'] : 'In-content placement',
				'position'  => $position,
				'ad_key'    => $key,
				'paragraph' => (int) ( $slot['paragraph_index'] ?? 1 ),
				'targeting' => array(
					'post_types' => $post_types,
					'contexts'   => array( 'singular' ),
					'devices'    => array( 'desktop', 'tablet', 'mobile' ),
				),
			);
		}

		return $defs;
	}
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `composer test -- --filter MigrationBuilderTest`
Expected: PASS — 3 tests.

- [ ] **Step 6: Load the new classes at runtime**

In `ad-placr.php`, add four `require_once` lines after the existing `class-ad-placr-in-content.php` require (before the updater require):

```php
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-positions.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-ad.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-placement.php';
require_once AD_PLACR_PLUGIN_DIR . 'includes/class-ad-placr-migration.php';
```

- [ ] **Step 7: Register the new subsystems in `boot()`**

In `includes/class-ad-placr-plugin.php`, inside `boot()`, add after `Ad_Placr_In_Content::register();`:

```php
Ad_Placr_Ad::register();
Ad_Placr_Placement::register();
Ad_Placr_Migration::register();
```

- [ ] **Step 8: Verify no fatal on load (syntax + lint the runtime files)**

Run: `php -l ad-placr.php && php -l includes/class-ad-placr-migration.php`
Expected: `No syntax errors detected` for both.

Run: `composer lint`
Expected: no errors across `includes/` and `ad-placr.php`.

- [ ] **Step 9: Integration check on the Local site (WP-CLI)**

This verifies runtime registration + migration against the real WordPress install (the pure logic is already unit-tested; this confirms the WordPress wiring).

Run (from the plugin directory):

```bash
wp eval 'Ad_Placr_Plugin::instance()->boot(); do_action("init"); var_export( post_type_exists("ad_placr_ad") ); var_export( post_type_exists("ad_placr_placement") );'
```

Expected: `truetrue` (both post types registered).

Run to confirm migration ran and is idempotent:

```bash
wp option get ad_placr_db_version
wp post list --post_type=ad_placr_placement --format=count
```

Expected: `ad_placr_db_version` = `1`; placement count matches the number of enabled legacy placements (0 if none configured). Re-running a page load does not increase the count.

- [ ] **Step 10: Run the full suite + analyze**

Run: `composer test`
Expected: PASS — all unit tests green.

Run: `composer analyze`
Expected: `[OK] No errors` (or triage documented).

- [ ] **Step 11: Commit**

```bash
git add includes/class-ad-placr-migration.php includes/class-ad-placr-plugin.php ad-placr.php tests/unit/MigrationBuilderTest.php tests/bootstrap.php
git commit -m "feat: migrate legacy settings into ad + placement post types"
```

---

## Self-Review

**1. Spec coverage (this plan's slice — spec Phases 0–1):**
- Dev tooling (PHPCS + PHPStan + PHPUnit) → Task 1. ✓
- Filterable position registry → Task 2. ✓
- `ad_placr_ad` CPT + meta constants + `is_active()` → Task 3. ✓
- `ad_placr_placement` CPT + weighted ad list + weighted selection → Task 4. ✓
- Idempotent, versioned migration of footer + in-content config → Task 5. ✓
- Deferred to later plans (correctly out of scope here): rendering/frontend hooks, targeting evaluation, admin meta-box UI, shortcode/widget, analytics, new position hooks, the picker==render invariant test (needs the renderer). Noted in the scope section.

**2. Placeholder scan:** No TBD/TODO. Every code step shows complete code; every run step shows the command + expected output.

**3. Type consistency:** Meta constant names (`META_CODE`, `META_MOBILE_CODE`, `META_STATUS`, `META_POSITION`, `META_ADS`, `META_TARGETING`) are used identically across Tasks 3–5. `normalize_ads`/`choose_weighted`/`get_ads`/`build_definitions`/`normalize_status` signatures match between their Interfaces blocks, tests, and implementations. Position constants referenced in migration (`STICKY_FOOTER`, `IN_CONTENT_BEFORE_PARAGRAPH`, `IN_CONTENT_AFTER_PARAGRAPH`) all exist in Task 2's class.

**4. Scope:** Focused on a single subsystem (data foundation) that is independently testable and leaves the plugin loadable. Rendering is the next plan.
