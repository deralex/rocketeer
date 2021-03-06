<?php
namespace Rocketeer\Tests;

use Rocketeer\Tests\TestCases\RocketeerTestCase;
use Rocketeer\Igniter;

class IgniterTest extends RocketeerTestCase
{
	/**
	 * The igniter instance
	 *
	 * @var Igniter
	 */
	protected $igniter;

	/**
	 * Setup the tests
	 */
	public function setUp()
	{
		parent::setUp();

		$this->igniter = new Igniter($this->app);
		unset($this->app['path.base']);
	}

	////////////////////////////////////////////////////////////////////
	//////////////////////////////// TESTS /////////////////////////////
	////////////////////////////////////////////////////////////////////

	public function testDoesntRebindBasePath()
	{
		$base = 'src';
		$this->app->instance('path.base', $base);
		$this->igniter->bindPaths();

		$this->assertEquals($base, $this->app['path.base']);
	}

	public function testCanBindBasePath()
	{
		$this->igniter->bindPaths();

		$this->assertEquals(realpath(__DIR__.'/..'), $this->app['path.base']);
	}

	public function testCanBindConfigurationPaths()
	{
		$this->igniter->bindPaths();

		$root = realpath(__DIR__.'/..');
		$this->assertEquals($root.'/.rocketeer', $this->app['path.rocketeer.config']);
		$this->assertEquals($root.'/.rocketeer/tasks.php', $this->app['path.rocketeer.tasks']);
	}

	public function testCanExportConfiguration()
	{
		$this->igniter->bindPaths();
		$this->igniter->exportConfiguration();

		$this->assertFileExists(__DIR__.'/../.rocketeer');
	}

	public function testCanReplaceStubsInConfigurationFile()
	{
		$this->igniter->bindPaths();
		$path = $this->igniter->exportConfiguration();
		$this->igniter->updateConfiguration($path, array('scm_username' => 'foobar'));

		$this->assertFileExists(__DIR__.'/../.rocketeer');
		$this->assertContains('foobar', file_get_contents(__DIR__.'/../.rocketeer/scm.php'));
	}

	public function testCanSetCurrentApplication()
	{
		$this->mock('rocketeer.server', 'Server', function ($mock) {
			return $mock->shouldReceive('setRepository')->once()->with('foobar');
		});

		$this->igniter->bindPaths();
		$path = $this->igniter->exportConfiguration();
		$this->igniter->updateConfiguration($path, array('application_name' => 'foobar', 'scm_username' => 'foobar'));

		$this->assertFileExists(__DIR__.'/../.rocketeer');
		$this->assertContains('foobar', file_get_contents(__DIR__.'/../.rocketeer/remote.php'));
	}
}
