<?php

namespace Tarantool\Queue;

final class Task
{
    private $id;
    private $type;
    private $data;

    private function __construct($id, $type, $data = null)
    {
        $this->id = $id;
        $this->type = $type;
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

    public function getType()
    {
        return $this->type;
    }

    public function getData()
    {
        return $this->data;
    }

    public function isReady()
    {
        return States::READY === $this->type;
    }

    public function isTaken()
    {
        return States::TAKEN === $this->type;
    }

    public function isDone()
    {
        return States::DONE === $this->type;
    }

    public function isBuried()
    {
        return States::BURIED === $this->type;
    }

    public function isDelayed()
    {
        return States::DELAYED === $this->type;
    }
}
