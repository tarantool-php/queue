<?php

/*
 * This file is part of the Tarantool Queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tarantool\Queue;

use Tarantool\Client\Client;

class ClientAdapter
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function call($funcName, array $args = [])
    {
        $result = $this->client->call($funcName, $args);

        return 'queue.stats' === $funcName ? [$result->getData()] : $result->getData();
    }
}
