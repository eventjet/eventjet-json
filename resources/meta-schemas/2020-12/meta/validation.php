<?php

// Generated from validation.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    '$id' => 'https://json-schema.org/draft/2020-12/meta/validation',
    '$dynamicAnchor' => 'meta',
    'title' => 'Validation vocabulary meta-schema',
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        'type' => (object) [
            'anyOf' => [
                (object) [
                    '$ref' => '#/$defs/simpleTypes',
                ],
                (object) [
                    'type' => 'array',
                    'items' => (object) [
                        '$ref' => '#/$defs/simpleTypes',
                    ],
                    'minItems' => 1,
                    'uniqueItems' => true,
                ],
            ],
        ],
        'const' => true,
        'enum' => (object) [
            'type' => 'array',
            'items' => true,
        ],
        'multipleOf' => (object) [
            'type' => 'number',
            'exclusiveMinimum' => 0,
        ],
        'maximum' => (object) [
            'type' => 'number',
        ],
        'exclusiveMaximum' => (object) [
            'type' => 'number',
        ],
        'minimum' => (object) [
            'type' => 'number',
        ],
        'exclusiveMinimum' => (object) [
            'type' => 'number',
        ],
        'maxLength' => (object) [
            '$ref' => '#/$defs/nonNegativeInteger',
        ],
        'minLength' => (object) [
            '$ref' => '#/$defs/nonNegativeIntegerDefault0',
        ],
        'pattern' => (object) [
            'type' => 'string',
            'format' => 'regex',
        ],
        'maxItems' => (object) [
            '$ref' => '#/$defs/nonNegativeInteger',
        ],
        'minItems' => (object) [
            '$ref' => '#/$defs/nonNegativeIntegerDefault0',
        ],
        'uniqueItems' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        'maxContains' => (object) [
            '$ref' => '#/$defs/nonNegativeInteger',
        ],
        'minContains' => (object) [
            '$ref' => '#/$defs/nonNegativeInteger',
            'default' => 1,
        ],
        'maxProperties' => (object) [
            '$ref' => '#/$defs/nonNegativeInteger',
        ],
        'minProperties' => (object) [
            '$ref' => '#/$defs/nonNegativeIntegerDefault0',
        ],
        'required' => (object) [
            '$ref' => '#/$defs/stringArray',
        ],
        'dependentRequired' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$ref' => '#/$defs/stringArray',
            ],
        ],
    ],
    '$defs' => (object) [
        'nonNegativeInteger' => (object) [
            'type' => 'integer',
            'minimum' => 0,
        ],
        'nonNegativeIntegerDefault0' => (object) [
            '$ref' => '#/$defs/nonNegativeInteger',
            'default' => 0,
        ],
        'simpleTypes' => (object) [
            'enum' => [
                'array',
                'boolean',
                'integer',
                'null',
                'number',
                'object',
                'string',
            ],
        ],
        'stringArray' => (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'string',
            ],
            'uniqueItems' => true,
            'default' => [],
        ],
    ],
];
