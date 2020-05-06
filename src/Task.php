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

final class Task
{
    private $id;
    private $state;
    private $data;

    private function __construct(int $id, string $state, $data)
    {
        $this->id = $id;
        $this->state = $state;
        $this->data = $data;
    }

    public static function fromTuple(array $tuple) : self
    {
        [$id, $state, $data] = $tuple + [2 => null];

        return new self($id, $state, $data);
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getState() : string
    {
        return $this->state;
    }

    public function getData()
    {
        return $this->data;
    }

    public function isReady() : bool
    {
        return States::READY === $this->state;
    }

    public function isTaken() : bool
    {
        return States::TAKEN === $this->state;
    }

    public function isDone() : bool
    {
        return States::DONE === $this->state;
    }

    public function isBuried() : bool
    {
        return States::BURIED === $this->state;
    }

    public function isDelayed() : bool
    {
        return States::DELAYED === $this->state;
    }
}
