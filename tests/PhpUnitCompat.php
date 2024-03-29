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

namespace Tarantool\Queue\Tests;

/**
 * A compatibility layer for the legacy PHPUnit 7.
 */
trait PhpUnitCompat
{
    public function expectExceptionMessageMatches(string $regularExpression) : void
    {
        \is_callable(parent::class.'::expectExceptionMessageMatches')
            ? parent::expectExceptionMessageMatches(...func_get_args())
            : parent::expectExceptionMessageRegExp(...func_get_args());
    }
}
