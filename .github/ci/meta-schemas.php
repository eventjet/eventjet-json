<?php

/**
 * Writes a PHP file next to each official meta-schema that returns the decoded document, so that the package loads
 * the meta-schemas without reading or decoding JSON at runtime.
 */

declare(strict_types=1);

function exportValue(mixed $value, string $indent): string
{
    if ($value instanceof stdClass) {
        $members = (array) $value;
        if ($members === []) {
            return '(object) []';
        }
        $lines = [];
        /** @var mixed $member */
        foreach ($members as $name => $member) {
            $lines[] = $indent . '    ' . var_export((string) $name, true) . ' => ' . exportValue($member, $indent . '    ');
        }
        return "(object) [\n" . implode(",\n", $lines) . ",\n" . $indent . ']';
    }
    if (is_array($value)) {
        if ($value === []) {
            return '[]';
        }
        $lines = [];
        /** @var mixed $item */
        foreach ($value as $item) {
            $lines[] = $indent . '    ' . exportValue($item, $indent . '    ');
        }
        return "[\n" . implode(",\n", $lines) . ",\n" . $indent . ']';
    }
    if (is_string($value) || is_int($value) || is_bool($value) || $value === null) {
        return var_export($value, true);
    }
    throw new RuntimeException('Unexpected value of type ' . get_debug_type($value) . '.');
}

$root = __DIR__ . '/../../resources/meta-schemas';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
/** @var SplFileInfo $file */
foreach ($files as $file) {
    if ($file->getExtension() !== 'json') {
        continue;
    }
    $json = file_get_contents($file->getPathname());
    if ($json === false) {
        throw new RuntimeException('Could not read ' . $file->getPathname() . '.');
    }
    $document = json_decode($json, flags: JSON_THROW_ON_ERROR);
    $php = "<?php\n\n// Generated from " . $file->getFilename() . " by .github/ci/meta-schemas.php. Do not edit.\n\n"
        . "declare(strict_types=1);\n\nreturn " . exportValue($document, '') . ";\n";
    file_put_contents(substr($file->getPathname(), 0, -strlen('.json')) . '.php', $php);
}
