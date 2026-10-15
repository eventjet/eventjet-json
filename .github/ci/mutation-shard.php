<?php

declare(strict_types=1);

/*
 * Prints the source files that one mutation testing shard mutates, one per line. Each file goes to exactly one shard.
 * The largest files are placed first, each on the shard with the fewest bytes so far, so that shards take similar time.
 */

$shard = $argv[1] ?? '';
$count = $argv[2] ?? '';
if (!ctype_digit($shard) || !ctype_digit($count) || (int) $count < 1 || (int) $shard < 1 || (int) $shard > (int) $count) {
    throw new InvalidArgumentException('Usage: mutation-shard.php SHARD COUNT, where 1 <= SHARD <= COUNT');
}

$sizes = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
        $sizes[$file->getPathname()] = $file->getSize();
    }
}
uksort($sizes, static fn(string $a, string $b): int => [$sizes[$b], $a] <=> [$sizes[$a], $b]);

$loads = array_fill(1, (int) $count, 0);
$assigned = [];
foreach ($sizes as $path => $size) {
    $lightest = array_keys($loads, min($loads), true)[0];
    $loads[$lightest] += $size;
    if ($lightest === (int) $shard) {
        $assigned[] = $path;
    }
}
sort($assigned);
echo implode("\n", $assigned), "\n";
