<?php

/*
 * This file is part of the Tarantool Queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Tarantool\Client\Client;

class Tarantool
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function call($functionName, array $args = [])
    {
        $result = $this->client->call($functionName, $args);

        return $result->getData();
    }

    public function disconnect()
    {
        $this->client->getConnection()->close();
    }
}
