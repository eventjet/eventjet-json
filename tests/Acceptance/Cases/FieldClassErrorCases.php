<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\JsonSerializableTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget;
use Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface;

/** @internal */
final class FieldClassErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \RuntimeException
     */
    public static function errors(): iterable
    {
        $fieldErrors = [
            'interface' => [
                RootTargetInterface::class,
                'Field value uses interface Eventjet\Json\Test\Acceptance\Fixtures\RootTargetInterface, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            ],
            'abstract class' => [
                AbstractRootTarget::class,
                'Field value uses abstract class Eventjet\Json\Test\Acceptance\Fixtures\AbstractRootTarget, which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.',
            ],
        ];
        foreach ($fieldErrors as $label => [$type, $reason]) {
            $class = CollectionDeclarationFixture::create('\\' . $type, '', 'param');
            yield 'field cannot use ' . $label => [
                '{}',
                $class,
                'Could not create ' . $class . ' from the JSON object: ' . $reason,
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        $classErrors = [
            'class that implements JsonSerializable' => [
                JsonSerializableTarget::class,
                '{}',
                'Target type implements JsonSerializable. Its custom JSON representation may not match its constructor parameters, so it cannot satisfy the round-trip contract.',
            ],
            'class with a private constructor' => [
                PrivateConstructorTarget::class,
                '{"value":{}}',
                'Target class has a private constructor, which cannot be called to create the object.',
            ],
        ];
        foreach ($classErrors as $label => [$type, $json, $reason]) {
            yield 'field reports the declarations of its ' . $label => [
                $json,
                CollectionDeclarationFixture::create('\\' . $type, '', 'param'),
                'Could not create ' . $type . ' from the JSON object: ' . $reason,
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
        $optional = CollectionNameSource::load(
            'OptionalPrivateConstructorField',
            'final class OptionalPrivateConstructorField { public function __construct(public ?\\'
            . PrivateConstructorTarget::class
            . ' $child = null) {} }',
        );
        foreach (['missing' => '{}', 'null' => '{"child":null}'] as $label => $json) {
            yield 'nullable field rejects a class with a private constructor when the member is ' . $label => [
                $json,
                $optional,
                'Could not create Eventjet\Json\Test\Acceptance\Fixtures\PrivateConstructorTarget from the JSON object: Target class has a private constructor, which cannot be called to create the object.',
                DecodeErrorKind::UnsupportedDeclaration,
            ];
        }
    }
}
