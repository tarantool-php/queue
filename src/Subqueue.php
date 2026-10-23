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
    public function __construct(Client $client, string $queueName, string $subqueueName)
    {
        parent::__construct($client, $queueName);
        $this->context = ['subqueue' => $subqueueName];
    }

    public function put(
        mixed $data,
        ?int $pri = null,
        ?float $ttl = null,
        ?float $ttr = null,
        ?float $delay = null,
    ) : Task {
        $options = array_filter([
            'pri' => $pri,
            'ttl' => $ttl,
            'ttr' => $ttr,
            'delay' => $delay,
        ], static fn ($value) => null !== $value);

        return Task::fromTuple($this->call('put', $data, $this->withContext($options))[0]);
    }

    public function release(int $taskId, ?float $delay = null) : Task
    {
        $options = null === $delay ? [] : ['delay' => $delay];

        return Task::fromTuple($this->call('release', $taskId, $this->withContext($options))[0]);
    }
}
