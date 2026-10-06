<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

/** @internal */
final class NestedConversionPathCases
{
    /**
     * @param class-string $leafClass
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(string $name, string $leafClass, string $wrong, string $suffix): iterable
    {
        $case = 0;
        foreach (['param', 'var'] as $parentTag) {
            foreach (ConversionPathInputs::wrappers($leafClass) as $shape => [
                $parentNative,
                $parentDeclaration,
                $before,
                $after,
                $segment,
            ]) {
                $class = CollectionDeclarationFixture::create($parentNative, $parentDeclaration, $parentTag);
                $json = '{"value":' . $before . '{"value":' . $wrong . '}' . $after . '}';
                $path = 'value' . $segment . '.value';
                $depth = 1 + ($case % 3);
                for ($level = 1; $level < $depth; ++$level) {
                    $class = CollectionDeclarationFixture::create('\\' . $class . '|int|null', '', $parentTag);
                    $json = '{"value":' . $json . '}';
                    $path = 'value.' . $path;
                }
                yield 'conversion path ' . $name . $parentTag . $shape . $depth => [
                    $json,
                    $class,
                    'Could not create ' . $leafClass . ' from the JSON object: Field ' . $path . $suffix,
                    3,
                ];
                ++$case;
            }
        }
    }
}
