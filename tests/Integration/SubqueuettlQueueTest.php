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

final class SubqueuettlQueueTest extends TestCase
{
    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    public function testTakeFromNewSubqueueReturnsNull() : void
    {
        $subqueue = Queue::subqueuettl($this->getClient(), $this->getQueueName())
            ->subqueue('created-on-first-put');

        self::assertNull($subqueue->take(0.0));
    }

    #[Lua("tube = create_tube('%tube_name%', '%tube_type%')")]
    public function testSubqueueRoutesOperations() : void
    {
        $subqueue = Queue::subqueuettl($this->getClient(), $this->getQueueName())
            ->subqueue('emails');

        self::assertTask($subqueue->put('typed', ttl: 60.0), 0, States::READY, 'typed');
        self::assertTask($subqueue->take(0.0), 0, States::TAKEN, 'typed');
        self::assertTask($subqueue->ack(0), 0, States::DONE, 'typed');
    }
}
