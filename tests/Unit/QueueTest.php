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
     * @dataProvider provideApiMethodData
     */
    public function testApiMethod($functionName, array $args, array $returnValue, $result)
    {
        $this->client->expects($this->once())->method('call')
            ->with("queue.tube.foo:$functionName", $args)
            ->will($this->returnValue([$returnValue]));

        $actualResult = call_user_func_array([$this->queue, $functionName], $args);

        is_object($result)
            ? $this->assertEquals($result, $actualResult)
            : $this->assertSame($result, $actualResult);
    }

    public function provideApiMethodData()
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

    /**
     * @dataProvider provideStatisticsData
     */
    public function testStatistics(array $args, array $returnValue, $result)
    {
        $this->client->expects($this->once())->method('call')
            ->with('queue.statistics')
            ->will($this->returnValue([$returnValue]));

        $actualResult = call_user_func_array([$this->queue, 'statistics'], $args);

        $this->assertSame($result, $actualResult);
    }

    public function provideStatisticsData()
    {
        $stats = ['tasks' => ['ready' => 1, 'done' => 0], 'calls' => ['put' => 3]];

        return [
            [[], [$stats], $stats],
            [['tasks'], [$stats], $stats['tasks']],
            [['tasks', 'ready'], [$stats], $stats['tasks']['ready']],
            [['tasks', 'done'], [$stats], $stats['tasks']['done']],
            [['calls'], [$stats], $stats['calls']],
            [['calls', 'put'], [$stats], $stats['calls']['put']],
            [[], [], null],
            [[null], [$stats], null],
            [[null, null], [$stats], null],
            [[''], [$stats], null],
            [['foo'], [$stats], null],
            [['tasks', 'foo'], [$stats], null],
            [[null, 'tasks'], [$stats], null],
            [['tasks', ''], [$stats], null],
        ];
    }
}
