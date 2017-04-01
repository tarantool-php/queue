<?php

/*
 * This file is part of the Tarantool Queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tarantool\Queue\Tests\Integration;

use Tarantool\Client\Client;
use Tarantool\Client\Connection\StreamConnection;
use Tarantool\Client\Packer\PurePacker;
use Tarantool\Queue\Queue;
use Tarantool\Queue\States;

abstract class QueueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var \Tarantool|\Tarantool\Client\Client
     */
    private static $client;

    public static function setUpBeforeClass()
    {
        if (class_exists('Tarantool', false)) {
            self::$client = new \Tarantool(
                getenv('TARANTOOL_HOST'),
                getenv('TARANTOOL_PORT')
            );

            return;
        }

        $uri = sprintf('tcp://%s:%s', getenv('TARANTOOL_HOST'), getenv('TARANTOOL_PORT'));
        $conn = new StreamConnection($uri);

        self::$client = new Client($conn, new PurePacker());
    }

    public static function tearDownAfterClass()
    {
        self::$client = null;
    }

    protected function setUp()
    {
        $name = preg_replace('/^test([^\s]+).*$/', '\1', $this->getName());
        $name = strtolower($name);

        $tubeType = $this->getTubeType();
        $tubeName = sprintf('t_%s_%s', $tubeType, $name);

        self::$client->evaluate('create_tube(...)', [$tubeName, $tubeType]);

        $ann = $this->getAnnotations();
        if (!empty($ann['method']['eval'])) {
            foreach ($ann['method']['eval'] as $eval) {
                self::$client->evaluate(str_replace('%tube_name%', $tubeName, $eval));
            }
        }

        $this->queue = new Queue(self::$client, $tubeName);
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

        $this->assertTask($task, 0, States::READY, $data);
    }

    public function provideTaskData()
    {
        return [
            [null],
            [true],
            ['foo'],
            ["\x04\x00\xa0\x00\x00"],
            [42],
            [-42],
            [4.2],
            [['foo' => 'bar', 'baz' => ['qux' => false, -4.2]]],
        ];
    }

    /**
     * @eval queue.tube['%tube_name%']:put('peek_0')
     */
    public function testPeek()
    {
        $task = $this->queue->peek(0);

        $this->assertTask($task, 0, States::READY, 'peek_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('take')
     */
    public function testTake()
    {
        $task = $this->queue->take();

        $this->assertTask($task, 0, States::TAKEN, 'take');
    }

    public function testTakeNone()
    {
        $time = time();
        $task = $this->queue->take(2);

        $this->assertGreaterThanOrEqual(2, time() - $time);
        $this->assertNull($task);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('release_0')
     * @eval queue.tube['%tube_name%']:take()
     */
    public function testRelease()
    {
        $task = $this->queue->release(0);

        $this->assertTask($task, 0, States::READY, 'release_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('ack_0')
     * @eval queue.tube['%tube_name%']:take()
     */
    public function testAck()
    {
        $task = $this->queue->ack(0);

        $this->assertTask($task, 0, States::DONE, 'ack_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('bury_0')
     */
    public function testBury()
    {
        $task = $this->queue->bury(0);

        $this->assertTask($task, 0, States::BURIED, 'bury_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('kick_1')
     * @eval queue.tube['%tube_name%']:bury(0)
     */
    public function testKickOne()
    {
        $count = $this->queue->kick(1);

        $this->assertSame(1, $count);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('kick_1')
     * @eval queue.tube['%tube_name%']:put('kick_2')
     * @eval queue.tube['%tube_name%']:put('kick_3')
     * @eval queue.tube['%tube_name%']:bury(0)
     * @eval queue.tube['%tube_name%']:bury(1)
     * @eval queue.tube['%tube_name%']:bury(2)
     */
    public function testKickMany()
    {
        $count = $this->queue->kick(3);

        $this->assertSame(3, $count);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('delete_0')
     */
    public function testDelete()
    {
        $task = $this->queue->delete(0);

        $this->assertTask($task, 0, States::DONE, 'delete_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('truncate_0')
     * @eval queue.tube['%tube_name%']:put('truncate_1')
     */
    public function testTruncate()
    {
        $this->assertSame(2, $this->queue->stats('tasks.total'));

        $this->queue->truncate();

        $this->assertSame(0, $this->queue->stats('tasks.total'));
    }

    public function testTruncateEmpty()
    {
        $this->assertSame(0, $this->queue->stats('tasks.total'));

        $this->queue->truncate();

        $this->assertSame(0, $this->queue->stats('tasks.total'));
    }

    /**
     * @eval queue.tube['%tube_name%']:put('stat_0')
     * @eval queue.tube['%tube_name%']:put('stat_1')
     * @eval queue.tube['%tube_name%']:put('stat_2')
     * @eval queue.tube['%tube_name%']:put('stat_3')
     * @eval queue.tube['%tube_name%']:put('stat_4')
     * @eval queue.tube['%tube_name%']:delete(4)
     * @eval queue.tube['%tube_name%']:take(.001)
     * @eval queue.tube['%tube_name%']:release(0)
     * @eval queue.tube['%tube_name%']:take(.001)
     * @eval queue.tube['%tube_name%']:ack(0)
     * @eval queue.tube['%tube_name%']:bury(1)
     * @eval queue.tube['%tube_name%']:bury(2)
     * @eval queue.tube['%tube_name%']:kick(1)
     * @eval queue.tube['%tube_name%']:take(.001)
     */
    public function testStats()
    {
        $stats = $this->queue->stats();

        $this->assertEquals([
            'tasks' => [
                'taken' => 1,
                'buried' => 1,
                'ready' => 1,
                'done' => 2,
                'delayed' => 0,
                'total' => 3,
            ],
            'calls' => [
                'ack' => 1,
                'delete' => 1,
                'take' => 3,
                'kick' => 1,
                'release' => 1,
                'touch' => 0,
                'put' => 5,
                'bury' => 2,
            ],
        ], $stats, '', 0.0, 3, true);
    }

    public function testEmptyStats()
    {
        $stats = $this->queue->stats();

        $this->assertEquals([
            'tasks' => [
                'taken' => 0,
                'buried' => 0,
                'ready' => 0,
                'done' => 0,
                'delayed' => 0,
                'total' => 0,
            ],
            'calls' => [
                'ack' => 0,
                'bury' => 0,
                'delete' => 0,
                'kick' => 0,
                'put' => 0,
                'release' => 0,
                'take' => 0,
                'touch' => 0,
            ],
        ], $stats);
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

    protected function assertTask($task, $expectedId, $expectedState, $expectedData)
    {
        $this->assertTaskInstance($task);

        $this->assertSame($expectedId, $task->getId());
        $this->assertSame($expectedState, $task->getState());
        $this->assertSame($expectedData, $task->getData());
    }

    protected function getTubeType()
    {
        $class = new \ReflectionClass($this);
        $type = str_replace('QueueTest', '', $class->getShortName());

        return strtolower($type);
    }
}
