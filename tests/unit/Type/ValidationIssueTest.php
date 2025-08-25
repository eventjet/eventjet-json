<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Type;

use Eventjet\Json\Type\ValidationIssue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class ValidationIssueTest extends TestCase
{
    /**
     * @return iterable<string, array{ValidationIssue, ValidationIssue, bool}>
     */
    public static function equalsCases(): iterable
    {
        $equals = [
            [new ValidationIssue('foo', 'bar'), new ValidationIssue('foo', 'bar')],
        ];
        $notEquals = [
            'Different messages' => [new ValidationIssue('foo', 'mykey'), new ValidationIssue('bar', 'mykey')],
            'Different paths' => [new ValidationIssue('foo', 'mykey'), new ValidationIssue('foo', 'myotherkey')],
        ];
        foreach ($equals as [$a, $b]) {
            yield sprintf('%s equals %s', $a, $b) => [$a, $b, true];
        }
        foreach ($notEquals as $name => [$a, $b]) {
            yield $name => [$a, $b, false];
        }
    }

    #[DataProvider('equalsCases')]
    public function testEquals(ValidationIssue $a, ValidationIssue $b, bool $expected): void
    {
        if ($expected) {
            self::assertTrue($a->equals($b), sprintf('Failed asserting that %s equals %s.', $a, $b));
            self::assertTrue($b->equals($a), sprintf('Failed asserting that %s equals %s.', $b, $a));
        } else {
            self::assertFalse($a->equals($b), sprintf('Failed asserting that %s does not equal %s.', $a, $b));
            self::assertFalse($b->equals($a), sprintf('Failed asserting that %s does not equal %s.', $b, $a));
        }
    }
}
