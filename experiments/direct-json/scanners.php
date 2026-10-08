<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Eventjet\Json\Benchmark\Prototype\DirectParser;

$json = '[' . str_repeat('{"x":[1,2,3],"text":"ignore me"},', 49999) . '{"x":[]}]';
$pattern = '~(?(DEFINE)(?<s>"(?:[^"\\\\]++|\\\\.)*+")(?<o>\{(?:[^{}\[\]"]++|(?&s)|(?&o)|(?&a))*+\})(?<a>\[(?:[^{}\[\]"]++|(?&s)|(?&o)|(?&a))*+\]))\G(?:(?&o)|(?&a))\K~s';
foreach (['strcspn', 'regex-jit', 'regex-interpreter', 'token-offsets'] as $mode) {
    $start = hrtime(true);
    if ($mode === 'strcspn') {
        $parser = new DirectParser($json, 'hybrid');
        new ReflectionMethod($parser, 'skip')->invoke($parser);
        $end = new ReflectionProperty($parser, 'position')->getValue($parser);
    } elseif ($mode === 'token-offsets') {
        preg_match_all('~"(?:[^"\\\\]++|\\\\.)*+"(*SKIP)(*F)|[{}\[\]]~s', $json, $matches, PREG_OFFSET_CAPTURE);
        $end = strlen($json);
    } else {
        $test = $mode === 'regex-interpreter' ? str_replace('~(', '~(*NO_JIT)(', $pattern) : $pattern;
        $ok = preg_match($test, $json, $matches, PREG_OFFSET_CAPTURE);
        $end = $ok === 1 ? $matches[0][1] : preg_last_error_msg();
    }
    echo json_encode(['mode' => $mode, 'us' => (hrtime(true) - $start) / 1000, 'end' => $end]), "\n";
    unset($matches, $parser);
}
