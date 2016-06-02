<?php

/*
 * This file is part of the Tarantool Queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tarantool\Queue\Tests\Unit;

use Tarantool\Queue\Queue;
use Tarantool\Queue\Task;

class QueueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Tarantool|\PHPUnit_Framework_MockObject_MockObject
     */
    private $client;

    /**
     * @var Queue
     */
    private $queue;

    protected function setUp()
    {
        $this->client = $this->getMock('Tarantool', ['call']);
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
    public function testStatistics(array $returnValue, $result, $path = null)
    {
        $this->client->expects($this->once())->method('call')
            ->with('queue.statistics')
            ->willReturn([$returnValue]);

        $actualResult = 3 === func_num_args()
            ? $this->queue->statistics($path)
            : $this->queue->statistics();

        $this->assertSame($result, $actualResult);
    }

    public function provideStatisticsData()
    {
        $stats = ['tasks' => ['ready' => 1, 'done' => 0], 'calls' => ['put' => 3]];

        return [
            [[$stats], $stats],
            [[$stats], $stats['tasks'], 'tasks'],
            [[$stats], $stats['tasks']['ready'], 'tasks.ready'],
            [[$stats], $stats['tasks']['done'], 'tasks.done'],
            [[$stats], $stats['calls'], 'calls'],
            [[$stats], $stats['calls']['put'], 'calls.put'],
            [[], null],
            [[$stats], null, ''],
            [[$stats], null, 'foo'],
            [[$stats], null, 'tasks.foo'],
            [[$stats], null, '.tasks'],
            [[$stats], null, 'tasks.'],
        ];
    }
}
