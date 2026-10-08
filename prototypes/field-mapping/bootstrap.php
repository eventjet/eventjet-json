<?php

declare(strict_types=1);

$loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
if (getenv('FIELD_PROTOTYPE_BASELINE') !== '1') {
    $loader->addPsr4('Eventjet\\Json\\', __DIR__ . '/overlay', prepend: true);
} else {
    require __DIR__ . '/overlay/Field.php';
    require __DIR__ . '/overlay/MappedJsonFields.php';
}
require __DIR__ . '/fixtures.php';
