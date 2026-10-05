<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\NameScopeTrait\Person as NamespacedPerson;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionException;
use RuntimeException;

/** @internal */
final class CollectionNameCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws RuntimeException
     * @throws ReflectionException
     */
    public static function objects(): iterable
    {
        foreach (self::specifications() as $label => $specification) {
            foreach (['param', 'var'] as $tag) {
                foreach (['list', 'non-empty-list', 'tuple', 'non-empty-array', 'ArrayObject'] as $shape) {
                    foreach (self::scopes($label) as $scope) {
                        $original = CollectionNameFixture::create($specification, $tag, $shape, $scope);
                        yield $label . '/' . $tag . '/' . $shape . '/' . $scope => [$original];
                    }
                }
            }
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: object|null, 3?: string}> */
    private static function specifications(): array
    {
        $fixtures = 'Eventjet\\Json\\Test\\Acceptance\\Fixtures';
        $person = new Person('Ada', 'Lovelace');
        return [
            ...self::sourceSpecifications($fixtures, $person),
            ...self::shadowSpecifications($fixtures, $person),
            'import' => ['use ' . $fixtures . '\\Person;', 'Person', $person],
            'alias' => ['use ' . $fixtures . '\\Person as ImportedPerson;', 'ImportedPerson', $person],
            'unicode alias' => ['use ' . $fixtures . '\\Person as ÜberPerson;', 'ÜberPerson', $person],
            'alias case' => ['use ' . $fixtures . '\\Person as ImportedPerson;', 'importedperson', $person],
            'long namespace alias' => [
                'use Eventjet as Root;',
                'Root\\Json\\Test\\Acceptance\\Fixtures\\Person',
                $person,
            ],
            'uppercase alias keyword' => ['use ' . $fixtures . '\\Person AS Model;', 'Model', $person],
            'namespace alias' => ['use ' . $fixtures . ' as Models;', 'Models\\Person', $person],
            'namespace alias case' => ['use ' . $fixtures . ' as Models;', 'models\\Person', $person],
            'group' => ['use ' . $fixtures . '\\{Person, StringBackedStatus as Status};', 'Person', $person],
            'group alias' => [
                'use ' . $fixtures . '\\{Person, StringBackedStatus as Status};',
                'Status',
                StringBackedStatus::Ready,
            ],
            'multiple imports' => [
                'use ' . $fixtures . '\\StringBackedStatus, ' . $fixtures . '\\Person as Model;',
                'Model',
                $person,
            ],
            'comments' => [
                'use /* before */ ' . $fixtures . '\\Person /* after */ as /* alias */ Model;',
                'Model',
                $person,
            ],
            'leading slash import' => ['use \\' . $fixtures . '\\Person;', 'Person', $person],
            'mixed group' => [
                'use ' . $fixtures . '\\{function strlen, const ANSWER, Person as Model};',
                'Model',
                $person,
            ],
            'fully qualified' => ['', '\\' . $fixtures . '\\Person', $person],
            'lowercase qualified container' => ['', '\\' . $fixtures . '\\Person', $person, '\\arrayobject'],
            'lowercase bare container' => ['', '\\' . $fixtures . '\\Person', $person, 'arrayobject'],
            'aliased container' => [
                'use ArrayObject as Container; use ' . $fixtures . '\\Person;',
                'Person',
                $person,
                'Container',
            ],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: object|null, 3?: string}> */
    private static function sourceSpecifications(string $fixtures, Person $person): array
    {
        return [
            'trait namespace prefix' => [
                'use ' . $fixtures . '\\NameScopeTrait as Models; class BeforeClass {use Models;}',
                'Models\\Person',
                new NamespacedPerson(42),
            ],
            'legacy interpolation' => [
                '$text = "brace"; $string = "${text} {"; use ' . $fixtures . '\\Person;',
                'Person',
                $person,
            ],
            'preceding declarations' => [
                'trait BeforeTrait {} class BeforeClass {use BeforeTrait;} use ' . $fixtures . '\\Person;',
                'Person',
                $person,
            ],
            'preceding closure' => [
                '$captured = 42; $closure = function () use ($captured) {return $captured;}; use '
                    . $fixtures
                    . '\\Person;',
                'Person',
                $person,
            ],
            'preceding interpolation' => [
                '$text = "brace"; $string = "{$text} {"; use ' . $fixtures . '\\Person;',
                'Person',
                $person,
            ],
            'namespace relative' => ['', 'namespace\\LocalPerson', null],
            'namespace relative uppercase' => ['', 'NAMESPACE\\LocalPerson', null],
            'global shorthand' => ['', 'GlobalPerson', null],
        ];
    }

    /** @return list<string> */
    private static function scopes(string $label): array
    {
        return match ($label) {
            'import' => [
                'semicolon',
                'braces',
                'global',
                'repeated',
                'inherited',
                'same-line classes',
                'same-line declarations',
            ],
            'namespace relative' => ['semicolon', 'braces', 'global', 'repeated'],
            'global shorthand' => ['global'],
            default => ['semicolon'],
        };
    }

    /** @return array<string, array{0: string, 1: string, 2: object|null, 3?: string}> */
    private static function shadowSpecifications(string $fixtures, Person $person): array
    {
        return [
            'uppercase function group shadow' => [
                'use ' . $fixtures . '\\Person; use FUNCTION ' . $fixtures . '\\{Person};',
                'Person',
                $person,
            ],
            'function group shadow' => [
                'use ' . $fixtures . '\\Person; use function ' . $fixtures . '\\{Person};',
                'Person',
                $person,
            ],
            'constant group shadow' => [
                'use ' . $fixtures . '\\Person; use const ' . $fixtures . '\\{Person};',
                'Person',
                $person,
            ],
            'function shadow' => ['use function strlen as Person; use ' . $fixtures . '\\Person;', 'Person', $person],
            'constant shadow' => ['use const PHP_VERSION as Person; use ' . $fixtures . '\\Person;', 'Person', $person],
        ];
    }
}
