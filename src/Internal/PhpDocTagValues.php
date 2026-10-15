<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/**
 * Parses tag values by the grammar that phpstan/phpdoc-parser 2.x uses for each tag.
 *
 * @internal
 */
final class PhpDocTagValues
{
    private const int PARAM = 1;
    private const int TYPED_VARIABLE = 3;
    private const int VAR = 4;
    private const int TYPED = 5;
    private const int DESCRIPTION = 6;
    private const int METHOD = 7;
    private const int TEMPLATE = 8;
    private const int EXTENDS = 9;
    private const int TYPE_ALIAS = 10;
    private const int IMPORT_TYPE = 11;
    private const int ASSERT = 12;

    private const array KINDS = [
        '@param' => self::PARAM,
        '@phpstan-param' => self::PARAM,
        '@psalm-param' => self::PARAM,
        '@phan-param' => self::PARAM,
        '@param-immediately-invoked-callable' => self::DESCRIPTION,
        '@phpstan-param-immediately-invoked-callable' => self::DESCRIPTION,
        '@param-later-invoked-callable' => self::DESCRIPTION,
        '@phpstan-param-later-invoked-callable' => self::DESCRIPTION,
        '@param-closure-this' => self::TYPED_VARIABLE,
        '@phpstan-param-closure-this' => self::TYPED_VARIABLE,
        '@pure-unless-callable-is-impure' => self::DESCRIPTION,
        '@phpstan-pure-unless-callable-is-impure' => self::DESCRIPTION,
        '@pure-unless-parameter-passed' => self::DESCRIPTION,
        '@phpstan-pure-unless-parameter-passed' => self::DESCRIPTION,
        '@var' => self::VAR,
        '@phpstan-var' => self::VAR,
        '@psalm-var' => self::VAR,
        '@phan-var' => self::VAR,
        '@return' => self::TYPED,
        '@phpstan-return' => self::TYPED,
        '@psalm-return' => self::TYPED,
        '@phan-return' => self::TYPED,
        '@phan-real-return' => self::TYPED,
        '@throws' => self::TYPED,
        '@phpstan-throws' => self::TYPED,
        '@mixin' => self::TYPED,
        '@phan-mixin' => self::TYPED,
        '@psalm-require-extends' => self::TYPED,
        '@phpstan-require-extends' => self::TYPED,
        '@psalm-require-implements' => self::TYPED,
        '@phpstan-require-implements' => self::TYPED,
        '@psalm-inheritors' => self::TYPED,
        '@phpstan-sealed' => self::TYPED,
        '@deprecated' => self::DESCRIPTION,
        '@property' => self::TYPED_VARIABLE,
        '@property-read' => self::TYPED_VARIABLE,
        '@property-write' => self::TYPED_VARIABLE,
        '@phpstan-property' => self::TYPED_VARIABLE,
        '@phpstan-property-read' => self::TYPED_VARIABLE,
        '@phpstan-property-write' => self::TYPED_VARIABLE,
        '@psalm-property' => self::TYPED_VARIABLE,
        '@psalm-property-read' => self::TYPED_VARIABLE,
        '@psalm-property-write' => self::TYPED_VARIABLE,
        '@phan-property' => self::TYPED_VARIABLE,
        '@phan-property-read' => self::TYPED_VARIABLE,
        '@phan-property-write' => self::TYPED_VARIABLE,
        '@method' => self::METHOD,
        '@phpstan-method' => self::METHOD,
        '@psalm-method' => self::METHOD,
        '@phan-method' => self::METHOD,
        '@template' => self::TEMPLATE,
        '@phpstan-template' => self::TEMPLATE,
        '@psalm-template' => self::TEMPLATE,
        '@phan-template' => self::TEMPLATE,
        '@template-covariant' => self::TEMPLATE,
        '@phpstan-template-covariant' => self::TEMPLATE,
        '@psalm-template-covariant' => self::TEMPLATE,
        '@template-contravariant' => self::TEMPLATE,
        '@phpstan-template-contravariant' => self::TEMPLATE,
        '@psalm-template-contravariant' => self::TEMPLATE,
        '@extends' => self::EXTENDS,
        '@phpstan-extends' => self::EXTENDS,
        '@phan-extends' => self::EXTENDS,
        '@phan-inherits' => self::EXTENDS,
        '@template-extends' => self::EXTENDS,
        '@implements' => self::EXTENDS,
        '@phpstan-implements' => self::EXTENDS,
        '@template-implements' => self::EXTENDS,
        '@use' => self::EXTENDS,
        '@phpstan-use' => self::EXTENDS,
        '@template-use' => self::EXTENDS,
        '@phpstan-type' => self::TYPE_ALIAS,
        '@psalm-type' => self::TYPE_ALIAS,
        '@phan-type' => self::TYPE_ALIAS,
        '@phpstan-import-type' => self::IMPORT_TYPE,
        '@psalm-import-type' => self::IMPORT_TYPE,
        '@phpstan-assert' => self::ASSERT,
        '@phpstan-assert-if-true' => self::ASSERT,
        '@phpstan-assert-if-false' => self::ASSERT,
        '@psalm-assert' => self::ASSERT,
        '@psalm-assert-if-true' => self::ASSERT,
        '@psalm-assert-if-false' => self::ASSERT,
        '@phan-assert' => self::ASSERT,
        '@phan-assert-if-true' => self::ASSERT,
        '@phan-assert-if-false' => self::ASSERT,
        '@phpstan-this-out' => self::TYPED,
        '@phpstan-self-out' => self::TYPED,
        '@psalm-this-out' => self::TYPED,
        '@psalm-self-out' => self::TYPED,
        '@param-out' => self::TYPED_VARIABLE,
        '@phpstan-param-out' => self::TYPED_VARIABLE,
        '@psalm-param-out' => self::TYPED_VARIABLE,
    ];

    /**
     * Returns the kind of value, and the tag if it is a `@param` or `@var` tag that PHPStan reads.
     *
     * @return array{int, PhpDocTag|null}
     * @throws PhpDocSyntaxError
     */
    public static function parse(PhpDocTokens $tokens, string $tag): array
    {
        $kind = self::KINDS[$tag] ?? null;
        if ($kind === null) {
            PhpDocDoctrineParser::annotation($tokens);
            return [PhpDocTagParser::GENERIC, null];
        }
        return [
            PhpDocTagParser::VALID,
            match ($kind) {
                self::PARAM => PhpDocFieldTagValue::param($tokens, $tag),
                self::VAR => PhpDocFieldTagValue::variable($tokens, $tag),
                self::METHOD => PhpDocMethodTagValue::method($tokens),
                default => self::other($tokens, $kind),
            },
        ];
    }

    /** @throws PhpDocSyntaxError */
    private static function other(PhpDocTokens $tokens, int $kind): null
    {
        return match ($kind) {
            self::TYPED_VARIABLE => PhpDocOtherTagValue::typedVariable($tokens),
            self::TYPED => PhpDocOtherTagValue::typed($tokens),
            self::DESCRIPTION => PhpDocOtherTagValue::description($tokens),
            self::TEMPLATE => PhpDocOtherTagValue::template($tokens),
            self::EXTENDS => PhpDocOtherTagValue::extends($tokens),
            self::TYPE_ALIAS => PhpDocOtherTagValue::typeAlias($tokens),
            self::IMPORT_TYPE => PhpDocOtherTagValue::importType($tokens),
            default => PhpDocOtherTagValue::assert($tokens),
        };
    }
}
