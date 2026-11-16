<?php

// Generated from applicator.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2019-09/schema',
    '$id' => 'https://json-schema.org/draft/2019-09/meta/applicator',
    '$recursiveAnchor' => true,
    'title' => 'Applicator vocabulary meta-schema',
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        'additionalItems' => (object) [
            '$recursiveRef' => '#',
        ],
        'unevaluatedItems' => (object) [
            '$recursiveRef' => '#',
        ],
        'items' => (object) [
            'anyOf' => [
                (object) [
                    '$recursiveRef' => '#',
                ],
                (object) [
                    '$ref' => '#/$defs/schemaArray',
                ],
            ],
        ],
        'contains' => (object) [
            '$recursiveRef' => '#',
        ],
        'additionalProperties' => (object) [
            '$recursiveRef' => '#',
        ],
        'unevaluatedProperties' => (object) [
            '$recursiveRef' => '#',
        ],
        'properties' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$recursiveRef' => '#',
            ],
            'default' => (object) [],
        ],
        'patternProperties' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$recursiveRef' => '#',
            ],
            'propertyNames' => (object) [
                'format' => 'regex',
            ],
            'default' => (object) [],
        ],
        'dependentSchemas' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$recursiveRef' => '#',
            ],
        ],
        'propertyNames' => (object) [
            '$recursiveRef' => '#',
        ],
        'if' => (object) [
            '$recursiveRef' => '#',
        ],
        'then' => (object) [
            '$recursiveRef' => '#',
        ],
        'else' => (object) [
            '$recursiveRef' => '#',
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
            '$recursiveRef' => '#',
        ],
    ],
    '$defs' => (object) [
        'schemaArray' => (object) [
            'type' => 'array',
            'minItems' => 1,
            'items' => (object) [
                '$recursiveRef' => '#',
            ],
        ],
    ],
];
