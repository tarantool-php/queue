<?php

declare(strict_types=1);

namespace Tarantool\Queue\Tests\Integration;

final class SubqueuettlQueueTest extends QueueTest
{
    use Ttl;
    private const SUBQUEUE = 'test_subqueue';

    public function testEmptyStats(): void
    {
        $stats = $this->queue->stats();
        self::assertSame(0, $stats['tasks']['total']);
        self::assertArrayHasKey('subqueues', $stats['extra']);
    }

    public function getSubqueue() : ?string
    {
        return self::SUBQUEUE;
    }

    public function testStats() : void
    {
        $stats = $this->queue->stats();

        self::assertEquals([
            'tasks' => [
                'taken' => 0, 'buried' => 1, 'ready' => 2,
                'done' => 2, 'delayed' => 0, 'total' => 3,
            ],
            'calls' => [
                'ack' => 1, 'delete' => 1, 'take' => 3, 'kick' => 1,
                'release' => 2, 'touch' => 0, 'put' => 5, 'bury' => 2,
                'ttr' => 0, 'delay' => 0, 'ttl' => 0,
            ],
        ], array_diff_key($stats, ['extra' => '']));

        self::assertArrayHasKey('subqueues', $stats['extra']);
	self::assertArrayHasKey('test_subqueue', $stats['extra']['subqueues']);

	$sub = $stats['extra']['subqueues']['test_subqueue'];
        self::assertSame(2, $sub['ready']);
        self::assertSame(0, $sub['taken']);
        self::assertSame(1, $sub['buried']);
        self::assertSame(0, $sub['delayed']);
        self::assertSame(3, $sub['total']);
    }
}
