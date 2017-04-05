<?php

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

class Queue
{
    private $client;
    private $name;

    /**
     * @param \Tarantool|\Tarantool\Client\Client $client
     * @param string $name
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($client, $name)
    {
        if ($client instanceof Client) {
            $client = new ClientAdapter($client);
        } else if (!$client instanceof \Tarantool) {
            throw new \InvalidArgumentException(sprintf(
                '%s() expects parameter 1 to be Tarantool or Tarantool\Client\Client, %s given.',
                __METHOD__, is_object($client) ? get_class($client) : gettype($client)
            ));
        }

        $this->client = $client;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $data
     * @param array $options
     *
     * @return Task
     */
    public function put($data, array $options = [])
    {
        $args = $options ? [$data, $options] : [$data];
        $result = $this->client->call("queue.tube.$this->name:put", $args);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int|float|null $timeout
     *
     * @return Task|null
     */
    public function take($timeout = null)
    {
        $args = null === $timeout ? [] : [$timeout];
        $result = $this->client->call("queue.tube.$this->name:take", $args);

        return empty($result[0]) ? null : Task::createFromTuple($result[0]);
    }

    /**
     * @param int $taskId
     * @param int|float $increment
     *
     * @return Task|null
     */
    public function touch($taskId, $increment)
    {
        $result = $this->client->call("queue.tube.$this->name:touch", [$taskId, $increment]);

        return empty($result[0]) ? null : Task::createFromTuple($result[0]);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function ack($taskId)
    {
        $result = $this->client->call("queue.tube.$this->name:ack", [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int $taskId
     * @param array $options
     *
     * @return Task
     */
    public function release($taskId, array $options = [])
    {
        $args = $options ? [$taskId, $options] : [$taskId];
        $result = $this->client->call("queue.tube.$this->name:release", $args);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function peek($taskId)
    {
        $result = $this->client->call("queue.tube.$this->name:peek", [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function bury($taskId)
    {
        $result = $this->client->call("queue.tube.$this->name:bury", [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int $count
     *
     * @return int
     */
    public function kick($count)
    {
        $result = $this->client->call("queue.tube.$this->name:kick", [$count]);

        return $result[0][0];
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function delete($taskId)
    {
        $result = $this->client->call("queue.tube.$this->name:delete", [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    public function truncate()
    {
        $this->client->call("queue.tube.$this->name:truncate");
    }

    /**
     * @param string|null $path
     *
     * @return array|int
     *
     * @throws \InvalidArgumentException
     */
    public function stats($path = null)
    {
        $result = $this->client->call('queue.stats', [$this->name]);

        if (null === $path) {
            return $result[0][0];
        }

        $result = $result[0][0];
        foreach (explode('.', $path) as $key) {
            if (!isset($result[$key])) {
                throw new \InvalidArgumentException(sprintf('Invalid path "%s".', $path));
            }
            $result = $result[$key];
        }

        return $result;
    }
}
