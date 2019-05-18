<?php

/**
 * This file is part of the Tarantool Queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tarantool\Queue;

final class TarantoolAdapter
{
    private $tarantool;

    public function __construct(\Tarantool $tarantool)
    {
        $this->tarantool = $tarantool;
    }

    public function call(string $funcName, ...$args) : array
    {
        return $this->tarantool->call($funcName, $args);
    }
}
