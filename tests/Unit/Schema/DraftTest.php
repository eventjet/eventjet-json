<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Schema;

use Closure;
use Eventjet\Json\Internal\Wither;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\Keep;
use Eventjet\Json\Test\Acceptance\Cases\SchemaCases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

use function array_combine;
use function explode;
use function ltrim;
use function preg_match_all;
use function serialize;
use function sort;
use function str_ends_with;
use function str_starts_with;
use function substr;

#[CoversClass(Draft4::class)]
#[CoversClass(Draft6::class)]
#[CoversClass(Draft7::class)]
#[CoversClass(Draft201909::class)]
#[CoversClass(Draft202012::class)]
#[CoversClass(Wither::class)]
final class DraftTest extends TestCase
{
    /** @param array<array-key, mixed> $changes */
    #[DataProviderExternal(WithCases::class, 'changes')]
    public function testWithChangesOnlyTheGivenKeywords(Closure $with, array $changes, object $expected): void
    {
        static::assertSame(serialize($expected), serialize($with(...$changes)));
    }

    /**
     * Static analysis accepts a PHPDoc type of `with()` that is narrower than the constructor's, and PHP checks
     * neither, so only a comparison of the declarations notices when one of them changes without the other.
     *
     * @param class-string $draft
     * @throws ReflectionException
     */
    #[DataProvider('drafts')]
    public function testWithTakesTheConstructorParametersOrKeep(string $draft): void
    {
        $class = new ReflectionClass($draft);
        $expected = [];
        foreach (self::parameters($class->getMethod('__construct')) as $parameter) {
            $expected[] = [
                'name' => $parameter['name'],
                'native' => self::sorted([...$parameter['native'], Keep::class]),
                'default' => Keep::Current,
                'phpdoc' => $parameter['phpdoc'] === null ? null : self::orKeep($parameter['phpdoc']),
            ];
        }
        static::assertSame($expected, self::parameters($class->getMethod('with')));
    }

    /** @return iterable<string, array{class-string}> */
    public static function drafts(): iterable
    {
        foreach (SchemaCases::empty() as $empty) {
            yield $empty::class => [$empty::class];
        }
    }

    /**
     * @return list<array{name: string, native: list<string>, default: mixed, phpdoc: string|null}>
     * @throws ReflectionException
     */
    private static function parameters(ReflectionMethod $method): array
    {
        $matches = [];
        preg_match_all('/@param\s+(.+?)\s+\$(\w+)/', (string) $method->getDocComment(), $matches);
        /** @var array{0: list<string>, 1: list<string>, 2: list<string>} $matches */
        $phpDoc = array_combine($matches[2], $matches[1]);
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $native = (string) $parameter->getType();
            $parameters[] = [
                'name' => $parameter->getName(),
                'native' => self::sorted([
                    ...explode('|', ltrim($native, characters: '?')),
                    ...(str_starts_with($native, '?') ? ['null'] : []),
                ]),
                'default' => $parameter->getDefaultValue(),
                'phpdoc' => $phpDoc[$parameter->getName()] ?? null,
            ];
        }
        return $parameters;
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    private static function sorted(array $types): array
    {
        sort($types);
        return $types;
    }

    private static function orKeep(string $type): string
    {
        return str_ends_with($type, '|null') ? substr($type, offset: 0, length: -5) . '|Keep|null' : $type . '|Keep';
    }
}
