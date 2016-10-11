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
    private $handler;
    private $tubeName;
    private $prefix;

    public function __construct(callable $handler, $tubeName)
    {
        $this->handler = $handler;
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
        $handler = $this->handler;
        $result = $handler($this->prefix.'put', $args);

        return Task::createFromTuple($result);
    }

    /**
     * @param int|float|null $timeout
     *
     * @return Task|null
     */
    public function take($timeout = null)
    {
        $args = null === $timeout ? [] : [$timeout];
        $handler = $this->handler;
        $result = $handler($this->prefix.'take', $args);

        return empty($result) ? null : Task::createFromTuple($result);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function ack($taskId)
    {
        $handler = $this->handler;
        $result = $handler($this->prefix.'ack', [$taskId]);

        return Task::createFromTuple($result);
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
        $handler = $this->handler;
        $result = $handler($this->prefix.'release', $args);

        return Task::createFromTuple($result);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function peek($taskId)
    {
        $handler = $this->handler;
        $result = $handler($this->prefix.'peek', [$taskId]);

        return Task::createFromTuple($result);
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function bury($taskId)
    {
        $handler = $this->handler;
        $result = $handler($this->prefix.'bury', [$taskId]);

        return Task::createFromTuple($result);
    }

    /**
     * @param int $count
     *
     * @return int
     */
    public function kick($count)
    {
        $handler = $this->handler;
        $result = $handler($this->prefix.'kick', [$count]);

        return $result[0];
    }

    /**
     * @param int $taskId
     *
     * @return Task
     */
    public function delete($taskId)
    {
        $handler = $this->handler;
        $result = $handler($this->prefix.'delete', [$taskId]);

        return Task::createFromTuple($result);
    }

    public function truncate()
    {
        $handler = $this->handler;
        $handler($this->prefix.'truncate');
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
        $handler = $this->handler;
        $result = $handler('queue.stats', [$this->tubeName]);

        if (null === $path) {
            return $result[0];
        }

        $result = $result[0];
        foreach (explode('.', $path) as $key) {
            if (!isset($result[$key])) {
                throw new \InvalidArgumentException(sprintf('Invalid path "%s".', $path));
            }
            $result = $result[$key];
        }

        return $result;
    }
}
