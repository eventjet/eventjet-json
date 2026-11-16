<?php

// Generated from applicator.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    '$id' => 'https://json-schema.org/draft/2020-12/meta/applicator',
    '$dynamicAnchor' => 'meta',
    'title' => 'Applicator vocabulary meta-schema',
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        'prefixItems' => (object) [
            '$ref' => '#/$defs/schemaArray',
        ],
        'items' => (object) [
            '$dynamicRef' => '#meta',
        ],
        'contains' => (object) [
            '$dynamicRef' => '#meta',
        ],
        'additionalProperties' => (object) [
            '$dynamicRef' => '#meta',
        ],
        'properties' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$dynamicRef' => '#meta',
            ],
            'default' => (object) [],
        ],
        'patternProperties' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$dynamicRef' => '#meta',
            ],
            'propertyNames' => (object) [
                'format' => 'regex',
            ],
            'default' => (object) [],
        ],
        'dependentSchemas' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$dynamicRef' => '#meta',
            ],
            'default' => (object) [],
        ],
        'propertyNames' => (object) [
            '$dynamicRef' => '#meta',
        ],
        'if' => (object) [
            '$dynamicRef' => '#meta',
        ],
        'then' => (object) [
            '$dynamicRef' => '#meta',
        ],
        'else' => (object) [
            '$dynamicRef' => '#meta',
        ],
        'allOf' => (object) [
            '$ref' => '#/$defs/schemaArray',
        ],
        'anyOf' => (object) [
            '$ref' => '#/$defs/schemaArray',
        ],
        'oneOf' => (object) [
            '$ref' => '#/$defs/schemaArray',
        ],
        'not' => (object) [
            '$dynamicRef' => '#meta',
        ],
    ],
    '$defs' => (object) [
        'schemaArray' => (object) [
            'type' => 'array',
            'minItems' => 1,
            'items' => (object) [
                '$dynamicRef' => '#meta',
            ],
        ],
    ],
];
