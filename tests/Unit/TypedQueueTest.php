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
        self::assertInstanceOf(FifottlQueue::class, Queue::fifoTtl($client, 'fifottl'));
        self::assertInstanceOf(UtubeQueue::class, Queue::utube($client, 'utube'));
        self::assertInstanceOf(UtubettlQueue::class, Queue::utubeTtl($client, 'utubettl'));
        self::assertInstanceOf(LimfifottlQueue::class, Queue::limFifoTtl($client, 'limfifottl'));
        self::assertInstanceOf(SubqueuettlQueue::class, Queue::subqueueTtl($client, 'subqueuettl'));
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

        $task = Queue::fifoTtl($client, 'emails')->put(
            'payload',
            pri: 2,
            ttl: 60.0,
            ttr: 10.0,
            delay: 5.0,
        );

        self::assertSame(7, $task->getId());
        self::assertSame('payload', $task->getData());
    }

    public function testFifottlReleaseSendsOptionalDelay() : void
    {
        $taskResponse = TestDoubleFactory::createResponseFromData([[1, 'r', 'payload']]);
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(
                new CallRequest('queue.tube.emails:release', [1, ['delay' => 5.0]]),
                new CallRequest('queue.tube.emails:release', [2, []]),
            )
            ->willReceive($taskResponse, $taskResponse)
            ->build();
        $queue = Queue::fifoTtl($client, 'emails');

        self::assertInstanceOf(Task::class, $queue->release(1, delay: 5.0));
        self::assertInstanceOf(Task::class, $queue->release(2));
    }

    public function testFifoPutAndReleaseUseOptionlessCalls() : void
    {
        $taskResponse = TestDoubleFactory::createResponseFromData([[1, 'r', 'payload']]);
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(
                new CallRequest('queue.tube.events:put', ['payload']),
                new CallRequest('queue.tube.events:release', [1]),
            )
            ->willReceive($taskResponse, $taskResponse)
            ->build();
        $queue = Queue::fifo($client, 'events');

        self::assertInstanceOf(Task::class, $queue->put('payload'));
        self::assertInstanceOf(Task::class, $queue->release(1));
    }

    public function testUtubePutAndReleasePassSubqueueOptions() : void
    {
        $taskResponse = TestDoubleFactory::createResponseFromData([[1, 'r', 'payload']]);
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(
                new CallRequest('queue.tube.events:put', ['payload', ['utube' => 'domain-a']]),
                new CallRequest('queue.tube.events:release', [1, []]),
            )
            ->willReceive($taskResponse, $taskResponse)
            ->build();
        $queue = Queue::utube($client, 'events');

        self::assertInstanceOf(Task::class, $queue->put('payload', 'domain-a'));
        self::assertInstanceOf(Task::class, $queue->release(1));
    }

    public function testUtubettlPutAndReleasePassTypedOptions() : void
    {
        $taskResponse = TestDoubleFactory::createResponseFromData([[1, 'r', 'payload']]);
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(
                new CallRequest('queue.tube.events:put', ['payload', [
                    'utube' => 'domain-a',
                    'pri' => 2,
                    'ttl' => 60.0,
                    'ttr' => 10.0,
                    'delay' => 5.0,
                ]]),
                new CallRequest('queue.tube.events:release', [1, ['delay' => 5.0]]),
            )
            ->willReceive($taskResponse, $taskResponse)
            ->build();
        $queue = Queue::utubeTtl($client, 'events');

        self::assertInstanceOf(Task::class, $queue->put(
            'payload',
            'domain-a',
            pri: 2,
            ttl: 60.0,
            ttr: 10.0,
            delay: 5.0,
        ));
        self::assertInstanceOf(Task::class, $queue->release(1, delay: 5.0));
    }

    public function testLimfifottlPutReturnsNullWhenQueueIsFull() : void
    {
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(new CallRequest('queue.tube.bounded:put', ['payload', ['timeout' => 0.0]]))
            ->willReceive(TestDoubleFactory::createEmptyResponse())
            ->build();

        self::assertNull(Queue::limFifoTtl($client, 'bounded')->put('payload', timeout: 0.0));
    }

    public function testGenericQueuePutCanReturnNull() : void
    {
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(new CallRequest('queue.tube.bounded:put', ['payload', ['timeout' => 0.0]]))
            ->willReceive(TestDoubleFactory::createEmptyResponse())
            ->build();

        self::assertNull((new Queue($client, 'bounded'))->put('payload', ['timeout' => 0.0]));
    }

    public function testLimfifottlReleasePassesOptionalDelay() : void
    {
        $taskResponse = TestDoubleFactory::createResponseFromData([[1, 'r', 'payload']]);
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(
                new CallRequest('queue.tube.bounded:release', [1, ['delay' => 5.0]]),
                new CallRequest('queue.tube.bounded:release', [2, []]),
            )
            ->willReceive($taskResponse, $taskResponse)
            ->build();
        $queue = Queue::limFifoTtl($client, 'bounded');

        self::assertInstanceOf(Task::class, $queue->release(1, delay: 5.0));
        self::assertInstanceOf(Task::class, $queue->release(2));
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
                new CallRequest('queue.tube.jobs:touch', [7, 1.0, ['subqueue' => 'emails']]),
            )
            ->willReceive($response, $response, $response, $response)
            ->build();

        $subqueue = Queue::subqueueTtl($client, 'jobs')->subqueue('emails');

        self::assertInstanceOf(Subqueue::class, $subqueue);
        self::assertInstanceOf(Task::class, $subqueue->put('payload', ttl: 60.0));
        self::assertInstanceOf(Task::class, $subqueue->take(timeout: 2.0));
        self::assertInstanceOf(Task::class, $subqueue->ack(7));
        self::assertInstanceOf(Task::class, $subqueue->touch(7, 1.0));
    }

    public function testSubqueueReleaseKeepsContextAndAddsOptionalDelay() : void
    {
        $taskResponse = TestDoubleFactory::createResponseFromData([[7, 'r', 'payload']]);
        $client = $this->getTestDoubleClientBuilder()
            ->shouldSend(
                new CallRequest('queue.tube.jobs:release', [7, ['subqueue' => 'emails', 'delay' => 5.0]]),
                new CallRequest('queue.tube.jobs:release', [8, ['subqueue' => 'emails']]),
                new CallRequest('queue.tube.jobs:truncate', [['subqueue' => 'emails']]),
            )
            ->willReceive($taskResponse, $taskResponse, TestDoubleFactory::createEmptyResponse())
            ->build();
        $subqueue = Queue::subqueueTtl($client, 'jobs')->subqueue('emails');

        self::assertInstanceOf(Task::class, $subqueue->release(7, delay: 5.0));
        self::assertInstanceOf(Task::class, $subqueue->release(8));
        $subqueue->truncate();
    }
}
