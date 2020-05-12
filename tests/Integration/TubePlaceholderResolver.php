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

namespace Tarantool\Queue\Tests\Integration;

use PHPUnitExtras\Annotation\PlaceholderResolver\PlaceholderResolver;
use PHPUnitExtras\Annotation\Target;

final class TubePlaceholderResolver implements PlaceholderResolver
{
    private $testCase;

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    public function getName() : string
    {
        return 'tube';
    }

    public function resolve(string $value, Target $target) : string
    {
        return strtr($value, [
            '%tube_name%' => $this->testCase->getQueueName(),
            '%tube_type%' => $this->testCase->getQueueType(),
        ]);
    }
}
