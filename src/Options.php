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

final class Options
{
    public const DELAY = 'delay';
    public const PRI = 'pri';
    public const TTL = 'ttl';
    public const TTR = 'ttr';
    public const UTUBE = 'utube';
}
