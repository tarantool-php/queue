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

namespace Tarantool\Queue\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tarantool\Client\Request\CallRequest;
use Tarantool\PhpUnit\Client\TestDoubleClient;
use Tarantool\PhpUnit\Client\TestDoubleFactory;
use Tarantool\Queue\FifoQueue;
use Tarantool\Queue\FifottlQueue;
use Tarantool\Queue\LimfifottlQueue;
use Tarantool\Queue\Queue;
use Tarantool\Queue\Subqueue;
use Tarantool\Queue\SubqueuettlQueue;
use Tarantool\Queue\Task;
use Tarantool\Queue\UtubeQueue;
use Tarantool\Queue\UtubettlQueue;

final class TypedQueueTest extends TestCase
{
    use TestDoubleClient;

    public function testFactoriesReturnDriverSpecificQueueTypes() : void
    {
        $client = $this->createDummyClient();

        self::assertInstanceOf(FifoQueue::class, Queue::fifo($client, 'fifo'));
        self::assertInstanceOf(FifottlQueue::class, Queue::fifottl($client, 'fifottl'));
        self::assertInstanceOf(UtubeQueue::class, Queue::utube($client, 'utube'));
        self::assertInstanceOf(UtubettlQueue::class, Queue::utubettl($client, 'utubettl'));
        self::assertInstanceOf(LimfifottlQueue::class, Queue::limfifottl($client, 'limfifottl'));
        self::assertInstanceOf(SubqueuettlQueue::class, Queue::subqueuettl($client, 'subqueuettl'));
    }

    public function testFifottlPutSendsNamedOptions() : void
    {
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(new CallRequest('queue.tube.emails:put', ['payload', [
                'pri' => 2,
                'ttl' => 60.0,
                'ttr' => 10.0,
                'delay' => 5.0,
            ]]))
            ->willReceive(TestDoubleFactory::createResponseFromData([[7, 'r', 'payload']]))
            ->build();

        $task = Queue::fifottl($client, 'emails')->put(
            'payload',
            pri: 2,
            ttl: 60.0,
            ttr: 10.0,
            delay: 5.0,
        );

        self::assertSame(7, $task->getId());
        self::assertSame('payload', $task->getData());
    }

    public function testLimfifottlPutReturnsNullWhenQueueIsFull() : void
    {
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(new CallRequest('queue.tube.bounded:put', ['payload', ['timeout' => 0.0]]))
            ->willReceive(TestDoubleFactory::createEmptyResponse())
            ->build();

        self::assertNull(Queue::limfifottl($client, 'bounded')->put('payload', timeout: 0.0));
    }

    public function testGenericQueuePutCanReturnNull() : void
    {
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(new CallRequest('queue.tube.bounded:put', ['payload', ['timeout' => 0.0]]))
            ->willReceive(TestDoubleFactory::createEmptyResponse())
            ->build();

        self::assertNull((new Queue($client, 'bounded'))->put('payload', ['timeout' => 0.0]));
    }

    public function testSubqueueBindsContextToOperations() : void
    {
        $taskTuple = [[7, 'r', 'payload']];
        $response = TestDoubleFactory::createResponseFromData($taskTuple);
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(
                new CallRequest('queue.tube.jobs:put', ['payload', ['subqueue' => 'emails', 'ttl' => 60.0]]),
                new CallRequest('queue.tube.jobs:take', [2.0, ['subqueue' => 'emails']]),
                new CallRequest('queue.tube.jobs:ack', [7, ['subqueue' => 'emails']]),
            )
            ->willReceive($response, $response, $response)
            ->build();

        $subqueue = Queue::subqueuettl($client, 'jobs')->subqueue('emails');

        self::assertInstanceOf(Subqueue::class, $subqueue);
        self::assertInstanceOf(Task::class, $subqueue->put('payload', ttl: 60.0));
        self::assertInstanceOf(Task::class, $subqueue->take(timeout: 2.0));
        self::assertInstanceOf(Task::class, $subqueue->ack(7));
    }
}
