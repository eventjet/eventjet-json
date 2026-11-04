<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Schema;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use Eventjet\Json\Schema\JsonSchema;

use function file_get_contents;
use function is_file;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * The remote documents of the official JSON Schema Test Suite, which its tests reference under http://localhost:1234/.
 *
 * @internal
 */
final class OfficialSuiteRemotes
{
    private const string REMOTE = 'http://localhost:1234/';

    /**
     * Returns a loader for the remote documents, which decodes documents without `$schema` into the given class.
     *
     * @param class-string<Draft4|Draft6|Draft7|Draft201909|Draft202012> $class
     * @return Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null)
     */
    public static function loader(string $class): Closure
    {
        $loader = static function (string $uri) use (
            &$loader,
            $class,
        ): Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null {
            $hash = strpos($uri, needle: '#');
            if ($hash !== false) {
                $uri = substr($uri, offset: 0, length: $hash);
            }
            if (!str_starts_with($uri, self::REMOTE)) {
                return null;
            }
            $path = OfficialSuiteCases::SUITE . '/remotes/' . substr($uri, strlen(self::REMOTE));
            $exists = is_file($path);
            $json = $exists ? file_get_contents($path) : false;
            if ($json === false) {
                return null;
            }
            /** @var Closure(string): (Draft4|Draft6|Draft7|Draft201909|Draft202012|bool|DecodeError|null) $loader */
            return JsonSchema::decode($json, $class, loader: $loader);
        };
        return $loader;
    }
}
