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

namespace Tarantool\Queue\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tarantool\Queue\Queue;

final class QueueTest extends TestCase
{
    /**
     * @dataProvider provideConstructorInvalidArgumentData
     */
    public function testConstructorThrowsInvalidArgumentException($invalidClient, string $type) : void
    {
        try {
            new Queue($invalidClient, 'foobar');
        } catch (\InvalidArgumentException $e) {
            self::assertContains('__construct() expects parameter 1 to be ', $e->getMessage());
            self::assertStringEndsWith(", $type given.", $e->getMessage());

            return;
        }

        $this->fail();
    }

    public function provideConstructorInvalidArgumentData() : iterable
    {
        return [
            [new \stdClass(), 'stdClass'],
            [[], 'array'],
        ];
    }
}
