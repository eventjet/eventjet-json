<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

use function sha1;
use function str_replace;

/** @internal */
final class CollectionNameDeclaration
{
    /**
     * @param array{0: string, 1: string, 2: object|null, 3?: string} $specification
     * @return array{class-string, string}
     * @throws RuntimeException
     */
    public static function source(
        array $specification,
        string $tag,
        string $shape,
        string $scope,
        string $scenario = '',
    ): array {
        [$imports, $itemType] = $specification;
        $container = $specification[3] ?? 'ArrayObject';
        $field = self::field($itemType, $tag, $shape, $container);
        // Separate scenarios must not share class metadata cached by the decoder.
        $key = sha1($imports . $field . $scope . $scenario);
        $namespace = 'CollectionNames_' . $key . '\\Nested';
        $class = 'Target_' . $key;
        $parent = $scope === 'inherited' ? 'class ParentTarget {' . $field . '} ' : '';
        $body =
            $imports
            . "\n"
            . $parent
            . 'final class '
            . $class
            . ($scope === 'inherited' ? ' extends ParentTarget {}' : ' {' . $field . '}')
            . ' final class LocalPerson {public int $value = 42;}';
        $source = self::wrap($body, $scope, $namespace, $key);
        $className = $scope === 'global' ? $class : $namespace . '\\' . $class;
        if ($scope === 'global') {
            $source = str_replace(
                ['GlobalPerson', 'LocalPerson', 'BeforeTrait', 'BeforeClass'],
                ['GlobalPerson_' . $key, 'LocalPerson_' . $key, 'BeforeTrait_' . $key, 'BeforeClass_' . $key],
                $source,
            );
        }
        $className = CollectionNameSource::load($className, $source);
        $itemClass = $itemType === 'GlobalPerson'
            ? 'GlobalPerson_' . $key
            : ($scope === 'global' ? '' : $namespace . '\\') . 'LocalPerson' . ($scope === 'global' ? '_' . $key : '');

        return [$className, $itemClass];
    }

    private static function wrap(string $body, string $scope, string $namespace, string $key): string
    {
        $source = match ($scope) {
            'same-line declarations' => 'namespace '
                . $namespace
                . '; class Earlier {} '
                . str_replace(search: "\n", replace: ' ', subject: $body),
            'same-line classes' => 'namespace Earlier_'
                . $key
                . '; use stdClass as Person; final class Target_'
                . $key
                . ' {} namespace '
                . $namespace
                . '; '
                . str_replace(search: "\n", replace: ' ', subject: $body),
            'global' => $body . ' final class GlobalPerson {public int $value = 42;}',
            'braces' => 'namespace ' . $namespace . ' {' . $body . '}',
            'repeated' => 'namespace '
                . $namespace
                . ' {use stdClass as LocalPerson;} namespace '
                . $namespace
                . ' {'
                . $body
                . '}',
            default => 'namespace ' . $namespace . '; ' . $body,
        };
        $after = 'use stdClass as Person, stdClass as ImportedPerson, stdClass as Models, stdClass as Model, stdClass as Status, stdClass as Container;';
        if ($scope !== 'global') {
            $source .=
                $scope === 'braces' || $scope === 'repeated'
                    ? ' namespace After_' . $key . ' {' . $after . '}'
                    : ' namespace After_' . $key . '; ' . $after;
        }
        return $source;
    }

    private static function field(string $itemType, string $tag, string $shape, string $container): string
    {
        $native = $shape === 'ArrayObject' ? '\\ArrayObject' : 'array';
        $declaration = match ($shape) {
            'tuple' => 'array{' . $itemType . '}',
            'non-empty-array' => $shape . '<string, ' . $itemType . '>',
            'ArrayObject' => $container . '<string, ' . $itemType . '>',
            default => $shape . '<' . $itemType . '>',
        };
        $doc = '/** @' . $tag . ' ' . $declaration . ($tag === 'param' ? ' $value' : '') . ' */';
        return $tag === 'param'
            ? $doc . ' public function __construct(public ' . $native . ' $value) {}'
            : $doc . ' public ' . $native . ' $value;';
    }
}
