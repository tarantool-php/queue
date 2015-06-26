<?php

namespace Tarantool\Queue;

final class Task
{
    private $id;
    private $state;
    private $data;

    private function __construct($id, $state, $data = null)
    {
        $this->id = $id;
        $this->state = $state;
        $this->data = $data;
    }

    public static function createFromTuple(array $tuple)
    {
        list($id, $state, $data) = $tuple + [2 => null];

        return new self($id, $state, $data);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getData()
    {
        return $this->data;
    }

    public function isReady()
    {
        return States::READY === $this->state;
    }

    public function isTaken()
    {
        return States::TAKEN === $this->state;
    }

    public function isDone()
    {
        return States::DONE === $this->state;
    }

    public function isBuried()
    {
        return States::BURIED === $this->state;
    }

    public function isDelayed()
    {
        return States::DELAYED === $this->state;
    }
}
