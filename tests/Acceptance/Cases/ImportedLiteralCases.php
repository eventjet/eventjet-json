<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionClass;

/**
 * Literal types that doc comments import with grouped, aliased, and unusually spelled `use` statements, and how
 * messages print literals and enum cases.
 *
 * @internal
 */
final class ImportedLiteralCases
{
    private const string NAMESPACE = 'ImportingLiterals';

    /**
     * @return iterable<string, DecodeSuccess>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function successes(): iterable
    {
        foreach (self::fields() as $label => [$class, , $value]) {
            yield $label => DecodeSuccess::roundTrip(new ReflectionClass(self::load($class))->newInstance($value));
        }
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \RuntimeException
     */
    public static function failures(): iterable
    {
        foreach (self::fields() as $label => [$class, $printed]) {
            $class = self::load($class);
            yield $label . ' is printed' => [
                '{"value":[]}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value must be of type '
                    . $printed
                    . ', array given.',
                DecodeErrorKind::InvalidValue,
            ];
        }
    }

    /** @return iterable<string, array{string, string, bool|int|string|StringBackedStatus|null}> */
    private static function fields(): iterable
    {
        yield 'a grouped constant import' => ['GroupedConstImport', "'ready'", 'ready'];
        yield 'an aliased grouped constant import' => ['AliasedConstImport', '42', 42];
        yield 'a constant in a mixed group import' => ['MixedGroupConstImport', 'true', true];
        yield 'an aliased constant in a mixed group import' => ['MixedGroupAliasImport', '42', 42];
        yield 'a function import does not import a constant' => ['FunctionImport', '1', 1];
        yield 'a grouped function import does not import a constant' => ['GroupedFunctionImport', '2', 2];
        yield 'a constant import with a leading separator' => ['LeadingSeparatorImport', "'ready'", 'ready'];
        yield 'a group import with a space before the brace' => ['SpacedGroupImport', '42', 42];
        yield 'an uppercase const keyword' => ['UppercaseConstImport', "'ready'", 'ready'];
        yield 'an imported enum case' => [
            'EnumCaseLabel',
            StringBackedStatus::class . '::Ready',
            StringBackedStatus::Ready,
        ];
        yield 'a union of literals' => ['LiteralLabels', "'ready'|42|true|null", null];
    }

    /**
     * @return class-string
     * @throws \RuntimeException
     */
    private static function load(string $class): string
    {
        $namespace = self::NAMESPACE;
        $status = StringBackedStatus::class;
        CollectionNameSource::load($namespace . '\\GroupedConstImport', <<<PHP
            namespace ImportedLiteralConstants {
                const READY = 'ready';
                const ANSWER = 42;
                const FLAG = true;
                const SHADOW = 7;
                const GROUPED_SHADOW = 8;
            }
            namespace {$namespace} {
                use const ImportedLiteralConstants\\{READY, ANSWER as Answer};
                use ImportedLiteralConstants\\{Unused, const FLAG, const ANSWER as Q};
                use function ImportedLiteralConstants\\SHADOW;
                use function ImportedLiteralConstants\\{GROUPED_SHADOW, other};
                use const \\ImportedLiteralConstants\\READY as LeadingReady;
                use const ImportedLiteralConstants\\ {ANSWER as Spaced};
                use CONST ImportedLiteralConstants\\{READY as Upper};
                use {$status};
                const SHADOW = 1;
                const GROUPED_SHADOW = 2;
                final class GroupedConstImport { /** @param READY \$value */ public function __construct(public string \$value) {} }
                final class AliasedConstImport { /** @param Answer \$value */ public function __construct(public int \$value) {} }
                final class MixedGroupConstImport { /** @param FLAG \$value */ public function __construct(public bool \$value) {} }
                final class MixedGroupAliasImport { /** @param Q \$value */ public function __construct(public int \$value) {} }
                final class FunctionImport { /** @param SHADOW \$value */ public function __construct(public int \$value) {} }
                final class GroupedFunctionImport { /** @param GROUPED_SHADOW \$value */ public function __construct(public int \$value) {} }
                final class LeadingSeparatorImport { /** @param LeadingReady \$value */ public function __construct(public string \$value) {} }
                final class SpacedGroupImport { /** @param Spaced \$value */ public function __construct(public int \$value) {} }
                final class UppercaseConstImport { /** @param Upper \$value */ public function __construct(public string \$value) {} }
                final class EnumCaseLabel { /** @param StringBackedStatus::Ready \$value */ public function __construct(public StringBackedStatus \$value) {} }
                final class LiteralLabels { /** @param 'ready'|42|true|null \$value */ public function __construct(public string|int|bool|null \$value) {} }
            }
            PHP);
        /** @var class-string */
        return $namespace . '\\' . $class;
    }
}
