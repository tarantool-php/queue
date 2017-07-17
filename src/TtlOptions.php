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

interface TtlOptions
{
    const PRI = 'pri';
    const TTL = 'ttl';
    const TTR = 'ttr';
    const DELAY = 'delay';
}
