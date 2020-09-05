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

use PHPUnitExtras\Annotation\AnnotationProcessorBuilder;
use Tarantool\Client\Client;
use Tarantool\PhpUnit\TestCase as BaseTestCase;
use Tarantool\Queue\Queue;
use Tarantool\Queue\Task;
use Tarantool\Queue\Tests\PhpUnitCompat;

abstract class TestCase extends BaseTestCase
{
    use PhpUnitCompat;

    /** @var Client|null */
    private $client;

    /** @var Queue */
    protected $queue;

    public function getQueueName() : string
    {
        $methodName = $this->getName(false);
        if (0 === strpos($methodName, 'test')) {
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

        if (0 === strpos($uri, '/')) {
            $dsn = 'unix://'.$uri;
        } elseif (0 === strpos($uri, 'unix/:')) {
            $dsn = 'unix://'.substr($uri, 6);
        } elseif (ctype_digit($uri)) {
            $dsn = 'tcp://127.0.0.1:'.$uri;
        } else {
            $dsn = 'tcp://'.$uri;
        }

        return $this->client = Client::fromDsn($dsn);
    }

    final protected function createAnnotationProcessorBuilder() : AnnotationProcessorBuilder
    {
        return parent::createAnnotationProcessorBuilder()
            ->addPlaceholderResolver(new TubePlaceholderResolver($this));
    }

    /**
     * @before
     */
    final protected function initQueue() : void
    {
        $this->queue = new Queue($this->getClient(), $this->getQueueName());
    }

    final protected function getQueue() : Queue
    {
        return $this->queue;
    }

    final protected static function assertTaskInstance($task) : void
    {
        self::assertInstanceOf(Task::class, $task);
    }

    final protected static function assertTask($task, int $expectedId, string $expectedState, $expectedData) : void
    {
        self::assertTaskInstance($task);
        self::assertSame($expectedId, $task->getId());
        self::assertSame($expectedState, $task->getState());
        self::assertSame($expectedData, $task->getData());
    }
}
