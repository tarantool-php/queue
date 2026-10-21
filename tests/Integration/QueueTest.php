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

use Tarantool\Queue\States;

abstract class QueueTest extends TestCase
{
    /**
     * @dataProvider provideTaskData
     * @lua create_tube('%tube_name%', '%tube_type%')
     *
     * @param mixed $data
     */
    public function testPut($data) : void
    {
        $task = $this->queue->put($data, $this->withSubqueue());

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
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('peek_0', {subqueue = %subqueue%})
     */
    public function testPeek() : void
    {
        $task = $this->queue->peek(0, $this->withSubqueue());

        self::assertTask($task, 0, States::READY, 'peek_0');
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('take', {subqueue = %subqueue%})
     */
    public function testTake() : void
    {
        $task = $this->queue->take(0, $this->withSubqueue());

        self::assertTask($task, 0, States::TAKEN, 'take');
    }

    /**
     * @lua create_tube('%tube_name%', '%tube_type%')
     */
    public function testTakeNone() : void
    {
        $time = time();
        $task = $this->queue->take(2, $this->withSubqueue());

        self::assertGreaterThanOrEqual(2, time() - $time);
        self::assertNull($task);
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('release_0', {subqueue = %subqueue%})
     * @lua tube:take(0, {subqueue = %subqueue%})
     */
    public function testRelease() : void
    {
        $task = $this->queue->release(0, $this->withSubqueue());

        self::assertTask($task, 0, States::READY, 'release_0');
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('ack_0', {subqueue = %subqueue%})
     * @lua tube:take(0, {subqueue = %subqueue%})
     */
    public function testAck() : void
    {
        $task = $this->queue->ack(0, $this->withSubqueue());

        self::assertTask($task, 0, States::DONE, 'ack_0');
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('bury_0', {subqueue = %subqueue%})
     */
    public function testBury() : void
    {
        $task = $this->queue->bury(0, $this->withSubqueue());

        self::assertTask($task, 0, States::BURIED, 'bury_0');
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('kick_1', {subqueue = %subqueue%})
     * @lua tube:bury(0, {subqueue = %subqueue%})
     */
    public function testKickOne() : void
    {
        $count = $this->queue->kick(1, $this->withSubqueue());

        self::assertSame(1, $count);
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('kick_1', {subqueue = %subqueue%})
     * @lua tube:put('kick_2', {subqueue = %subqueue%})
     * @lua tube:put('kick_3', {subqueue = %subqueue%})
     * @lua tube:bury(0, {subqueue = %subqueue%})
     * @lua tube:bury(1, {subqueue = %subqueue%})
     * @lua tube:bury(2, {subqueue = %subqueue%})
     */
    public function testKickMany() : void
    {
        $count = $this->queue->kick(3, $this->withSubqueue());

        self::assertSame(3, $count);
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('delete_0', {subqueue = %subqueue%})
     */
    public function testDelete() : void
    {
        $task = $this->queue->delete(0, $this->withSubqueue());

        self::assertTask($task, 0, States::DONE, 'delete_0');
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('truncate_0', {subqueue = %subqueue%})
     * @lua tube:put('truncate_1', {subqueue = %subqueue%})
     */
    public function testTruncate() : void
    {
        self::assertSame(2, $this->queue->stats('tasks.total'));

        $this->queue->truncate($this->withSubqueue());

        self::assertSame(0, $this->queue->stats('tasks.total'));
    }

    /**
     * @lua create_tube('%tube_name%', '%tube_type%')
     */
    public function testTruncateEmpty() : void
    {
        self::assertSame(0, $this->queue->stats('tasks.total'));

        $this->queue->truncate();

        self::assertSame(0, $this->queue->stats('tasks.total'));
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('stat_0', {subqueue = %subqueue%})
     * @lua tube:put('stat_1', {subqueue = %subqueue%})
     * @lua tube:put('stat_2', {subqueue = %subqueue%})
     * @lua tube:put('stat_3', {subqueue = %subqueue%})
     * @lua tube:put('stat_4', {subqueue = %subqueue%})
     * @lua tube:delete(4, {subqueue = %subqueue%})
     * @lua tube:take(.001, {subqueue = %subqueue%})
     * @lua tube:release(0, {subqueue = %subqueue%})
     * @lua tube:take(.001, {subqueue = %subqueue%})
     * @lua tube:ack(0, {subqueue = %subqueue%})
     * @lua tube:bury(1, {subqueue = %subqueue%})
     * @lua tube:bury(2, {subqueue = %subqueue%})
     * @lua tube:kick(1, {subqueue = %subqueue%})
     * @lua tube:take(.001, {subqueue = %subqueue%})
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
            'extra' => [
            ],
        ], $stats);
    }

    /**
     * @lua create_tube('%tube_name%', '%tube_type%')
     */
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
            'extra' => [
            ],
        ], $stats);
    }

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube:put('stat_0', {subqueue = %subqueue%})
     * @lua tube:put('stat_1', {subqueue = %subqueue%})
     * @lua tube:put('stat_2', {subqueue = %subqueue%})
     * @lua tube:put('stat_3', {subqueue = %subqueue%})
     * @lua tube:put('stat_4', {subqueue = %subqueue%})
     * @lua tube:put('stat_5', {subqueue = %subqueue%})
     * @lua tube:put('stat_6', {subqueue = %subqueue%})
     * @lua tube:put('stat_7', {subqueue = %subqueue%})
     * @lua tube:put('stat_8', {subqueue = %subqueue%})
     * @lua tube:put('stat_9', {subqueue = %subqueue%})
     * @lua tube:take(.001, {subqueue = %subqueue%})
     * @lua tube:release(0, {subqueue = %subqueue%})
     * @lua tube:take(.001, {subqueue = %subqueue%})
     * @lua tube:ack(0, {subqueue = %subqueue%})
     * @lua tube:bury(1, {subqueue = %subqueue%})
     * @lua tube:bury(2, {subqueue = %subqueue%})
     * @lua tube:bury(3, {subqueue = %subqueue%})
     * @lua tube:kick(1, {subqueue = %subqueue%})
     * @lua tube:delete(6, {subqueue = %subqueue%})
     * @lua tube:delete(7, {subqueue = %subqueue%})
     * @lua tube:delete(8, {subqueue = %subqueue%})
     * @lua tube:take(.001, {subqueue = %subqueue%})
     */
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

    /**
     * @dataProvider provideStatsInvalidPathData
     * @lua create_tube('%tube_name%', '%tube_type%')
     */
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

    /**
     * @lua tube = create_tube('%tube_name%', '%tube_type%')
     * @lua tube.pow = function(self, base, exp) return math.pow(base, exp) end
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

        $this->queue->$methodName(...array_merge($args, [$this->withSubqueue()]));
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
}
