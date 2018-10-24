<?php

declare(strict_types=1);

/*
 * This file is part of the Tarantool Queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tarantool\Queue;

use Tarantool\Client\Client;

final class Queue
{
    private $client;
    private $name;

    /**
     * @param \Tarantool|Client $client
     * @param string $name
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($client, string $name)
    {
        if ($client instanceof Client) {
            $client = new ClientAdapter($client);
        } elseif (!$client instanceof \Tarantool) {
            throw new \InvalidArgumentException(\sprintf(
                '%s() expects parameter 1 to be Tarantool or %s, %s given.',
                __METHOD__, Client::class, \is_object($client) ? \get_class($client) : \gettype($client)
            ));
        }

        $this->client = $client;
        $this->name = $name;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function put($data, array $options = []) : Task
    {
        $args = $options ? [$data, $options] : [$data];
        $result = $this->client->call("queue.tube.$this->name:put", $args);

        return Task::createFromTuple($result[0]);
    }

    public function take(float $timeout = null) : ?Task
    {
        $args = null === $timeout ? [] : [$timeout];
        $result = $this->client->call("queue.tube.$this->name:take", $args);

        return empty($result[0]) ? null : Task::createFromTuple($result[0]);
    }

    public function touch(int $taskId, float $increment) : ?Task
    {
        $result = $this->client->call("queue.tube.$this->name:touch", [$taskId, $increment]);

        return empty($result[0]) ? null : Task::createFromTuple($result[0]);
    }

    public function ack(int $taskId) : Task
    {
        $result = $this->client->call("queue.tube.$this->name:ack", [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    public function release(int $taskId, array $options = []) : Task
    {
        $args = $options ? [$taskId, $options] : [$taskId];
        $result = $this->client->call("queue.tube.$this->name:release", $args);

        return Task::createFromTuple($result[0]);
    }

    public function peek(int $taskId) : Task
    {
        $result = $this->client->call("queue.tube.$this->name:peek", [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    public function bury(int $taskId) : Task
    {
        $result = $this->client->call("queue.tube.$this->name:bury", [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    public function kick(int $count) : int
    {
        $result = $this->client->call("queue.tube.$this->name:kick", [$count]);

        return $result[0][0];
    }

    public function delete(int $taskId) : Task
    {
        $result = $this->client->call("queue.tube.$this->name:delete", [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    public function truncate() : void
    {
        $this->client->call("queue.tube.$this->name:truncate");
    }

    /**
     * @param string|null $path
     *
     * @throws \InvalidArgumentException
     *
     * @return array|int
     */
    public function stats(string $path = null)
    {
        $result = $this->client->call('queue.stats', [$this->name]);

        if (null === $path) {
            return $result[0][0];
        }

        $result = $result[0][0];
        foreach (\explode('.', $path) as $key) {
            if (!isset($result[$key])) {
                throw new \InvalidArgumentException(\sprintf('Invalid path "%s".', $path));
            }
            $result = $result[$key];
        }

        return $result;
    }

    public function call(string $methodName, ...$args) : array
    {
        return $this->client->call("queue.tube.$this->name:$methodName", $args);
    }
}
