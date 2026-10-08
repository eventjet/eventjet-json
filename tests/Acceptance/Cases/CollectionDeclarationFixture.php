<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ReflectionClass;
use RuntimeException;

use function class_exists;
use function file_put_contents;
use function is_file;
use function sha1;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/** @internal */
final class CollectionDeclarationFixture
{
    /**
     * @throws \RuntimeException
     * @throws \ReflectionException
     */
    public static function object(string $nativeType, string $declaration, string $tag, mixed $value): object
    {
        $class = new ReflectionClass(self::create($nativeType, $declaration, $tag));
        $object = $tag === 'param' ? $class->newInstance($value) : $class->newInstance();
        if ($tag === 'var') {
            $class->getProperty('value')->setValue($object, $value);
        }
        return $object;
    }

    /**
     * @return class-string
     * @throws RuntimeException
     */
    public static function create(string $nativeType, string $declaration, string $tag): string
    {
        $doc = match (true) {
            $declaration === '' => '',
            str_starts_with($declaration, '/**') => $declaration,
            default => '/** @' . $tag . ' ' . $declaration . ($tag === 'param' ? ' $value' : '') . ' */',
        };
        $field = $tag === 'param'
            ? $doc . "\n" . 'public function __construct(public ' . $nativeType . ' $value) {}'
            : $doc . "\n" . 'public ' . $nativeType . ' $value;';
        $class = 'InvalidCollection_' . sha1($field);
        if (class_exists($class)) {
            return $class;
        }
        // Invalid PHPDoc is input data for the decoder, not a declaration in the test implementation.
        $path = tempnam(sys_get_temp_dir(), prefix: 'json-collection-');
        if ($path === false) {
            throw new RuntimeException('Could not create a collection fixture file.');
        }
        try {
            $written = file_put_contents($path, '<?php final class ' . $class . ' {' . $field . '}');
            if ($written === false) {
                throw new RuntimeException('Could not write the collection fixture.');
            }
            $exists = is_file($path);
            if (!$exists) {
                throw new RuntimeException('The collection fixture file is missing.');
            }
            require $path;
        } finally {
            unlink($path);
        }
        if (!class_exists($class)) {
            throw new RuntimeException('The collection fixture did not declare its class.');
        }
        return $class;
    }
}
