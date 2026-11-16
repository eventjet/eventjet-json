<?php

// Generated from core.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    '$id' => 'https://json-schema.org/draft/2020-12/meta/core',
    '$dynamicAnchor' => 'meta',
    'title' => 'Core vocabulary meta-schema',
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        '$id' => (object) [
            '$ref' => '#/$defs/uriReferenceString',
            '$comment' => 'Non-empty fragments not allowed.',
            'pattern' => '^[^#]*#?$',
        ],
        '$schema' => (object) [
            '$ref' => '#/$defs/uriString',
        ],
        '$ref' => (object) [
            '$ref' => '#/$defs/uriReferenceString',
        ],
        '$anchor' => (object) [
            '$ref' => '#/$defs/anchorString',
        ],
        '$dynamicRef' => (object) [
            '$ref' => '#/$defs/uriReferenceString',
        ],
        '$dynamicAnchor' => (object) [
            '$ref' => '#/$defs/anchorString',
        ],
        '$vocabulary' => (object) [
            'type' => 'object',
            'propertyNames' => (object) [
                '$ref' => '#/$defs/uriString',
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
                '$dynamicRef' => '#meta',
            ],
        ],
    ],
    '$defs' => (object) [
        'anchorString' => (object) [
            'type' => 'string',
            'pattern' => '^[A-Za-z_][-A-Za-z0-9._]*$',
        ],
        'uriString' => (object) [
            'type' => 'string',
            'format' => 'uri',
        ],
        'uriReferenceString' => (object) [
            'type' => 'string',
            'format' => 'uri-reference',
        ],
    ],
];
