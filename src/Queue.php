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

    public static function fifoTtl(Client $client, string $name) : FifottlQueue
    {
        return new FifottlQueue($client, $name);
    }

    public static function utube(Client $client, string $name) : UtubeQueue
    {
        return new UtubeQueue($client, $name);
    }

    public static function utubeTtl(Client $client, string $name) : UtubettlQueue
    {
        return new UtubettlQueue($client, $name);
    }

    public static function limFifoTtl(Client $client, string $name) : LimfifottlQueue
    {
        return new LimfifottlQueue($client, $name);
    }

    public static function subqueueTtl(Client $client, string $name) : SubqueuettlQueue
    {
        return new SubqueuettlQueue($client, $name);
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
