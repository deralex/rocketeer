<?php
namespace Rocketeer\Tests\Tasks;

use Rocketeer\Tests\TestCases\RocketeerTestCase;

class RollbackTest extends RocketeerTestCase
{
	public function testCanRollbackRelease()
	{
		$this->task('Rollback')->execute();

		$this->assertEquals(10000000000000, $this->app['rocketeer.releases']->getCurrentRelease());
	}
}
