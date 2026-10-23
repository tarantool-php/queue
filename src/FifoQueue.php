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

namespace Tarantool\Queue;

final class FifoQueue extends AbstractQueue
{
    public function put(mixed $data) : Task
    {
        return Task::fromTuple($this->call('put', $data)[0]);
    }

    public function release(int $taskId) : Task
    {
        return Task::fromTuple($this->call('release', $taskId)[0]);
    }
}
