<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\FailingAutoloader;
use RuntimeException;

/** @internal */
final class AutoloadFailureCases
{
    /** @return iterable<string, array{string, class-string|JsonType<list<mixed>|object>, string, DecodeErrorKind, RuntimeException}> */
    public static function errors(): iterable
    {
        $class = FailingAutoloader::target();
        $message = 'Could not create ' . $class . ' from the JSON object: Autoloader failed.';
        foreach ([
            'object' => ['{}', $class],
            'string' => ['"ready"', $class],
            'root list' => ['[]', JsonType::array($class)],
        ] as $label => [$json, $type]) {
            yield 'autoloader failure for the target, ' . $label => [
                $json,
                $type,
                $message,
                DecodeErrorKind::UnsupportedDeclaration,
                FailingAutoloader::failure(),
            ];
        }
    }
}
