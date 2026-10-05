<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use function array_key_exists;
use function assert;
use function sprintf;
use function ucfirst;

/** @internal */
final class CollectionValidationErrorCases
{
    /** @return iterable<string, array{string, class-string, string, int}> */
    public static function errors(): iterable
    {
        $fixtures = CollectionValidationInputs::fixtures();

        foreach (CollectionValidationInputs::itemTypes() as $name => [$type, $valid, $invalid]) {
            assert(array_key_exists($name, $fixtures), description: 'Each item domain has a matching typed fixture.');
            $class = $fixtures[$name]::class;

            foreach (['list', 'nonEmptyList', 'map', 'objectMap'] as $form) {
                foreach ([$form, 'public' . ucfirst($form)] as $field) {
                    $prefix = sprintf('Could not create %s from the JSON object: Field %s', $class, $field);

                    foreach (self::invalidCollections($valid, $invalid, $form) as $collection => [$path, $reason]) {
                        yield sprintf('%s %s rejects %s', $class, $field, $collection) => [
                            '{"' . $field . '":' . $collection . '}',
                            $class,
                            $prefix . $path . $reason,
                            3,
                        ];
                    }

                    foreach (CollectionShapeErrors::inputs($form, $type, $valid, $field) as $json => $reason) {
                        yield sprintf('%s %s rejects shape %s', $class, $field, $json) => [
                            '{"' . $field . '":' . $json . '}',
                            $class,
                            $prefix . $reason,
                            3,
                        ];
                    }
                }
            }
        }
    }

    /**
     * @param array<array-key, string> $invalid
     * @return iterable<string, array{string, string}>
     */
    private static function invalidCollections(string $valid, array $invalid, string $form): iterable
    {
        foreach ($invalid as $item => $reason) {
            if ($form === 'list' || $form === 'nonEmptyList') {
                yield '[' . $item . ']' => ['[0]', $reason];
                yield '[' . $valid . ',' . $item . ']' => ['[1]', $reason];
                continue;
            }

            yield '{"invalid":' . $item . '}' => ['[invalid]', $reason];
            yield '{"valid":' . $valid . ',"invalid":' . $item . '}' => ['[invalid]', $reason];
        }
    }
}
