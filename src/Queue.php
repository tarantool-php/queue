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

class Queue extends AbstractQueue
{
    public static function fifo(Client $client, string $name) : FifoQueue
    {
        return new FifoQueue($client, $name);
    }

    public static function fifoTtl(Client $client, string $name) : FifoTtlQueue
    {
        return new FifoTtlQueue($client, $name);
    }

    public static function utube(Client $client, string $name) : UtubeQueue
    {
        return new UtubeQueue($client, $name);
    }

    public static function utubeTtl(Client $client, string $name) : UtubeTtlQueue
    {
        return new UtubeTtlQueue($client, $name);
    }

    public static function limFifoTtl(Client $client, string $name) : LimFifoTtlQueue
    {
        return new LimFifoTtlQueue($client, $name);
    }

    public static function subqueueTtl(Client $client, string $name) : SubqueueTtlQueue
    {
        return new SubqueueTtlQueue($client, $name);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function put(mixed $data, array $options = []) : ?Task
    {
        $result = $this->call('put', $data, $options);

        return empty($result[0]) ? null : Task::fromTuple($result[0]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function release(int $taskId, array $options = []) : Task
    {
        return Task::fromTuple($this->call('release', $taskId, $options)[0]);
    }
}
