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

use Tarantool\Client\Client;

final class SubqueuettlQueue
{
    public function __construct(
        private readonly Client $client,
        private readonly string $name,
    ) {
    }

    public function subqueue(string $name) : Subqueue
    {
        return new Subqueue($this->client, $this->name, $name);
    }
}
