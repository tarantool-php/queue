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

final class States
{
    public const READY = 'r';
    public const TAKEN = 't';
    public const DONE = '-';
    public const BURIED = '!';
    public const DELAYED = '~';
}
