<?php

declare(strict_types=1);

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

final class ClientAdapter
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function call(string $funcName, array $args = []) : array
    {
        $data = $this->client->call($funcName, $args)->getData();

        return isset($data[0][0]) ? $data : [$data];
    }
}
