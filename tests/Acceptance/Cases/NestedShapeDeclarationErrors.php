<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

/** @internal */
final class NestedShapeDeclarationErrors
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        foreach (['param', 'var'] as $tag) {
            foreach (NestedShapeDeclarationInputs::declarations($tag) as [$type, $reason, $documents]) {
                $class = CollectionDeclarationFixture::create('array', $type, $tag);
                foreach ($documents as $json) {
                    yield 'invalid nested shape declaration ' . $type . $tag . $json => [
                        $json,
                        $class,
                        'Could not create ' . $class . ' from the JSON object: ' . $reason,
                        3,
                    ];
                }
            }
        }
    }
}
