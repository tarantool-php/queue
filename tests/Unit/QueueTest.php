<?php

namespace Tarantool\Queue\Tests\Unit;

use Tarantool\Queue\Queue;
use Tarantool\Queue\Task;

class QueueTest extends \PHPUnit_Framework_TestCase
{
    private $client;
    private $queue;

    protected function setUp()
    {
        $this->client = $this->getMock('Tarantool');
        $this->queue = new Queue($this->client, 'foo');
    }

    /**
     * @dataProvider provideCallData
     */
    public function testMethod($functionName, array $args, array $tuple, $result)
    {
        $this->client->expects($this->once())->method('call')
            ->with("queue.tube.foo:$functionName", $args)
            ->will($this->returnValue([$tuple]));

        $actualResult = call_user_func_array([$this->queue, $functionName], $args);

        is_object($result)
            ? $this->assertEquals($result, $actualResult)
            : $this->assertSame($result, $actualResult);
    }

    public function provideCallData()
    {
        $tuple = [1, 'x', 42];
        $task = Task::createFromTuple($tuple);

        return [
            ['put', [42], $tuple, $task],
            ['put', [42, ['delay' => 2]], $tuple, $task],
            ['take', [], $tuple, $task],
            ['take', [.1], $tuple, $task],
            ['ack', [1], $tuple, $task],
            ['release', [1], $tuple, $task],
            ['release', [1, ['delay' => 2]], $tuple, $task],
            ['peek', [1], $tuple, $task],
            ['bury', [1], $tuple, $task],
            ['kick', [5], [5], 5],
            ['delete', [1], $tuple, $task],
        ];
    }
}
