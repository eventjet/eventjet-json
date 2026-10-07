<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumField;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedEnumPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\NonBackedStatus;
use RuntimeException;

/** @internal */
final class NonBackedEnumErrorCases
{
    /**
     * @api Called by PHPUnit through DataProviderExternal.
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['list', 'non-empty-list', 'non-empty-array', 'ArrayObject', 'array{}'] as $container) {
            $enum = '\\' . NonBackedStatus::class;
            $declaration = match ($container) {
                'array{}' => 'array{' . $enum . '}',
                'non-empty-array', 'ArrayObject' => $container . '<string, ' . $enum . '>',
                default => $container . '<' . $enum . '>',
            };
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create(
                    $container === 'ArrayObject' ? '\\ArrayObject' : 'array',
                    $declaration,
                    $tag,
                );
                $path = $container === 'array{}' ? 'value[0]' : 'value';
                yield 'non-backed collection declaration without input ' . $container . ' ' . $tag => [
                    '{}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: Field '
                        . $path
                        . ' uses non-backed enum '
                        . NonBackedStatus::class
                        . ', which cannot be decoded because it has no backing value.',
                    3,
                ];
            }
        }

        $fieldTarget = new NonBackedEnumField(NonBackedStatus::Ready);
        $rootMessage = 'Could not create Eventjet\\Json\\Test\\Acceptance\\Fixtures\\NonBackedStatus from the JSON object: Target type non-backed enum Eventjet\\Json\\Test\\Acceptance\\Fixtures\\NonBackedStatus, which cannot be decoded because it has no backing value.';
        $fieldMessage = 'Could not create Eventjet\\Json\\Test\\Acceptance\\Fixtures\\NonBackedEnumField from the JSON object: Field status uses non-backed enum Eventjet\\Json\\Test\\Acceptance\\Fixtures\\NonBackedStatus, which cannot be decoded because it has no backing value.';

        yield 'non-backed enum root target' => ['{}', NonBackedStatus::class, $rootMessage, 3];
        yield 'non-backed enum field, member present' => [
            '{"status":"ready"}',
            $fieldTarget::class,
            $fieldMessage,
            3,
        ];
        yield 'non-backed enum field, member absent' => ['{}', $fieldTarget::class, $fieldMessage, 3];

        $publicPropertyTarget = new NonBackedEnumPublicProperty();
        $publicPropertyMessage =
            'Could not create '
            . $publicPropertyTarget::class
            . ' from the JSON object: Field status uses non-backed enum '
            . NonBackedStatus::class
            . ', which cannot be decoded because it has no backing value.';

        yield 'non-backed enum public property' => [
            '{"status":"ready"}',
            $publicPropertyTarget::class,
            $publicPropertyMessage,
            3,
        ];
    }
}
