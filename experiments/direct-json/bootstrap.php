<?php

declare(strict_types=1);

// PROTOTYPE: deliberately kept outside the production decoder.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/NativeJsonDecoder.php';
require_once __DIR__ . '/DirectParser.php';
require_once __DIR__ . '/RegexValidator.php';
require_once __DIR__ . '/Fixtures.php';
require_once __DIR__ . '/Breadcrumb.php';
require_once __DIR__ . '/GraphParser.php';
require_once __DIR__ . '/workloads.php';
