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

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tarantool\Client\Client;
use Tarantool\PhpUnit\Annotation\Annotations;
use Tarantool\Queue\Queue;
use Tarantool\Queue\Task;
use Tarantool\Queue\Tests\PhpUnitCompat;

abstract class TestCase extends BaseTestCase
{
    use Annotations;
    use PhpUnitCompat;

    /** @var Client|null */
    private $client;

    /** @var Queue */
    protected $queue;

    final protected function getClient() : Client
    {
        if ($this->client) {
            return $this->client;
        }

        if (false === $uri = getenv('TNT_LISTEN_URI')) {
            return $this->client = Client::fromDefaults();
        }

        if (0 === strpos($uri, '/') || 0 === strpos($uri, 'unix/:')) {
            $dsn = 'unix://'.substr($uri, 6);
        } elseif (ctype_digit($uri)) {
            $dsn = "tcp://127.0.0.1:$uri";
        } else {
            $dsn = "tcp://$uri";
        }

        return $this->client = Client::fromDsn($dsn);
    }

    /**
     * @before
     */
    final protected function initQueue() : void
    {
        $queueName = $this->getQueueName();
        $client = $this->getClient();
        $this->queue = new Queue($client, $queueName);

        $client->evaluate(sprintf(
            "tube = create_tube('%s', '%s')",
            $queueName,
            $this->getQueueType()
        ));

        $this->processAnnotations(static::class, $this->getName(false) ?? '');
    }

    /**
     * @after
     */
    final protected function destroyQueue() : void
    {
        $this->getClient()->evaluate('tube = nil');
        $this->queue = null;
    }

    final protected function getQueue() : Queue
    {
        return $this->queue;
    }

    protected function getQueueName() : string
    {
        $testName = preg_replace('/^test(\S+).*$/', '\1', $this->getName());
        $testName = strtolower($testName);

        return sprintf('t_%s_%s', $this->getQueueType(), $testName);
    }

    protected function getQueueType() : string
    {
        $class = new \ReflectionClass($this);
        $type = str_replace('QueueTest', '', $class->getShortName());

        return strtolower($type);
    }

    final protected static function assertTaskInstance($task) : void
    {
        self::assertInstanceOf(Task::class, $task);
    }

    final protected static function assertTask(Task $task, int $expectedId, string $expectedState, $expectedData) : void
    {
        self::assertSame($expectedId, $task->getId());
        self::assertSame($expectedState, $task->getState());
        self::assertSame($expectedData, $task->getData());
    }

    final protected static function assertSameArray(array $expected, array $actual) : void
    {
        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }
}
