<?php

namespace Tarantool\Queue\Tests\Unit;

use Tarantool\Queue\Queue;

class QueueTest extends \PHPUnit_Framework_TestCase
{
    private $tarantool;
    private $queue;

    protected function setUp()
    {
        $this->tarantool = $this->getMock('Tarantool');
        $this->queue = new Queue($this->tarantool, 'foo');
    }

    /**
     * @dataProvider provideCallData
     */
    public function testClientCall($functionName, array $args, $returnValue, $hasEmptyOptions = false)
    {
        $this->tarantool->expects($this->once())->method('call')
            ->with("queue.tube.foo:$functionName",
                $hasEmptyOptions ? $this->logicalOr(
                    $this->equalTo($args + [1 => []]),
                    $this->equalTo($args)
                ) : $args
            )
            ->will($this->returnValue([$returnValue]));

        call_user_func_array([$this->queue, $functionName], $args);
    }

    public function provideCallData()
    {
        return [
            ['put', [42], [1, 0], true],
            ['put', [42, ['delay' => 2]], [1, 0]],
            ['take', [1], [1, 0]],
            ['ack', [1], [1, 0]],
            ['release', [1], [1, 0], true],
            ['release', [1, ['delay' => 2]], [1, 0]],
            ['peek', [1], [1, 0]],
            ['bury', [1], [1, 0]],
            ['kick', [5], 5],
            ['delete', [1], [1, 0]],
        ];
    }
}
