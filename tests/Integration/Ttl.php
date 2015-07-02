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

trait Ttl
{
    /**
     * @eval queue.tube['%tube_name%']:put('ttr_1', {ttr = 1})
     */
    public function testTimeToRun()
    {
        $task1 = $this->queue->take(.1);
        sleep(1);
        $task2 = $this->queue->take(.1);

        $this->assertTaskInstance($task1);
        $this->assertSame('ttr_1', $task1->getData());
        $this->assertEquals($task1, $task2);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('ttl_1', {ttl = 1})
     */
    public function testTimeToLive()
    {
        sleep(1);
        $task = $this->queue->take(.1);

        $this->assertNull($task);
    }

    /**
     * @eval queue.tube['%tube_name%']:put('pri_low', {pri = 2})
     * @eval queue.tube['%tube_name%']:put('pri_high', {pri = 1})
     */
    public function testPriority()
    {
        $task1 = $this->queue->take(.1);
        $this->queue->delete($task1->getId());

        $task2 = $this->queue->take(.1);
        $this->queue->delete($task2->getId());

        $this->assertSame('pri_high', $task1->getData());
        $this->assertSame('pri_low', $task2->getData());
    }

    /**
     * @eval queue.tube['%tube_name%']:put('delay_1', {delay = 1})
     */
    public function testDelay()
    {
        $task = $this->queue->take(.1);

        $this->assertNull($task);

        sleep(1);
        $task = $this->queue->take(.1);

        $this->assertTaskInstance($task);
        $this->assertSame('delay_1', $task->getData());
    }

    /**
     * @eval queue.tube['%tube_name%']:put('stat_delayed_0', {delay = 9999})
     * @eval queue.tube['%tube_name%']:put('stat_delayed_1', {delay = 9999})
     */
    public function testStatisticsDelayed()
    {
        $count = $this->queue->statistics('tasks', 'delayed');

        $this->assertSame(2, $count);
    }
}
