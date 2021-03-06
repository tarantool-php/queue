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

final class Options
{
    public const DELAY = 'delay';
    public const PRI = 'pri';
    public const TTL = 'ttl';
    public const TTR = 'ttr';
    public const UTUBE = 'utube';

    private function __construct()
    {
    }
}
