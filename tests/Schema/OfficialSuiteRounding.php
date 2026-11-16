<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use JsonException;
use RuntimeException;

use function in_array;
use function json_encode;
use function sprintf;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * The tests of the official JSON Schema Test Suite whose numbers differ only beyond the precision of PHP's `float` and
 * `int`. PHP rounds their data to the same value as a sibling test's data, so each one expects the sibling's result
 * instead of its own. See [ADR 0012](../../docs/adr/0012-validate-numbers-as-php-represents-them.md).
 *
 * @internal
 */
final class OfficialSuiteRounding
{
    /** The descriptions of the tests by file and case. */
    private const array TESTS = [
        'optional/bignum.json' => [
            'maximum integer comparison' => ['just above the maximum is invalid'],
            'float comparison with high precision (boolean)' => ['below the exclusive maximum is valid'],
            'float comparison with high precision' => ['below the exclusive maximum is valid'],
            'minimum integer comparison' => ['just below the minimum is invalid'],
            'float comparison with high precision on negative numbers (boolean)' => [
                'above the exclusive minimum is valid',
            ],
            'float comparison with high precision on negative numbers' => ['above the exclusive minimum is valid'],
            'enum comparison with high precision' => [
                'a slightly smaller decimal is invalid',
                'a slightly larger decimal is invalid',
            ],
            'const comparison with high precision' => [
                'a slightly smaller decimal is invalid',
                'a slightly larger decimal is invalid',
            ],
            'uniqueItems comparison with high precision' => [
                'distinct high-precision decimals are unique',
                'arrays containing distinct decimals are unique',
                'objects containing distinct decimals are unique',
            ],
        ],
    ];

    /** @var array<string, true> The tests of {@see self::TESTS} that were found, keyed by their names. */
    private array $found = [];

    /**
     * Whether the test's data is valid as PHP represents it.
     *
     * @param object{description: string, tests: list<object{description: string, data: mixed, valid: bool}>} $case
     * @param object{description: string, data: mixed, valid: bool} $test
     * @throws JsonException
     * @throws RuntimeException
     */
    public function validity(string $file, object $case, object $test): bool
    {
        $rounded = self::TESTS[$file][$case->description] ?? [];
        $isRounded = in_array($test->description, $rounded, strict: true);
        if (!$isRounded) {
            return $test->valid;
        }
        $this->found[self::name($file, $case->description, $test->description)] = true;
        $data = self::encode($test->data);
        $hasSibling = false;
        foreach ($case->tests as $sibling) {
            $comparable =
                !in_array($sibling->description, $rounded, strict: true) && self::encode($sibling->data) === $data;
            if (!$comparable) {
                continue;
            }
            if ($sibling->valid === $test->valid) {
                throw new RuntimeException(sprintf(
                    '"%s" expects the same result as "%s".',
                    $test->description,
                    $sibling->description,
                ));
            }
            $hasSibling = true;
        }
        if (!$hasSibling) {
            throw new RuntimeException(sprintf('No other test has the same data as "%s".', $test->description));
        }
        return !$test->valid;
    }

    /** @throws RuntimeException If the suite lacks a test of {@see self::TESTS}. */
    public function assertAllFound(): void
    {
        foreach (self::TESTS as $file => $cases) {
            foreach ($cases as $case => $tests) {
                foreach ($tests as $test) {
                    $name = self::name($file, $case, $test);
                    if (!($this->found[$name] ?? false)) {
                        throw new RuntimeException(sprintf('The suite has no test "%s".', $name));
                    }
                }
            }
        }
    }

    private static function name(string $file, string $case, string $test): string
    {
        return sprintf('%s: %s: %s', $file, $case, $test);
    }

    /** @throws JsonException */
    private static function encode(mixed $data): string
    {
        return json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
