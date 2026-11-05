<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Schema;

use Closure;
use Eventjet\Json\Schema\Keep;
use Eventjet\Json\Test\Acceptance\Cases\SchemaCases;
use Eventjet\Json\Test\Acceptance\Cases\SchemaInputs;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function array_fill_keys;
use function array_filter;
use function array_intersect_key;
use function array_keys;
use function get_object_vars;
use function sprintf;

/**
 * The `with()` method of a schema, its arguments, and the schema that the constructor builds from the keywords that the
 * changed schema should have. Generated cases leave out each keyword, pass `Keep::Current` for it, or pass the keyword
 * of another generated schema, which removes the keyword if it is `null`.
 *
 * @internal
 */
final class WithCases
{
    private const int GENERATED = 10;

    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{Closure, array<array-key, mixed>, object}>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function changes(): iterable
    {
        foreach (SchemaCases::empty() as $empty) {
            $draft = $empty::class;
            $every = SchemaInputs::withEveryKeyword(new Randomizer(new Mt19937(0)), $draft);
            yield sprintf('%s with every keyword keeps them without arguments', $draft) => [
                $every->with(...),
                [],
                self::build($every, get_object_vars($every)),
            ];
            yield sprintf('empty %s gets every keyword', $draft) => [
                $empty->with(...),
                get_object_vars($every),
                self::build($empty, get_object_vars($every)),
            ];
            yield sprintf('%s with every keyword loses them all', $draft) => [
                $every->with(...),
                get_object_vars($empty),
                self::build($every, get_object_vars($empty)),
            ];
            for ($seed = 1; $seed <= self::GENERATED; $seed++) {
                $random = new Randomizer(new Mt19937($seed));
                $schema = SchemaInputs::schema($random, $draft);
                $other = get_object_vars(SchemaInputs::schema($random, $draft));
                $choices = self::choices($random, $other);
                $kept = array_filter(
                    $choices,
                    static fn(KeywordChoice $choice): bool => $choice === KeywordChoice::Keep,
                );
                $replaced = array_intersect_key($other, array_filter(
                    $choices,
                    static fn(KeywordChoice $choice): bool => $choice === KeywordChoice::Replace,
                ));
                yield sprintf('generated %s changed, seed %d', $draft, $seed) => [
                    $schema->with(...),
                    [...array_fill_keys(array_keys($kept), Keep::Current), ...$replaced],
                    self::build($schema, [...get_object_vars($schema), ...$replaced]),
                ];
            }
        }
    }

    /**
     * @param array<array-key, mixed> $keywords
     * @throws ReflectionException
     */
    private static function build(object $schema, array $keywords): object
    {
        return new ReflectionClass($schema)->newInstance(...$keywords);
    }

    /**
     * @param array<array-key, mixed> $keywords
     * @return array<array-key, KeywordChoice>
     */
    private static function choices(Randomizer $random, array $keywords): array
    {
        $choices = [];
        foreach (array_keys($keywords) as $keyword) {
            $choices[$keyword] = KeywordChoice::random($random);
        }
        return $choices;
    }
}
