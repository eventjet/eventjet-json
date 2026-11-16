<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\ValidationFailure;
use JsonException;
use stdClass;

use function array_diff_key;
use function array_fill_keys;
use function array_intersect_key;
use function array_keys;
use function array_slice;
use function in_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * The applicators that a schema and a value can be placed in, for the drafts that have them.
 *
 * @internal
 */
final readonly class ApplicatorWrappings
{
    private const string ONE_OF = 'The value must match exactly one of the "oneOf" schemas, but it matches ';

    /**
     * @param array<array-key, mixed> $dialect The `$schema` member of the schema, if it has one.
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     */
    private function __construct(
        private array $dialect,
        private mixed $schema,
        private mixed $value,
        private string $class,
    ) {}

    /**
     * Returns the wrappings of a schema document by name. There are none if moving the schema could change its
     * meaning.
     *
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @return array<string, ApplicatorWrapping>
     * @throws JsonException
     */
    public static function of(string $schemaJson, mixed $value, string $class): array
    {
        $selfContained = SelfContainedSchemas::is($schemaJson);
        if (!$selfContained) {
            return [];
        }
        /** @var mixed $schema */
        $schema = json_decode($schemaJson, flags: JSON_THROW_ON_ERROR);
        $dialect = [];
        if ($schema instanceof stdClass) {
            $dialect = array_intersect_key((array) $schema, ['$schema' => true]);
            $schema = (object) array_diff_key((array) $schema, $dialect);
        }
        $wrappings = new self($dialect, $schema, $value, $class);
        return [
            ...$wrappings->forObjects(),
            ...$wrappings->forArrays(),
            ...$wrappings->forAnyValue(),
            ...$wrappings->forMembers(),
        ];
    }

    /** @return array<string, ApplicatorWrapping> */
    private function forObjects(): array
    {
        return [
            'properties' => new ApplicatorWrapping(
                $this->wrap(['properties' => (object) ['x' => $this->schema]]),
                (object) ['x' => $this->value],
                [['/x', '/properties/x']],
            ),
            'additionalProperties' => new ApplicatorWrapping(
                $this->wrap(['additionalProperties' => $this->schema]),
                (object) ['x' => $this->value, 'y' => $this->value],
                [
                    ['/x', '/additionalProperties'],
                    ['/y', '/additionalProperties'],
                ],
            ),
            'patternProperties' => new ApplicatorWrapping(
                $this->wrap(['patternProperties' => (object) ['x' => $this->schema]]),
                (object) ['x' => $this->value, 'xx' => $this->value],
                [
                    ['/x',  '/patternProperties/x'],
                    ['/xx', '/patternProperties/x'],
                ],
            ),
        ];
    }

    /** @return array<string, ApplicatorWrapping> */
    private function forArrays(): array
    {
        $pair = [$this->value, $this->value];
        $wrappings = [
            'items' => new ApplicatorWrapping($this->wrap(['items' => $this->schema]), $pair, [
                ['/0', '/items'],
                ['/1', '/items'],
            ]),
        ];
        if ($this->class === Draft202012::class) {
            $wrappings['prefixItems'] = new ApplicatorWrapping(
                $this->wrap(['prefixItems' => [$this->schema, $this->schema]]),
                $pair,
                [
                    ['/0', '/prefixItems/0'],
                    ['/1', '/prefixItems/1'],
                ],
            );
        }
        if ($this->class !== Draft202012::class) {
            $wrappings['items array'] = new ApplicatorWrapping(
                $this->wrap(['items' => [$this->schema, $this->schema]]),
                $pair,
                [['/0', '/items/0'], ['/1', '/items/1']],
            );
            $wrappings['additionalItems'] = new ApplicatorWrapping(
                $this->wrap(['items' => [new stdClass()], 'additionalItems' => $this->schema]),
                [0, ...$pair],
                [
                    ['/1', '/additionalItems'],
                    ['/2', '/additionalItems'],
                ],
            );
        }
        if ($this->hasUnevaluated()) {
            $wrappings['unevaluatedItems'] = new ApplicatorWrapping(
                $this->wrap(['unevaluatedItems' => $this->schema]),
                $pair,
                [
                    ['/0', '/unevaluatedItems'],
                    ['/1', '/unevaluatedItems'],
                ],
            );
            $wrappings['unevaluatedProperties'] = new ApplicatorWrapping(
                $this->wrap(['unevaluatedProperties' => $this->schema]),
                (object) ['x' => $this->value, 'y' => $this->value],
                [
                    ['/x', '/unevaluatedProperties'],
                    ['/y', '/unevaluatedProperties'],
                ],
            );
        }
        return $wrappings;
    }

    /** @return array<string, ApplicatorWrapping> */
    private function forAnyValue(): array
    {
        $wrappings = [
            'allOf' => new ApplicatorWrapping($this->wrap(['allOf' => [$this->schema, $this->schema]]), $this->value, [
                ['', '/allOf/0'],
                ['', '/allOf/1'],
            ]),
            'anyOf' => new ApplicatorWrapping(
                $this->wrap(['anyOf' => [$this->schema, $this->schema]]),
                $this->value,
                [['', '/anyOf/0'], ['', '/anyOf/1']],
                [new ValidationFailure(
                    '',
                    '/anyOf',
                    '#/anyOf',
                    'The value must match at least one of the "anyOf" schemas.',
                )],
            ),
            'oneOf' => new ApplicatorWrapping(
                $this->wrap(['oneOf' => [$this->schema, $this->schema]]),
                $this->value,
                [['', '/oneOf/0'], ['', '/oneOf/1']],
                [new ValidationFailure('', '/oneOf', '#/oneOf', self::ONE_OF . '0.')],
                [new ValidationFailure('', '/oneOf', '#/oneOf', self::ONE_OF . '2.')],
            ),
        ];
        if (in_array($this->class, [Draft7::class, Draft201909::class, Draft202012::class], strict: true)) {
            $wrappings['then'] = new ApplicatorWrapping(
                $this->wrap(['if' => new stdClass(), 'then' => $this->schema]),
                $this->value,
                [['', '/then']],
            );
            $wrappings['else'] = new ApplicatorWrapping(
                $this->wrap(['if' => (object) ['not' => new stdClass()], 'else' => $this->schema]),
                $this->value,
                [['', '/else']],
            );
        }
        return $wrappings;
    }

    /**
     * Wraps the schema in the dependencies of the first one, two, … members of the value, and in `propertyNames` for
     * a string.
     *
     * @return array<string, ApplicatorWrapping>
     */
    private function forMembers(): array
    {
        $keyword = $this->hasUnevaluated() ? 'dependentSchemas' : 'dependencies';
        $names = $this->value instanceof stdClass ? array_keys((array) $this->value) : [];
        $wrappings = [];
        $copies = [];
        foreach ($names as $count => $name) {
            $copies[] = ['', JsonPointer::format([$keyword, (string) $name])];
            $dependencies = array_fill_keys(array_slice($names, offset: 0, length: $count + 1), $this->schema);
            $wrappings[$keyword . ' ' . (string) ($count + 1)] = new ApplicatorWrapping(
                $this->wrap([$keyword => (object) $dependencies]),
                $this->value,
                $copies,
            );
        }
        if (is_string($this->value) && $this->class !== Draft4::class) {
            $wrappings['propertyNames'] = new ApplicatorWrapping(
                $this->wrap(['propertyNames' => $this->schema]),
                (object) [$this->value => 1],
                [[JsonPointer::format([$this->value]), '/propertyNames']],
            );
        }
        return $wrappings;
    }

    /**
     * Returns whether the draft has the `unevaluated*` keywords, which came with `dependentSchemas`.
     *
     * @mutation-free
     */
    private function hasUnevaluated(): bool
    {
        return in_array($this->class, [Draft201909::class, Draft202012::class], strict: true);
    }

    /** @param array<string, mixed> $keywords */
    private function wrap(array $keywords): object
    {
        return (object) [...$this->dialect, ...$keywords];
    }
}
