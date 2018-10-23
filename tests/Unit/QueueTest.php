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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tarantool\Client\Client;
use Tarantool\Queue\Queue;

final class QueueTest extends TestCase
{
    /**
     * @dataProvider provideConstructorInvalidArgumentData
     */
    public function testConstructorThrowsInvalidArgumentException($invalidClient, string $type) : void
    {
        try {
            new Queue($invalidClient, 'foobar');
        } catch (\InvalidArgumentException $e) {
            self::assertContains('__construct() expects parameter 1 to be ', $e->getMessage());
            self::assertStringEndsWith(", $type given.", $e->getMessage());

            return;
        }

        $this->fail();
    }

    public function provideConstructorInvalidArgumentData() : iterable
    {
        return [
            [new \stdClass(), 'stdClass'],
            [[], 'array'],
        ];
    }

    public function testGetName() : void
    {
        /** @var \Tarantool|Client|MockObject $client */
        $client = class_exists(\Tarantool::class, false)
            ? $this->createMock(\Tarantool::class)
            : $this->createMock(Client::class);

        $queueName = uniqid('queue_', true);
        $queue = new Queue($client, $queueName);

        self::assertSame($queueName, $queue->getName());
    }
}
