<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Reference;

use LogicException;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

use function array_slice;
use function in_array;

/**
 * Reads doc comments with phpstan/phpdoc-parser, set up the way PHPStan 2.3.1 sets it up. This is the reference that
 * the decoder's PHPDoc reader must match.
 *
 * @internal
 */
final class PhpStanReader
{
    public const array PARAM_TAGS = ['@param', '@phan-param', '@psalm-param', '@phpstan-param'];
    public const array VAR_TAGS = ['@var', '@phan-var', '@psalm-var', '@phpstan-var'];

    private static self|null $instance = null;

    private function __construct(
        private readonly Lexer $lexer,
        private readonly TypeParser $types,
        private readonly PhpDocParser $docs,
    ) {}

    /**
     * Returns the `@param` and `@var` tags, including their prefixed variants, in the order of the doc comment. A doc
     * comment that phpdoc-parser cannot parse as a whole has no tags.
     *
     * @return list<PhpStanTag>
     */
    public static function tags(string $doc): array
    {
        $node = self::instance()->docComment($doc);
        if ($node === null) {
            return [];
        }
        $tags = [];
        foreach ($node->children as $child) {
            $tag = $child instanceof PhpDocTagNode ? self::tag($child) : null;
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }
        return $tags;
    }

    /** Parses a standalone type such as `list<int>`, which must take the whole source. */
    public static function type(string $source): PhpStanType
    {
        $reader = self::instance();
        $tokens = new TokenIterator($reader->lexer->tokenize($source));
        try {
            $type = $reader->types->parse($tokens);
        } catch (ParserException) {
            return PhpStanType::none();
        }
        $complete = $tokens->isCurrentTokenType(Lexer::TOKEN_END);
        return $complete ? PhpStanTypeTranslator::translate($type) : PhpStanType::none();
    }

    private static function instance(): self
    {
        if (self::$instance === null) {
            // PHPStan's conf/services.neon passes these attributes. They do not change what is parsed.
            $config = new ParserConfig(usedAttributes: ['lines' => true]);
            $constants = new ConstExprParser($config);
            $types = new TypeParser($config, $constants);
            self::$instance = new self(new Lexer($config), $types, new PhpDocParser($config, $types, $constants));
        }
        return self::$instance;
    }

    /** Returns the tag if it is one of the `@param` or `@var` tags with a valid value, like PhpDocNode's getters. */
    private static function tag(PhpDocTagNode $tag): PhpStanTag|null
    {
        $value = $tag->value;
        return match (true) {
            $value instanceof ParamTagValueNode => self::named(
                $tag->name,
                self::PARAM_TAGS,
                $value->parameterName,
                $value->type,
            ),
            $value instanceof VarTagValueNode => self::named(
                $tag->name,
                self::VAR_TAGS,
                $value->variableName,
                $value->type,
            ),
            default => null,
        };
    }

    /** @param list<string> $names */
    private static function named(string $name, array $names, string $variable, TypeNode $type): PhpStanTag|null
    {
        $read = in_array($name, $names, strict: true);
        return $read ? new PhpStanTag($name, $variable, PhpStanTypeTranslator::translate($type)) : null;
    }

    /** Parses a doc comment like PHPStan's PhpDocStringResolver, or returns null where that throws. */
    private function docComment(string $doc): PhpDocNode|null
    {
        $tokens = $this->lexer->tokenize($doc);
        // The lexer stops at a lone carriage return. Without the closing token, phpdoc-parser reads no tags, but it can
        // read past the last token first.
        $closing = array_slice($tokens, offset: -2, length: 1)[0] ?? null;
        if ($closing === null || $closing[Lexer::TYPE_OFFSET] !== Lexer::TOKEN_CLOSE_PHPDOC) {
            return null;
        }
        $iterator = new TokenIterator($tokens);
        try {
            $node = $this->docs->parse($iterator);
            $iterator->consumeTokenType(Lexer::TOKEN_END);
        } catch (ParserException|LogicException) {
            return null;
        }
        return $node;
    }
}
