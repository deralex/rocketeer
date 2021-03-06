<?php
namespace Rocketeer\Tests;

use ReflectionFunction;
use Rocketeer\Facades\Rocketeer;
use Rocketeer\Tests\TestCases\RocketeerTestCase;

class TasksQueueTest extends RocketeerTestCase
{
	public function testCanUseFacadeOutsideOfLaravel()
	{
		Rocketeer::before('deploy', 'ls');
		$before = Rocketeer::getTasksListeners('deploy', 'before', true);

		$this->assertEquals(array('ls'), $before);
	}

	public function testCanBuildTaskByName()
	{
		$task = $this->tasksQueue()->buildTask('Rocketeer\Tasks\Deploy');

		$this->assertInstanceOf('Rocketeer\Traits\Task', $task);
	}

	public function testCanBuildCustomTaskByName()
	{
		$tasks = $this->tasksQueue()->buildQueue(array('Rocketeer\Tasks\Check'));

		$this->assertInstanceOf('Rocketeer\Tasks\Check', $tasks[0]);
	}

	public function testCanAddCommandsToArtisan()
	{
		$command = $this->tasksQueue()->add('Rocketeer\Tasks\Deploy');
		$this->assertInstanceOf('Rocketeer\Commands\BaseTaskCommand', $command);
		$this->assertInstanceOf('Rocketeer\Tasks\Deploy', $command->getTask());
	}

	public function testCanGetTasksBeforeOrAfterAnotherTask()
	{
		$task   = $this->task('Deploy');
		$before = $this->tasksQueue()->getTasksListeners($task, 'before', true);

		$this->assertEquals(array('before', 'foobar'), $before);
	}

	public function testCanAddTasksViaFacade()
	{
		$task   = $this->task('Deploy');
		$before = $this->tasksQueue()->getTasksListeners($task, 'before', true);

		$this->tasksQueue()->before('deploy', 'composer install');

		$newBefore = array_merge($before, array('composer install'));
		$this->assertEquals($newBefore, $this->tasksQueue()->getTasksListeners($task, 'before', true));
	}

	public function testCanAddMultipleTasksViaFacade()
	{
		$task   = $this->task('Deploy');
		$after = $this->tasksQueue()->getTasksListeners($task, 'after', true);

		$this->tasksQueue()->after('deploy', array(
			'composer install',
			'bower install'
		));

		$newAfter = array_merge($after, array('composer install', 'bower install'));
		$this->assertEquals($newAfter, $this->tasksQueue()->getTasksListeners($task, 'after', true));
	}

	public function testCanAddSurroundTasksToNonExistingTasks()
	{
		$task   = $this->task('Setup');
		$this->tasksQueue()->after('setup', 'composer install');

		$after = array('composer install');
		$this->assertEquals($after, $this->tasksQueue()->getTasksListeners($task, 'after', true));
	}

	public function testCanAddSurroundTasksToMultipleTasks()
	{
		$this->tasksQueue()->after(array('cleanup', 'setup'), 'composer install');

		$after = array('composer install');
		$this->assertEquals($after, $this->tasksQueue()->getTasksListeners('setup', 'after', true));
		$this->assertEquals($after, $this->tasksQueue()->getTasksListeners('cleanup', 'after', true));
	}

	public function testCangetTasksListenersOrAfterAnotherTaskBySlug()
	{
		$after = $this->tasksQueue()->getTasksListeners('deploy', 'after', true);

		$this->assertEquals(array('after', 'foobar'), $after);
	}

	public function testCanBuildTaskFromString()
	{
		$string = 'echo "I love ducks"';

		$string = $this->tasksQueue()->buildTaskFromClosure($string);
		$this->assertInstanceOf('Rocketeer\Tasks\Closure', $string);

		$closure = $string->getClosure();
		$this->assertInstanceOf('Closure', $closure);

		$closureReflection = new ReflectionFunction($closure);
		$this->assertEquals(array('stringTask' => 'echo "I love ducks"'), $closureReflection->getStaticVariables());

		$this->assertEquals('I love ducks', $string->execute());
	}

