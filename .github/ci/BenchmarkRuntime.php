<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

use InvalidArgumentException;

final class BenchmarkRuntime
{
    /**
     * @param array<array-key, mixed> $configuration
     * @return array<string, string>
     */
    public static function settings(array $configuration, bool $opcache): array
    {
        $settings = $configuration['runner.php_config'] ?? [];
        if (!is_array($settings)) {
            throw new InvalidArgumentException('Benchmark PHP configuration must be an object');
        }
        $normalized = [];
        foreach ($settings as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                throw new InvalidArgumentException('Benchmark PHP settings must have string names and scalar values');
            }
            $normalized[$name] = (string) $value;
        }
        return array_replace($normalized, [
            'pcov.enabled' => '0',
            'opcache.enable' => '1',
            'opcache.enable_cli' => $opcache ? '1' : '0',
            'opcache.file_update_protection' => '0',
            'opcache.file_cache' => '',
            'opcache.save_comments' => '1',
            'opcache.jit' => '0',
            'opcache.jit_buffer_size' => '0',
            'xdebug.mode' => 'off',
            'memory_limit' => '1G',
        ]);
    }
}
