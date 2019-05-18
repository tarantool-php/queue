<?php

/**
 * This file is part of the Tarantool Queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tarantool\Queue\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tarantool\Client\Client;
use Tarantool\Queue\Queue;
use Tarantool\Queue\States;
use Tarantool\Queue\Task;

abstract class QueueTest extends TestCase
{
    protected $queueName;

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var \Tarantool|Client
     */
    private static $client;

    public static function setUpBeforeClass() : void
    {
        $host = getenv('TARANTOOL_HOST');
        $port = (int) getenv('TARANTOOL_PORT');

        self::$client = extension_loaded('tarantool')
            ? new \Tarantool($host, $port)
            : Client::fromDsn(sprintf('tcp://%s:%d', $host, $port));
    }

    public static function tearDownAfterClass() : void
    {
        self::$client = null;
    }

    protected function setUp() : void
    {
        $testName = preg_replace('/^test(\S+).*$/', '\1', $this->getName());
        $testName = strtolower($testName);

        $tubeType = $this->getTubeType();
        $queueName = sprintf('t_%s_%s', $tubeType, $testName);

        $this->evaluate('create_tube(...)', $queueName, $tubeType);

        $ann = $this->getAnnotations();
        if (!empty($ann['method']['eval'])) {
            foreach ($ann['method']['eval'] as $eval) {
                $this->evaluate(str_replace('%tube_name%', $queueName, $eval));
            }
        }

        $this->queueName = $queueName;
        $this->queue = new Queue(self::$client, $queueName);
    }

    protected function tearDown() : void
    {
        $this->queue = null;
    }

    public function testGetName() : void
    {
        self::assertSame($this->queueName, $this->queue->getName());
    }

    /**
     * @dataProvider provideTaskData
     */
    public function testPut($data) : void
    {
        $task = $this->queue->put($data);

        self::assertTask($task, 0, States::READY, $data);
    }

    public function provideTaskData() : iterable
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
    public function testPeek() : void
    {
        $task = $this->queue->peek(0);

        self::assertTask($task, 0, States::READY, 'peek_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('take')
     */
    public function testTake() : void
    {
        $task = $this->queue->take();

        self::assertTask($task, 0, States::TAKEN, 'take');
    }

    public function testTakeNone() : void
    {
        $time = time();
        $task = $this->queue->take(2);

        self::assertGreaterThanOrEqual(2, time() - $time);
        self::assertNull($task);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('release_0')
     * @eval queue.tube['%tube_name%']:take()
     */
    public function testRelease() : void
    {
        $task = $this->queue->release(0);

        self::assertTask($task, 0, States::READY, 'release_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('ack_0')
     * @eval queue.tube['%tube_name%']:take()
     */
    public function testAck() : void
    {
        $task = $this->queue->ack(0);

        self::assertTask($task, 0, States::DONE, 'ack_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('bury_0')
     */
    public function testBury() : void
    {
        $task = $this->queue->bury(0);

        self::assertTask($task, 0, States::BURIED, 'bury_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('kick_1')
     * @eval queue.tube['%tube_name%']:bury(0)
     */
    public function testKickOne() : void
    {
        $count = $this->queue->kick(1);

        self::assertSame(1, $count);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('kick_1')
     * @eval queue.tube['%tube_name%']:put('kick_2')
     * @eval queue.tube['%tube_name%']:put('kick_3')
     * @eval queue.tube['%tube_name%']:bury(0)
     * @eval queue.tube['%tube_name%']:bury(1)
     * @eval queue.tube['%tube_name%']:bury(2)
     */
    public function testKickMany() : void
    {
        $count = $this->queue->kick(3);

        self::assertSame(3, $count);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('delete_0')
     */
    public function testDelete() : void
    {
        $task = $this->queue->delete(0);

        self::assertTask($task, 0, States::DONE, 'delete_0');
    }

    /**
     * @eval queue.tube['%tube_name%']:put('truncate_0')
     * @eval queue.tube['%tube_name%']:put('truncate_1')
     */
    public function testTruncate() : void
    {
        self::assertSame(2, $this->queue->stats('tasks.total'));

        $this->queue->truncate();

        self::assertSame(0, $this->queue->stats('tasks.total'));
    }

    public function testTruncateEmpty() : void
    {
        self::assertSame(0, $this->queue->stats('tasks.total'));

        $this->queue->truncate();

        self::assertSame(0, $this->queue->stats('tasks.total'));
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
    public function testStats() : void
    {
        $stats = $this->queue->stats();

        self::assertEquals([
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
                'ttr' => 0,
                'delay' => 0,
                'ttl' => 0,
            ],
        ], $stats, '', 0.0, 3, true);
    }

    public function testEmptyStats() : void
    {
        $stats = $this->queue->stats();

        self::assertEquals([
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
                'ttr' => 0,
                'delay' => 0,
                'ttl' => 0,
            ],
        ], $stats);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('stat_0')
     * @eval queue.tube['%tube_name%']:put('stat_1')
     * @eval queue.tube['%tube_name%']:put('stat_2')
     * @eval queue.tube['%tube_name%']:put('stat_3')
     * @eval queue.tube['%tube_name%']:put('stat_4')
     * @eval queue.tube['%tube_name%']:put('stat_5')
     * @eval queue.tube['%tube_name%']:put('stat_6')
     * @eval queue.tube['%tube_name%']:put('stat_7')
     * @eval queue.tube['%tube_name%']:put('stat_8')
     * @eval queue.tube['%tube_name%']:put('stat_9')
     * @eval queue.tube['%tube_name%']:take(.001)
     * @eval queue.tube['%tube_name%']:release(0)
     * @eval queue.tube['%tube_name%']:take(.001)
     * @eval queue.tube['%tube_name%']:ack(0)
     * @eval queue.tube['%tube_name%']:bury(1)
     * @eval queue.tube['%tube_name%']:bury(2)
     * @eval queue.tube['%tube_name%']:bury(3)
     * @eval queue.tube['%tube_name%']:kick(1)
     * @eval queue.tube['%tube_name%']:delete(6)
     * @eval queue.tube['%tube_name%']:delete(7)
     * @eval queue.tube['%tube_name%']:delete(8)
     * @eval queue.tube['%tube_name%']:take(.001)
     */
    public function testStatsPath() : void
    {
        self::assertSameArray([
            'taken' => 1,
            'buried' => 2,
            'ready' => 3,
            'done' => 4,
            'delayed' => 0,
            'total' => 6,
        ], $this->queue->stats('tasks'));

        self::assertSame(1, $this->queue->stats('tasks.taken'));
        self::assertSame(2, $this->queue->stats('tasks.buried'));
        self::assertSame(3, $this->queue->stats('tasks.ready'));
        self::assertSame(4, $this->queue->stats('tasks.done'));
        self::assertSame(0, $this->queue->stats('tasks.delayed'));
        self::assertSame(6, $this->queue->stats('tasks.total'));

        self::assertSameArray([
            'ack' => 1,
            'delete' => 3,
            'take' => 3,
            'kick' => 1,
            'release' => 1,
            'touch' => 0,
            'put' => 10,
            'bury' => 3,
            'ttr' => 0,
            'delay' => 0,
            'ttl' => 0,
        ], $this->queue->stats('calls'));

        self::assertSame(1, $this->queue->stats('calls.ack'));
        self::assertSame(3, $this->queue->stats('calls.delete'));
        self::assertSame(3, $this->queue->stats('calls.take'));
        self::assertSame(1, $this->queue->stats('calls.kick'));
        self::assertSame(1, $this->queue->stats('calls.release'));
        self::assertSame(0, $this->queue->stats('calls.touch'));
        self::assertSame(10, $this->queue->stats('calls.put'));
        self::assertSame(3, $this->queue->stats('calls.bury'));
    }

    /**
     * @dataProvider provideStatsInvalidPathData
     */
    public function testStatsInvalidPath($path) : void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/^Invalid path ".*?"\.$/');

        $this->queue->stats($path);
    }

    public function provideStatsInvalidPathData() : iterable
    {
        return [
            [''],
            ['.'],
            ['foo'],
            ['tasks.foo'],
            ['.tasks'],
            ['tasks.'],
            ['calls.foo'],
            ['.calls'],
            ['calls.'],
        ];
    }

    /**
     * @eval queue.tube['%tube_name%'].pow = function(self, base, exp) return math.pow(base, exp) end
     */
    public function testCall() : void
    {
        $result = $this->queue->call('pow', 2, 8);

        self::assertSame(256, $result[0]);
    }

    /**
     * @dataProvider provideFailureCallbackData
     */
    public function testThrowException(string $methodName, array $args) : void
    {
        $this->expectException(\Throwable::class);

        $this->queue->$methodName(...$args);
    }

    public function provideFailureCallbackData() : iterable
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

    final protected static function assertTaskInstance($task) : void
    {
        self::assertInstanceOf(Task::class, $task);
    }

    final protected static function assertTask(Task $task, int $expectedId, string $expectedState, $expectedData) : void
    {
        self::assertSame($expectedId, $task->getId());
        self::assertSame($expectedState, $task->getState());
        self::assertSame($expectedData, $task->getData());
    }

    final protected static function assertSameArray(array $expected, array $actual) : void
    {
        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    final protected function getQueue() : Queue
    {
        return $this->queue;
    }

    final protected function getTubeType() : string
    {
        $class = new \ReflectionClass($this);
        $type = str_replace('QueueTest', '', $class->getShortName());

        return strtolower($type);
    }

    final protected function evaluate(string $expr, ...$args) : array
    {
        return self::$client instanceof Client
            ? self::$client->evaluate($expr, ...$args)
            : self::$client->evaluate($expr, $args);
    }
}
