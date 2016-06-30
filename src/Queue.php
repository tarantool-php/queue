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

class Queue
{
    private $client;
    private $tubeName;
    private $prefix;

    public function __construct(\Tarantool $client, $tubeName)
    {
        $this->client = $client;
        $this->tubeName = $tubeName;
        $this->prefix = "queue.tube.$tubeName:";
    }

    /**
     * @param mixed      $data
     * @param array|null $options
     *
     * @return Task
     */
    public function put($data, array $options = null)
    {
        $args = $options ? [$data, $options] : [$data];
        $result = $this->client->call($this->prefix.'put', $args);

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
        $result = $this->client->call($this->prefix.'take', $args);

        return empty($result[0]) ? null : Task::createFromTuple($result[0]);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function ack($taskId)
    {
        $result = $this->client->call($this->prefix.'ack', [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int        $taskId
     * @param array|null $options
     *
     * @return Task
     */
    public function release($taskId, array $options = null)
    {
        $args = $options ? [$taskId, $options] : [$taskId];
        $result = $this->client->call($this->prefix.'release', $args);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function peek($taskId)
    {
        $result = $this->client->call($this->prefix.'peek', [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function bury($taskId)
    {
        $result = $this->client->call($this->prefix.'bury', [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int $count
     *
     * @return int
     */
    public function kick($count)
    {
        $result = $this->client->call($this->prefix.'kick', [$count]);

        return $result[0][0];
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function delete($taskId)
    {
        $result = $this->client->call($this->prefix.'delete', [$taskId]);

        return Task::createFromTuple($result[0]);
    }

    public function truncate()
    {
        $this->client->call($this->prefix.'truncate');
    }

    /**
     * @param string|null $path
     *
     * @return array|int
     *
     * @throws \InvalidArgumentException
     */
    public function statistics($path = null)
    {
        $result = $this->client->call('queue.statistics', [$this->tubeName]);

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
