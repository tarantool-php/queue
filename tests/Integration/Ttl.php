<?php

namespace Tarantool\Queue\Tests\Integration;

trait Ttl
{
    public function testTimeToRun()
    {
        $this->queue->put('ttr_1', ['ttr' => 1]);

        $task1 = $this->queue->take(.1);
        sleep(1);
        $task2 = $this->queue->take(.1);

        $this->assertTaskInstance($task1);
        $this->assertSame('ttr_1', $task1->getData());
        $this->assertEquals($task1, $task2);
    }

    public function testTimeToLive()
    {
        $this->queue->put('ttl_1', ['ttl' => 1]);

        sleep(1);
        $task = $this->queue->take(.1);

        $this->assertNull($task);
    }

    public function testPriority()
    {
        $this->queue->put('pri_low', ['pri' => 2]);
        $this->queue->put('pri_high', ['pri' => 1]);

        $task1 = $this->queue->take(.1);
        $this->queue->delete($task1->getId());

        $task2 = $this->queue->take(.1);
        $this->queue->delete($task2->getId());

        $this->assertSame('pri_high', $task1->getData());
        $this->assertSame('pri_low', $task2->getData());
    }

    public function testDelay()
    {
        $this->queue->put('delay_1', ['delay' => 1]);
        $task = $this->queue->take(.1);

        $this->assertNull($task);

        sleep(1);
        $task = $this->queue->take(.1);

        $this->assertTaskInstance($task);
        $this->assertSame('delay_1', $task->getData());
    }
}
