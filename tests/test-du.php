<?php
/**
 * Class DuTest
 *
 * @package Baizman_Design_Disk_Usage
 */

use disk_usage_mu\mu_plugin;

class DuTest extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public static function setUpBeforeClass():void {
	}

	/**
	 * @return void
	 */
	public static function tearDownAfterClass ():void {
	}

	/**
	 * @return void
	 */
	protected function setUp ():void {
	}

	/**
	 * @return void
	 */
	protected function tearDown ():void {
	}

	/**
	 * Test that we're in a WordPress instance.
	 *
	 * @return void
	 */
	public function test_is_wordpress(): void {
		$this->assertTrue( defined( constant_name: 'ABSPATH' ) );
	}

	/**
	 * Test setting and getting the operating system.
	 *
	 * @return void
	 */
	public function test_operating_system(): void {
		$mu = new mu_plugin();
		$mu->set_os( os: 'macos' );
		$this->assertEquals( expected: $mu->get_os(), actual: 'macos' );
	}

}
