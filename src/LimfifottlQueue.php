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

final class LimfifottlQueue extends AbstractQueue
{
    public function put(
        mixed $data,
        ?int $pri = null,
        ?float $ttl = null,
        ?float $ttr = null,
        ?float $delay = null,
        ?float $timeout = null,
    ) : ?Task {
        $options = array_filter([
            'pri' => $pri,
            'ttl' => $ttl,
            'ttr' => $ttr,
            'delay' => $delay,
            'timeout' => $timeout,
        ], static fn ($value) => null !== $value);
        $result = $this->call('put', $data, $options);

        return empty($result[0]) ? null : Task::fromTuple($result[0]);
    }

    public function release(int $taskId, ?float $delay = null) : Task
    {
        $options = null === $delay ? [] : ['delay' => $delay];

        return Task::fromTuple($this->call('release', $taskId, $options)[0]);
    }
}
