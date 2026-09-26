<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Hydrator\Strategy;

use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

class ToStringTest extends TestCase
{
    /** @return array<string, array{mixed, string}> */
    public static function valueProvider(): array
    {
        return [
            'string' => ['value', 'value'],
            'integer' => [42, '42'],
            'negative integer' => [-7, '-7'],
            'float' => [1.5, '1.5'],
            'true' => [true, '1'],
            'false' => [false, ''],
            'empty string' => ['', ''],
            'stringable' => [
                new class implements Stringable {
                    public function __toString(): string
                    {
                        return 'stringable';
                    }
                },
                'stringable',
            ],
        ];
    }

    #[DataProvider('valueProvider')]
    public function testExtractConvertsToString(mixed $value, string $expected): void
    {
        $this->assertSame($expected, (new ToString())->extract($value));
    }

    public function testExtractReturnsNullForNull(): void
    {
        $this->assertNull((new ToString())->extract(null));
    }
}
