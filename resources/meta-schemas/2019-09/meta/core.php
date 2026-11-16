<?php

// Generated from core.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2019-09/schema',
    '$id' => 'https://json-schema.org/draft/2019-09/meta/core',
    '$recursiveAnchor' => true,
    'title' => 'Core vocabulary meta-schema',
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        '$id' => (object) [
            'type' => 'string',
            'format' => 'uri-reference',
            '$comment' => 'Non-empty fragments not allowed.',
            'pattern' => '^[^#]*#?$',
        ],
        '$schema' => (object) [
            'type' => 'string',
            'format' => 'uri',
        ],
        '$anchor' => (object) [
            'type' => 'string',
            'pattern' => '^[A-Za-z][-A-Za-z0-9.:_]*$',
        ],
        '$ref' => (object) [
            'type' => 'string',
            'format' => 'uri-reference',
        ],
        '$recursiveRef' => (object) [
            'type' => 'string',
            'format' => 'uri-reference',
        ],
        '$recursiveAnchor' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        '$vocabulary' => (object) [
            'type' => 'object',
            'propertyNames' => (object) [
                'type' => 'string',
                'format' => 'uri',
            ],
            'additionalProperties' => (object) [
                'type' => 'boolean',
            ],
        ],
        '$comment' => (object) [
            'type' => 'string',
        ],
        '$defs' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$recursiveRef' => '#',
            ],
            'default' => (object) [],
        ],
    ],
];
