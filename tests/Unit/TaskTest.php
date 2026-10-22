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

namespace Tarantool\Queue\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tarantool\Queue\States;
use Tarantool\Queue\Task;

final class TaskTest extends TestCase
{
    #[DataProvider('provideTuples')]
    public function testCreateFromTuple(array $tuple) : void
    {
        $task = Task::fromTuple($tuple);

        self::assertSame($tuple[0], $task->getId());
        self::assertSame($tuple[1], $task->getState());

        if (3 === \count($tuple)) {
            self::assertSame($tuple[2], $task->getData());
        }
    }

    public static function provideTuples() : array
    {
        return [
            [[0, States::READY, [42]]],
            [[1, States::DONE, null]],
            [[2, States::BURIED]],
        ];
    }

    #[DataProvider('provideStates')]
    public function testIsser(string $state) : void
    {
        static $map = [
            States::READY => 'isReady',
            States::TAKEN => 'isTaken',
            States::DONE => 'isDone',
            States::BURIED => 'isBuried',
            States::DELAYED => 'isDelayed',
        ];

        $task = Task::fromTuple([0, $state, null]);

        self::assertTrue($task->{$map[$state]}());

        $issers = $map;
        unset($issers[$state]);

        foreach ($issers as $isser) {
            self::assertFalse($task->$isser());
        }
    }

    public static function provideStates() : array
    {
        return [
            [States::READY],
            [States::TAKEN],
            [States::DONE],
            [States::BURIED],
            [States::DELAYED],
        ];
    }
}
