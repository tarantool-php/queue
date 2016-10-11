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

class PeclHandler
{
    private $client;

    public function __construct(\Tarantool $client)
    {
        $this->client = $client;
    }

    public function __invoke($func, array $args = null)
    {
        $result = $this->client->call($func, $args);

        return empty($result[0]) ? null : $result[0];
    }
}
