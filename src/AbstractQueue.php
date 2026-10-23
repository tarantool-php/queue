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
    /** @var array<string, mixed> */
    protected array $context = [];

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
        $args = [$timeout];
        if ([] !== $this->context) {
            $args[] = $this->context;
        }

        $result = $this->call('take', ...$args);

        return empty($result[0]) ? null : Task::fromTuple($result[0]);
    }

    public function touch(int $taskId, float $increment) : ?Task
    {
        $args = [$taskId, $increment];
        if ([] !== $this->context) {
            $args[] = $this->context;
        }

        $result = $this->call('touch', ...$args);

        return empty($result[0]) ? null : Task::fromTuple($result[0]);
    }

    public function ack(int $taskId) : Task
    {
        return Task::fromTuple($this->callWithContext('ack', $taskId)[0]);
    }

    public function peek(int $taskId) : Task
    {
        return Task::fromTuple($this->callWithContext('peek', $taskId)[0]);
    }

    public function bury(int $taskId) : Task
    {
        return Task::fromTuple($this->callWithContext('bury', $taskId)[0]);
    }

    public function kick(int $count) : int
    {
        return $this->callWithContext('kick', $count)[0];
    }

    public function delete(int $taskId) : Task
    {
        return Task::fromTuple($this->callWithContext('delete', $taskId)[0]);
    }

    public function truncate() : void
    {
        $args = [];
        if ([] !== $this->context) {
            $args[] = $this->context;
        }
        $this->call('truncate', ...$args);
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

    private function callWithContext(string $methodName, mixed ...$args) : array
    {
        if ([] !== $this->context) {
            $args[] = $this->context;
        }

        return $this->call($methodName, ...$args);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function withContext(array $options) : array
    {
        return $this->context + $options;
    }
}
