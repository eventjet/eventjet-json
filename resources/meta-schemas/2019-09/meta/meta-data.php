<?php

// Generated from meta-data.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2019-09/schema',
    '$id' => 'https://json-schema.org/draft/2019-09/meta/meta-data',
    '$recursiveAnchor' => true,
    'title' => 'Meta-data vocabulary meta-schema',
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        'title' => (object) [
            'type' => 'string',
        ],
        'description' => (object) [
            'type' => 'string',
        ],
        'default' => true,
        'deprecated' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        'readOnly' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        'writeOnly' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        'examples' => (object) [
            'type' => 'array',
            'items' => true,
        ],
    ],
];
