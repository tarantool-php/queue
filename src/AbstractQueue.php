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

namespace Tarantool\Queue;

use Tarantool\Client\Client;

abstract class AbstractQueue
{
    public function __construct(
        private readonly Client $client,
        private readonly string $name,
    ) {
    }

    public function getClient() : Client
    {
        return $this->client;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function take(?float $timeout = null) : ?Task
    {
        $result = $this->call('take', $timeout);

        return empty($result[0]) ? null : Task::fromTuple($result[0]);
    }

    public function touch(int $taskId, float $increment) : ?Task
    {
        $result = $this->call('touch', $taskId, $increment);

        return empty($result[0]) ? null : Task::fromTuple($result[0]);
    }

    public function ack(int $taskId) : Task
    {
        return Task::fromTuple($this->call('ack', $taskId)[0]);
    }

    public function peek(int $taskId) : Task
    {
        return Task::fromTuple($this->call('peek', $taskId)[0]);
    }

    public function bury(int $taskId) : Task
    {
        return Task::fromTuple($this->call('bury', $taskId)[0]);
    }

    public function kick(int $count) : int
    {
        return $this->call('kick', $count)[0];
    }

    public function delete(int $taskId) : Task
    {
        return Task::fromTuple($this->call('delete', $taskId)[0]);
    }

    public function truncate() : void
    {
        $this->call('truncate');
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array|int
     */
    public function stats(?string $path = null)
    {
        [$stats] = $this->client->call('queue.stats', $this->name);

        if (null === $path) {
            return $stats;
        }

        foreach (explode('.', $path) as $key) {
            if (!isset($stats[$key])) {
                throw new \InvalidArgumentException(\sprintf('Invalid path "%s"', $path));
            }
            $stats = $stats[$key];
        }

        return $stats;
    }

    public function call(string $methodName, ...$args) : array
    {
        return $this->client->call("queue.tube.$this->name:$methodName", ...$args);
    }
}
