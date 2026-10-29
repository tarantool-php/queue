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

use Tarantool\PhpUnit\Attribute\Lua;
use Tarantool\Queue\Queue;
use Tarantool\Queue\States;
use Tarantool\Queue\Subqueue;

final class SubqueueTtlQueueTest extends TestCase
{
    private function createSubqueue(string $name = 'emails') : Subqueue
    {
        return Queue::subqueueTtl($this->getClient(), $this->getQueueName())
            ->subqueue($name);
    }

    #[Lua("create_tube('%tube_name%', '%tube_type%')")]
    public function testTakeFromNewSubqueueReturnsNull() : void
    {
        self::assertNull($this->createSubqueue('created-on-first-put')->take(0.0));
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    public function testPut() : void
    {
        $subqueue = $this->createSubqueue();

        self::assertTask($subqueue->put('payload', ttl: 60.0), 0, States::READY, 'payload');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('peek_0', {subqueue = 'emails'})")]
    public function testPeek() : void
    {
        self::assertTask($this->createSubqueue()->peek(0), 0, States::READY, 'peek_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('take_0', {subqueue = 'emails'})")]
    public function testTake() : void
    {
        self::assertTask($this->createSubqueue()->take(1.0), 0, States::TAKEN, 'take_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('ack_0', {subqueue = 'emails'})")]
    #[Lua("tube:take(0.001, {subqueue = 'emails'})")]
    public function testAck() : void
    {
        self::assertTask($this->createSubqueue()->ack(0), 0, States::DONE, 'ack_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('release_0', {subqueue = 'emails'})")]
    #[Lua("tube:take(0.001, {subqueue = 'emails'})")]
    public function testRelease() : void
    {
        self::assertTask($this->createSubqueue()->release(0), 0, States::READY, 'release_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('release_delay_0', {subqueue = 'emails'})")]
    #[Lua("tube:take(0.001, {subqueue = 'emails'})")]
    public function testReleaseWithDelay() : void
    {
        self::assertTask($this->createSubqueue()->release(0, delay: 0.1), 0, States::DELAYED, 'release_delay_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('touch_0', {subqueue = 'emails', ttr = 2.0})")]
    #[Lua("tube:take(0.001, {subqueue = 'emails'})")]
    public function testTouch() : void
    {
        $task = $this->createSubqueue()->touch(0, 5.0);

        self::assertNotNull($task);
        self::assertSame(0, $task->getId());
        self::assertSame(States::TAKEN, $task->getState());
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('bury_0', {subqueue = 'emails'})")]
    public function testBury() : void
    {
        self::assertTask($this->createSubqueue()->bury(0), 0, States::BURIED, 'bury_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('kick_0', {subqueue = 'emails'})")]
    #[Lua('tube:bury(0)')]
    public function testKick() : void
    {
        self::assertSame(1, $this->createSubqueue()->kick(1));
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('delete_0', {subqueue = 'emails'})")]
    public function testDelete() : void
    {
        self::assertTask($this->createSubqueue()->delete(0), 0, States::DONE, 'delete_0');
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    #[Lua("tube:put('truncate_0', {subqueue = 'emails'})")]
    #[Lua("tube:put('truncate_1', {subqueue = 'emails'})")]
    public function testTruncate() : void
    {
        $subqueue = $this->createSubqueue();

        self::assertSame(2, $this->queue->stats('tasks.total'));
        $subqueue->truncate();
        self::assertSame(0, $this->queue->stats('tasks.total'));
    }
}
