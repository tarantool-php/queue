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

namespace Tarantool\Queue\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tarantool\PhpUnit\Client\TestDoubleClient;
use Tarantool\Queue\Queue;

final class QueueTest extends TestCase
{
    use TestDoubleClient;

    public function testGetName() : void
    {
        $queueName = 'foobar';
        $queue = new Queue($this->createDummyClient(), $queueName);

        self::assertSame($queueName, $queue->getName());
    }

    public function testGetClient() : void
    {
        $client = $this->createDummyClient();
        $queue = new Queue($client, 'foobar');

        self::assertSame($client, $queue->getClient());
    }
}
