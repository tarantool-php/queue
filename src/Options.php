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

interface Options
{
    const TEMPORARY = 'temporary';
    const IF_NOT_EXISTS = 'if_not_exists';
    const ON_TASK_CHANGE = 'on_task_change';
}
