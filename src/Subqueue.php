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

final class Subqueue extends AbstractQueue
{
    private readonly string $subqueueName;

    public function __construct(Client $client, string $queueName, string $subqueueName)
    {
        parent::__construct($client, $queueName);
        $this->subqueueName = $subqueueName;
    }

    public function put(
        mixed $data,
        ?int $pri = null,
        ?float $ttl = null,
        ?float $ttr = null,
        ?float $delay = null,
    ) : Task {
        $options = ['subqueue' => $this->subqueueName] + array_filter([
            'pri' => $pri,
            'ttl' => $ttl,
            'ttr' => $ttr,
            'delay' => $delay,
        ], static fn ($value) => null !== $value);

        return Task::fromTuple($this->call('put', $data, $options)[0]);
    }

    public function release(int $taskId, ?float $delay = null) : Task
    {
        $options = null === $delay
            ? ['subqueue' => $this->subqueueName]
            : ['subqueue' => $this->subqueueName, 'delay' => $delay];

        return Task::fromTuple($this->call('release', $taskId, $options)[0]);
    }

    #[\Override]
    public function take(?float $timeout = null) : ?Task
    {
        $result = $this->call('take', $timeout, ['subqueue' => $this->subqueueName]);

        return empty($result[0]) ? null : Task::fromTuple($result[0]);
    }

    #[\Override]
    public function touch(int $taskId, float $increment) : ?Task
    {
        $result = $this->call('touch', $taskId, $increment, ['subqueue' => $this->subqueueName]);

        return empty($result[0]) ? null : Task::fromTuple($result[0]);
    }

    #[\Override]
    public function ack(int $taskId) : Task
    {
        return Task::fromTuple($this->call('ack', $taskId, ['subqueue' => $this->subqueueName])[0]);
    }

    #[\Override]
    public function peek(int $taskId) : Task
    {
        return Task::fromTuple($this->call('peek', $taskId, ['subqueue' => $this->subqueueName])[0]);
    }

    #[\Override]
    public function bury(int $taskId) : Task
    {
        return Task::fromTuple($this->call('bury', $taskId, ['subqueue' => $this->subqueueName])[0]);
    }

    #[\Override]
    public function kick(int $count) : int
    {
        return $this->call('kick', $count, ['subqueue' => $this->subqueueName])[0];
    }

    #[\Override]
    public function delete(int $taskId) : Task
    {
        return Task::fromTuple($this->call('delete', $taskId, ['subqueue' => $this->subqueueName])[0]);
    }

    #[\Override]
    public function truncate() : void
    {
        $this->call('truncate', ['subqueue' => $this->subqueueName]);
    }
}