	public function testCanBuildTaskFromClosure()
	{
		$originalClosure = function ($task) {
			return $task->getCommand()->info('echo "I love ducks"');
		};

		$closure = $this->tasksQueue()->buildTaskFromClosure($originalClosure);
		$this->assertInstanceOf('Rocketeer\Tasks\Closure', $closure);
		$this->assertEquals($originalClosure, $closure->getClosure());
	}

	public function testCanBuildQueue()
	{
		$queue = array(
			'foobar',
			function ($task) {
				return 'lol';
			},
			'Rocketeer\Tasks\Deploy'
		);

		$queue = $this->tasksQueue()->buildQueue($queue);

		$this->assertInstanceOf('Rocketeer\Tasks\Closure', $queue[0]);
		$this->assertInstanceOf('Rocketeer\Tasks\Closure', $queue[1]);
		$this->assertInstanceOf('Rocketeer\Tasks\Deploy',  $queue[2]);
	}

	public function testCanRunQueue()
	{
		$this->swapConfig(array(
			'rocketeer::default' => 'production',
		));

		$this->expectOutputString('JOEY DOESNT SHARE FOOD');
		$this->tasksQueue()->run(array(
			function ($task) {
				print 'JOEY DOESNT SHARE FOOD';
			}
		), $this->getCommand());
	}

	public function testCanRunQueueOnDifferentConnectionsAndStages()
	{
		$this->swapConfig(array(
			'rocketeer::default'       => array('staging', 'production'),
			'rocketeer::stages.stages' => array('first', 'second'),
		));

		$output = array();
		$queue = array(
			function ($task) use (&$output) {
				$output[] = $task->rocketeer->getConnection(). ' - ' .$task->rocketeer->getStage();
			}
		);

		$queue = $this->tasksQueue()->buildQueue($queue);
		$this->tasksQueue()->run($queue, $this->getCommand());

		$this->assertEquals(array(
			'staging - first',
			'staging - second',
			'production - first',
			'production - second',
		), $output);
	}

	public function testCanRunQueueViaExecute()
	{
		$this->swapConfig(array(
			'rocketeer::default' => 'production',
		));

		$output = $this->tasksQueue()->execute(array(
			'ls -a',
			function ($task) {
				return 'JOEY DOESNT SHARE FOOD';
			}
		));

		$this->assertEquals(array(
			'.'.PHP_EOL.'..'.PHP_EOL.'.gitkeep',
			'JOEY DOESNT SHARE FOOD',
		), $output);
	}

	public function testCanRunOnMultipleConnectionsViaOn()
	{
		$this->swapConfig(array(
			'rocketeer::stages.stages' => array('first', 'second'),
		));

		$output = $this->tasksQueue()->on(array('staging', 'production'), function ($task) {
			return $task->rocketeer->getConnection(). ' - ' .$task->rocketeer->getStage();
		});

		$this->assertEquals(array(
			'staging - first',
			'staging - second',
			'production - first',
			'production - second',
		), $output);
	}

	public function testCanAddEventsWithPriority()
	{
		$this->tasksQueue()->before('deploy', 'second', -5);
		$this->tasksQueue()->before('deploy', 'first');

		$listeners = $this->tasksQueue()->getTasksListeners('deploy', 'before', true);
		$this->assertEquals(array('before', 'foobar', 'first', 'second'), $listeners);
	}

	public function testCanExecuteContextualEvents()
	{
		$this->swapConfig(array(
			'rocketeer::stages.stages'            => array('hasEvent', 'noEvent'),
			'rocketeer::on.stages.hasEvent.hooks' => array('before' => array('check' => 'ls')),
		));

		$this->app['rocketeer.rocketeer']->setStage('hasEvent');
		$this->assertEquals(array('ls'), $this->tasksQueue()->getTasksListeners('check', 'before', true));

		$this->app['rocketeer.rocketeer']->setStage('noEvent');
		$this->assertEquals(array(), $this->tasksQueue()->getTasksListeners('check', 'before', true));
	}
}
