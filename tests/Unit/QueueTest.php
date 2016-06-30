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
    const QUEUE_NAME = 'foo';

    /**
     * @var \Tarantool|\PHPUnit_Framework_MockObject_MockObject
     */
    private $client;

    /**
     * @var Queue
     */
    private $queue;

    private static $stats = [
        'tasks' => [
            'taken' => 1,
            'buried' => 2,
            'ready' => 3,
            'done' => 4,
            'delayed' => 5,
            'total' => 15,
        ],
        'calls' => [
            'ack' => 1,
            'delete' => 2,
            'take' => 3,
            'kick' => 4,
            'release' => 5,
            'put' => 6,
            'bury' => 7,
        ],
    ];

    protected function setUp()
    {
        $this->client = $this->getMockBuilder('Tarantool')->setMethods(['call'])->getMock();
        $this->queue = new Queue($this->client, self::QUEUE_NAME);
    }

    /**
     * @dataProvider provideApiMethodData
     */
    public function testApiMethod($functionName, array $args, array $returnValue, $expectedResult)
    {
        $this->client->expects($this->once())->method('call')
            ->with('queue.tube.'.self::QUEUE_NAME.':'.$functionName, $args)
            ->willReturn($returnValue);

        $actualResult = call_user_func_array([$this->queue, $functionName], $args);

        is_object($expectedResult)
            ? $this->assertEquals($expectedResult, $actualResult)
            : $this->assertSame($expectedResult, $actualResult);
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

    public function testTruncate()
    {
        $this->client->expects($this->once())->method('call')
            ->with('queue.tube.'.self::QUEUE_NAME.':truncate');

        $this->queue->truncate();
    }

    /**
     * @dataProvider provideStatisticsData
     */
    public function testStatistics(array $stats, $expectedResult, $path = null)
    {
        $this->client->expects($this->once())->method('call')
            ->with('queue.statistics')
            ->willReturn([[$stats]]);

        $actualResult = 3 === func_num_args()
            ? $this->queue->statistics($path)
            : $this->queue->statistics();

        $this->assertSame($expectedResult, $actualResult);
    }

    public function provideStatisticsData()
    {
        return [
            [self::$stats, self::$stats],
            [self::$stats, self::$stats['tasks'], 'tasks'],
            [self::$stats, self::$stats['tasks']['taken'], 'tasks.taken'],
            [self::$stats, self::$stats['tasks']['buried'], 'tasks.buried'],
            [self::$stats, self::$stats['tasks']['ready'], 'tasks.ready'],
            [self::$stats, self::$stats['tasks']['done'], 'tasks.done'],
            [self::$stats, self::$stats['tasks']['delayed'], 'tasks.delayed'],
            [self::$stats, self::$stats['tasks']['total'], 'tasks.total'],
            [self::$stats, self::$stats['calls'], 'calls'],
            [self::$stats, self::$stats['calls']['ack'], 'calls.ack'],
            [self::$stats, self::$stats['calls']['delete'], 'calls.delete'],
            [self::$stats, self::$stats['calls']['take'], 'calls.take'],
            [self::$stats, self::$stats['calls']['kick'], 'calls.kick'],
            [self::$stats, self::$stats['calls']['release'], 'calls.release'],
            [self::$stats, self::$stats['calls']['put'], 'calls.put'],
            [self::$stats, self::$stats['calls']['bury'], 'calls.bury'],
        ];
    }

    /**
     * @dataProvider provideStatisticsInvalidPath
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /^Invalid path ".*?"\.$/
     */
    public function testStatisticsInvalidPath($path)
    {
        $this->client->expects($this->once())->method('call')
            ->with('queue.statistics')
            ->willReturn([[self::$stats]]);

        $this->queue->statistics($path);
    }

    public function provideStatisticsInvalidPath()
    {
        return [
            [''],
            ['.'],
            ['foo'],
            ['tasks.foo'],
            ['.tasks'],
            ['tasks.'],
            ['calls.foo'],
            ['.calls'],
            ['calls.'],
        ];
    }
}
