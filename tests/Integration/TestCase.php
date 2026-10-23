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

use PHPUnit\Framework\Attributes\Before;
use PHPUnitExtras\Attribute\AttributeProcessorBuilder;
use Tarantool\Client\Client;
use Tarantool\PhpUnit\TestCase as BaseTestCase;
use Tarantool\Queue\Queue;
use Tarantool\Queue\Task;

abstract class TestCase extends BaseTestCase
{
    private ?Client $client = null;

    protected Queue $queue;

    public function getQueueName() : string
    {
        $methodName = $this->name();
        if (str_starts_with($methodName, 'test')) {
            $methodName = substr($methodName, 4);
        }

        return sprintf('t_%s_%s', $this->getQueueType(), strtolower($methodName));
    }

    public function getQueueType() : string
    {
        $class = new \ReflectionClass($this);
        $type = str_replace('QueueTest', '', $class->getShortName());

        return strtolower($type);
    }

    final protected function getClient() : Client
    {
        if ($this->client) {
            return $this->client;
        }

        if (false === $uri = getenv('TNT_LISTEN_URI')) {
            return $this->client = Client::fromDefaults();
        }

        if (str_starts_with($uri, '/')) {
            $dsn = 'unix://'.$uri;
        } elseif (str_starts_with($uri, 'unix/:')) {
            $dsn = 'unix://'.substr($uri, 6);
        } elseif (ctype_digit($uri)) {
            $dsn = 'tcp://127.0.0.1:'.$uri;
        } else {
            $dsn = 'tcp://'.$uri;
        }

        return $this->client = Client::fromDsn($dsn);
    }

    final protected function createAttributeProcessorBuilder() : AttributeProcessorBuilder
    {
        return parent::createAttributeProcessorBuilder()
            ->addPlaceholderResolver(new TubePlaceholderResolver($this));
    }

    #[Before]
    final protected function initQueue() : void
    {
        $this->queue = new Queue($this->getClient(), $this->getQueueName());
    }

    final protected function getQueue() : Queue
    {
        return $this->queue;
    }

    final protected static function assertTaskInstance(mixed $task) : void
    {
        self::assertInstanceOf(Task::class, $task);
    }

    final protected static function assertTask(mixed $task, int $expectedId, string $expectedState, mixed $expectedData) : void
    {
        self::assertTaskInstance($task);
        self::assertSame($expectedId, $task->getId());
        self::assertSame($expectedState, $task->getState());
        self::assertSame($expectedData, $task->getData());
    }
}
