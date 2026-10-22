<?php
    /**
 * This file is part of the tarantool/queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tarantool\Queue\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tarantool\PhpUnit\Attribute\Lua;
use Tarantool\Queue\States;

abstract class QueueTest extends TestCase
{
    /**
     * @param mixed $data
     */
    #[DataProvider('provideTaskData')]
    #[Lua("create_tube('%tube_name%', '%tube_type%')")]
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

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('peek_0')")]
    public function testPeek() : void
    {
        $task = $this->queue->peek(0);

        self::assertTask($task, 0, States::READY, 'peek_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('take')")]
    public function testTake() : void
    {
        $task = $this->queue->take();

        self::assertTask($task, 0, States::TAKEN, 'take');
    }

    #[Lua("create_tube('%tube_name%', '%tube_type%')")]
    public function testTakeNone() : void
    {
        $time = time();
        $task = $this->queue->take(2);

        self::assertGreaterThanOrEqual(2, time() - $time);
        self::assertNull($task);
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('release_0')")]
    #[Lua('tube:take()')]
    public function testRelease() : void
    {
        $task = $this->queue->release(0);

        self::assertTask($task, 0, States::READY, 'release_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('ack_0')")]
    #[Lua('tube:take()')]
    public function testAck() : void
    {
        $task = $this->queue->ack(0);

        self::assertTask($task, 0, States::DONE, 'ack_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('bury_0')")]
    public function testBury() : void
    {
        $task = $this->queue->bury(0);

        self::assertTask($task, 0, States::BURIED, 'bury_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('kick_1')")]
    #[Lua('tube:bury(0)')]
    public function testKickOne() : void
    {
        $count = $this->queue->kick(1);

        self::assertSame(1, $count);
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('kick_1')")]
    #[Lua("tube:put('kick_2')")]
    #[Lua("tube:put('kick_3')")]
    #[Lua('tube:bury(0)')]
    #[Lua('tube:bury(1)')]
    #[Lua('tube:bury(2)')]
    public function testKickMany() : void
    {
        $count = $this->queue->kick(3);

        self::assertSame(3, $count);
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('delete_0')")]
    public function testDelete() : void
    {
        $task = $this->queue->delete(0);

        self::assertTask($task, 0, States::DONE, 'delete_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('truncate_0')")]
    #[Lua("tube:put('truncate_1')")]
    public function testTruncate() : void
    {
        self::assertSame(2, $this->queue->stats('tasks.total'));

        $this->queue->truncate();

        self::assertSame(0, $this->queue->stats('tasks.total'));
    }

    #[Lua("create_tube('%tube_name%', '%tube_type%')")]
    public function testTruncateEmpty() : void
    {
        self::assertSame(0, $this->queue->stats('tasks.total'));

        $this->queue->truncate();

        self::assertSame(0, $this->queue->stats('tasks.total'));
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('stat_0')")]
    #[Lua("tube:put('stat_1')")]
    #[Lua("tube:put('stat_2')")]
    #[Lua("tube:put('stat_3')")]
    #[Lua("tube:put('stat_4')")]
    #[Lua('tube:delete(4)')]
    #[Lua('tube:take(.001)')]
    #[Lua('tube:release(0)')]
    #[Lua('tube:take(.001)')]
    #[Lua('tube:ack(0)')]
    #[Lua('tube:bury(1)')]
    #[Lua('tube:bury(2)')]
    #[Lua('tube:kick(1)')]
    #[Lua('tube:take(.001)')]
    public function testStats() : void
    {
        $stats = $this->queue->stats();
        if (\array_key_exists('extra', $stats)) {
            self::assertSame([], $stats['extra']);
            unset($stats['extra']);
        }

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
        ], $stats);
    }

    #[Lua("create_tube('%tube_name%', '%tube_type%')")]
    public function testEmptyStats() : void
    {
        $stats = $this->queue->stats();
        if (\array_key_exists('extra', $stats)) {
            self::assertSame([], $stats['extra']);
            unset($stats['extra']);
        }

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

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('stat_0')")]
    #[Lua("tube:put('stat_1')")]
    #[Lua("tube:put('stat_2')")]
    #[Lua("tube:put('stat_3')")]
    #[Lua("tube:put('stat_4')")]
    #[Lua("tube:put('stat_5')")]
    #[Lua("tube:put('stat_6')")]
    #[Lua("tube:put('stat_7')")]
    #[Lua("tube:put('stat_8')")]
    #[Lua("tube:put('stat_9')")]
    #[Lua('tube:take(.001)')]
    #[Lua('tube:release(0)')]
    #[Lua('tube:take(.001)')]
    #[Lua('tube:ack(0)')]
    #[Lua('tube:bury(1)')]
    #[Lua('tube:bury(2)')]
    #[Lua('tube:bury(3)')]
    #[Lua('tube:kick(1)')]
    #[Lua('tube:delete(6)')]
    #[Lua('tube:delete(7)')]
    #[Lua('tube:delete(8)')]
    #[Lua('tube:take(.001)')]
    public function testStatsPath() : void
    {
        self::assertEquals([
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

        self::assertEquals([
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

    #[DataProvider('provideStatsInvalidPathData')]
    #[Lua("create_tube('%tube_name%', '%tube_type%')")]
    public function testStatsInvalidPath(?string $path) : void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Invalid path ".*?"$/');

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

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua('tube.pow = function(self, base, exp) return math.pow(base, exp) end')]
    public function testCall() : void
    {
        $result = $this->queue->call('pow', 2, 8);

        self::assertSame(256, $result[0]);
    }

    #[DataProvider('provideFailureCallbackData')]
    public function testThrowException(string $methodName, array $args) : void
    {
        $this->expectException(\Throwable::class);

        $this->queue->$methodName(...$args);
    }

    public function provideFailureCallbackData() : array
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
}
