<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use RuntimeException;

/** Bounded Linux subprocess execution for performance tooling. */
final class BenchmarkCommand
{
    /** @param non-empty-list<string> $arguments */
    public static function run(array $arguments, string|null $directory = null): string
    {
        $result = self::execute($arguments, $directory);
        if ($result['status'] !== 0) {
            throw new RuntimeException(
                'Command failed (' . $result['status'] . '): ' . implode(' ', $arguments) . "\n" . $result['output'],
            );
        }
        return $result['output'];
    }

    /**
     * @param non-empty-list<string> $arguments
     * @return array{output: string, status: int}
     */
    public static function execute(array $arguments, string|null $directory = null): array
    {
        $process = proc_open(
            ['timeout', '90', ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR],
            $pipes,
            $directory,
        );
        if ($process === false) {
            throw new RuntimeException('Cannot start command');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($process);
        if ($output === false) {
            throw new RuntimeException('Cannot read command output');
        }
        return ['output' => $output, 'status' => $status];
    }
}
