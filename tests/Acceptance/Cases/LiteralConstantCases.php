<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ReflectionClass;
use ReflectionException;
use RuntimeException;

use function sha1;
use function str_contains;

use const PHP_INT_MAX;

/** @internal */
final class LiteralConstantCases
{
    /**
     * @return iterable<string, array{object}>
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function objects(): iterable
    {
        $cases = [
            ['USE CONST PHP_INT_MAX AS Maximum;',                                                 'Maximum',          PHP_INT_MAX],
            ['class Base {public const int VALUE = 42;}',                                         'parent::VALUE',    42],
            ['class Base {public const int VALUE = 42;}',                                         'static::VALUE',    42],
            ['use const PHP_INT_MAX as Maximum;',                                                 'Maximum',          PHP_INT_MAX],
            ['const LOCAL = 42;',                                                                 'LOCAL',            42],
            ['const PHP_INT_MAX = 42;',                                                           '\\PHP_INT_MAX',    PHP_INT_MAX],
            ['const LOCAL = 42;',                                                                 'namespace\\LOCAL', 42],
            ['use const Eventjet\\Json\\Test\\Acceptance\\Fixtures\\{LITERAL_NUMBER as Number};', 'Number',           42],
        ];
        foreach ($cases as [$imports, $declaration, $value]) {
            foreach (['param', 'var'] as $tag) {
                $key = sha1($imports . $declaration . $tag);
                $namespace = 'LiteralConstants_' . $key;
                $doc = '/** @' . $tag . ' list<' . $declaration . '>' . ($tag === 'param' ? ' $value' : '') . ' */';
                $field = $tag === 'param'
                    ? $doc . ' public function __construct(public array $value) {}'
                    : $doc . ' public array $value;';
                $class = CollectionNameSource::load(
                    $namespace . '\\Target',
                    'namespace '
                    . $namespace
                    . '; '
                    . $imports
                    . ' final class Target'
                    . (str_contains($imports, 'class Base') ? ' extends Base' : '')
                    . ' {'
                    . $field
                    . '}',
                );
                $reflection = new ReflectionClass($class);
                $object = $tag === 'param' ? $reflection->newInstance([$value]) : $reflection->newInstance();
                if ($tag === 'var') {
                    $reflection->getProperty('value')->setValue($object, [$value]);
                }
                yield 'literal constant name ' . $key => [$object];
            }
        }
    }
}
