<?php

declare(strict_types=1);

// Distribute every PHP source file once, balancing by code lines.
$shard = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT);
$count = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT);
if (count($argv) !== 3 || $shard === false || $count === false || $shard < 1 || $shard > $count) {
    fwrite(STDERR, "Expected 1 <= shard <= shard count\n");
    exit(1);
}

/** @var array<string, int> $weights */
$weights = [];
if (is_dir('src')) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src', FilesystemIterator::SKIP_DOTS));
    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            fwrite(STDERR, "Cannot read source file: $path\n");
            exit(1);
        }
        $weight = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '/') && !str_starts_with($line, '*')) {
                ++$weight;
            }
        }
        $weights[$path] = $weight;
    }
}
if ($weights === []) {
    fwrite(STDERR, "No PHP source files found\n");
    exit(1);
}

uksort($weights, static function (string $a, string $b) use ($weights): int {
    $byWeight = $weights[$b] <=> $weights[$a];
    return $byWeight !== 0 ? $byWeight : strcmp($a, $b);
});
$sizes = array_fill(0, $count, 0);
foreach ($weights as $path => $weight) {
    $lightest = 0;
    foreach ($sizes as $index => $size) {
        if ($size < $sizes[$lightest]) {
            $lightest = $index;
        }
    }
    $sizes[$lightest] += $weight;
    if ($lightest === ($shard - 1)) {
        echo $path, "\n";
    }
}
