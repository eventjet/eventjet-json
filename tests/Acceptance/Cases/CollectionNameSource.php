<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use RuntimeException;

use function class_exists;
use function file_put_contents;
use function is_file;
use function register_shutdown_function;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/** @internal */
final class CollectionNameSource
{
    /**
     * @return class-string
     * @throws RuntimeException
     */
    public static function load(string $class, string $source): string
    {
        if (class_exists($class)) {
            return $class;
        }
        $path = tempnam(sys_get_temp_dir(), prefix: 'json-names-');
        if ($path === false) {
            throw new RuntimeException('Could not create the collection name fixture.');
        }
        // Compile modern interpolation while retaining equivalent legacy spelling as lexer input.
        $compiledSource = str_replace(search: '${text}', replace: '{$text}', subject: $source);
        $written = file_put_contents($path, '<?php ' . $compiledSource);
        if ($written === false) {
            throw new RuntimeException('Could not write the collection name fixture.');
        }
        $exists = is_file($path);
        if (!$exists) {
            throw new RuntimeException('The collection name fixture file is missing.');
        }
        require $path;
        if ($compiledSource !== $source) {
            $written = file_put_contents($path, '<?php ' . $source);
            if ($written === false) {
                throw new RuntimeException('Could not restore the collection name source input.');
            }
        }
        register_shutdown_function(static function () use ($path): void {
            unlink($path);
        });
        if (!class_exists($class)) {
            throw new RuntimeException('The collection name fixture did not declare its class.');
        }
        return $class;
    }
}
