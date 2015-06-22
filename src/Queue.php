<?php

namespace Tarantool\Queue;

class Queue
{
    private $client;
    private $prefix;

    public function __construct(\Tarantool $client, $tubeName)
    {
        $this->client = $client;
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
        $result = $this->client->call($this->prefix.'put', [$data, (array) $options]);

        return Task::createFromTuple($result[0]);
    }

    /**
     * @param int|null $timeout
     *
     * @return Task|null
     */
    public function take($timeout = null)
    {
        $options = null === $timeout ? [] : [(float) $timeout];
        $result = $this->client->call($this->prefix.'take', $options);

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
        $result = $this->client->call($this->prefix.'release', [$taskId, (array) $options]);

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
}
