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

namespace Tarantool\Queue\Tests\Integration;

use Tarantool\PhpUnit\Attribute\Lua;
use Tarantool\Queue\Queue;
use Tarantool\Queue\States;

final class LimfifottlQueueTest extends TestCase
{
    #[Lua("create_tube('%tube_name%', '%tube_type%', {capacity = 1})")]
    public function testPutReturnsNullWhenCapacityIsReached() : void
    {
        $queue = Queue::limfifottl($this->getClient(), $this->getQueueName());

        self::assertTask($queue->put('first'), 0, States::READY, 'first');
        self::assertNull($queue->put('second', timeout: 0.0));
    }
}
