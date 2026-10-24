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

final class FifottlQueueTest extends QueueTest
{
    use Ttl;

    #[Lua("create_tube('%tube_name%', '%tube_type%')")]
    public function testTypedQueuePut() : void
    {
        $queue = Queue::fifoTtl($this->getClient(), $this->getQueueName());
        $task = $queue->put('typed', ttl: 60.0, pri: 0);

        self::assertTask($task, 0, States::READY, 'typed');
    }
}
