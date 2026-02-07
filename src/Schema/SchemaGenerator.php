<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

use function class_exists;
use function count;
use function enum_exists;
use function explode;
use function interface_exists;

final class SchemaGenerator
{
    private readonly SchemaRegistry $registry;
    private readonly TypeNodeConverter $typeNodeConverter;
    private readonly ClassSchemaGenerator $classSchemaGenerator;
    private readonly Lexer $lexer;
    private readonly TypeParser $typeParser;

    public function __construct()
    {
        $this->registry = new SchemaRegistry();
        $this->classSchemaGenerator = new ClassSchemaGenerator($this->registry);
        $this->typeNodeConverter = new TypeNodeConverter($this->registry, $this->classSchemaGenerator);
        $this->classSchemaGenerator->setTypeNodeConverter($this->typeNodeConverter);
        $config = new ParserConfig([]);
        $this->lexer = new Lexer($config);
        $this->typeParser = new TypeParser($config, new ConstExprParser($config));
    }

    public function generate(string $type, bool $inlineRoot = true): Schema
    {
        if (class_exists($type) || interface_exists($type) || enum_exists($type)) {
            /** @var class-string $type */
            return $this->generateClass($type, $inlineRoot);
        }
        return $this->generateFromTypeString($type);
    }

    /**
     * @param class-string $className
     */
    private function generateClass(string $className, bool $inlineRoot): Schema
    {
        $refSchema = $this->classSchemaGenerator->generate($className);
        $defs = $this->registry->definitions();
        if ($inlineRoot && !$this->registry->isSelfReferenced($className)) {
            $shortName = $this->shortName($className);
            $rootDef = $defs[$shortName] ?? null;
            unset($defs[$shortName]);
            assert(
                $rootDef !== null,
                'ClassSchemaGenerator::generate() always registers the class, so its definition must exist',
            );
            if ($defs !== []) {
                return $rootDef->withDefs($defs);
            }
            return $rootDef;
        }
        assert($defs !== [], 'ClassSchemaGenerator::generate() always registers at least one definition');
        return $refSchema->withDefs($defs);
    }

    private function generateFromTypeString(string $type): Schema
    {
        $typeNode = $this->parseTypeString($type);
        $schema = $this->typeNodeConverter->convert($typeNode);
        $defs = $this->registry->definitions();
        if ($defs !== []) {
            return $schema->withDefs($defs);
        }
        return $schema;
    }

    private function parseTypeString(string $type): TypeNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($type));
        return $this->typeParser->parse($tokens);
    }

    private function shortName(string $className): string
    {
        $parts = explode('\\', $className);
        return $parts[count($parts) - 1];
    }
}
