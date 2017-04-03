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

class QueueTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideConstructorInvalidArgumentData
     */
    public function testConstructorThrowsInvalidArgumentException($invalidClient, $type)
    {
        try {
            new Queue($invalidClient, 'foobar');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
            $this->assertSame(
                "Tarantool\\Queue\\Queue::__construct() expects parameter 1 to be Tarantool or Tarantool\\Client\\Client, $type given.",
                $e->getMessage()
            );

            return;
        }

        $this->fail();
    }

    public function provideConstructorInvalidArgumentData()
    {
        return [
            [new \stdClass(), 'stdClass'],
            [[], 'array'],
        ];
    }

    public function testGetName()
    {
        $client = $this->getMockBuilder('Tarantool\Client\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $queueName = uniqid('queue_', true);
        $queue = new Queue($client, $queueName);

        $this->assertSame($queueName, $queue->getName());
    }
}
