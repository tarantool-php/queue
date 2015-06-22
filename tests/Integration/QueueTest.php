<?php

namespace Tarantool\Queue\Tests\Integration;

use Tarantool\Queue\Queue;
use Tarantool\Queue\States;
use Tarantool\Queue\Task;

abstract class QueueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Tarantool
     */
    private static $tarantool;

    /**
     * @var Queue
     */
    protected $queue;

    public static function setUpBeforeClass()
    {
        self::$tarantool = new \Tarantool(getenv('TNT_HOST'), getenv('TNT_PORT'));
    }

    public static function tearDownAfterClass()
    {
        self::$tarantool->close();
        self::$tarantool = null;
    }

    protected function setUp()
    {
        $tubeType = $this->getTubeType();
        $tubeName = uniqid(sprintf('t_%s_', $tubeType));

        self::$tarantool->call('queue._create_tube', [$tubeName, $tubeType]);
        $this->queue = new Queue(self::$tarantool, $tubeName);
    }

    protected function tearDown()
    {
        $this->queue = null;
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testPut($data)
    {
        $task = $this->queue->put($data);

        $this->assertTaskInstance($task);
        $this->assertNotNull($task->getId());
        $this->assertSame(States::READY, $task->getType());
        $this->assertSame($data, $task->getData());
    }

    public function provideTaskData()
    {
        return [
            [null],
            ['foo'],
            [42],
            [-42],
            [4.2],
            [['foo' => 'bar', 'baz']],
            //[(object) ['foo' => 'bar', 'baz']],
        ];
    }

    public function testPeek()
    {
        $task = $this->queue->put('foo');
        $peekedTask = $this->queue->peek($task->getId());

        $this->assertEquals($task, $peekedTask);
    }

    public function testTake()
    {
        $task = $this->queue->put('foo');
        $takenTask = $this->queue->take();

        $this->assertSimilar($task, $takenTask, States::TAKEN);
    }

    public function testTakeNone()
    {
        $time = time();
        $task = $this->queue->take(2);

        $this->assertGreaterThanOrEqual(2, time() - $time);
        $this->assertNull($task);
    }

    public function testRelease()
    {
        $task = $this->queue->put('foo');
        $takenTask = $this->queue->take();
        $releasedTask = $this->queue->release($takenTask->getId());

        $this->assertEquals($task, $releasedTask);
    }

    public function testAck()
    {
        $task = $this->queue->put('foo');
        $takenTask = $this->queue->take();
        $doneTask = $this->queue->ack($takenTask->getId());

        $this->assertSimilar($task, $doneTask, States::DONE);
    }

    public function testDelete()
    {
        $task = $this->queue->put('foo');
        $deletedTask = $this->queue->delete($task->getId());

        $this->assertSimilar($task, $deletedTask, States::DONE);
    }

    public function testBuryKick()
    {
        $task = $this->queue->put('foo');
        $buriedTask = $this->queue->bury($task->getId());

        $this->assertSimilar($task, $buriedTask, States::BURIED);

        $count = $this->queue->kick(1);

        $this->assertSame(1, $count);

        $peekedTask = $this->queue->peek($task->getId());

        $this->assertEquals($task, $peekedTask);
    }

    /**
     * @dataProvider provideFailureCallbackData
     * @expectedException \Exception
     */
    public function testThrowException($methodName, array $args)
    {
        call_user_func_array([$this->queue, $methodName], $args);
    }

    public function provideFailureCallbackData()
    {
        return [
            ['ack', [42]],
            ['release', [42]],
            ['peek', [42]],
            ['bury', [42]],
            ['kick', ['foo']],
            ['delete', [42]],
        ];
    }

    protected function assertTaskInstance($task)
    {
        $this->assertInstanceOf('Tarantool\Queue\Task', $task);
    }

    protected function assertSimilar(Task $expectedTask, $actualTask, $expectedType = null)
    {
        $this->assertTaskInstance($actualTask);
        $this->assertSame($expectedTask->getId(), $actualTask->getId());
        $this->assertEquals($expectedTask->getData(), $actualTask->getData());

        if (null !== $expectedType) {
            $this->assertSame($expectedType, $actualTask->getType());
        }
    }

    protected function getTubeType()
    {
        $class = new \ReflectionClass($this);
        $type = str_replace('QueueTest', '', $class->getShortName());

        return strtolower($type);
    }
}
