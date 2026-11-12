<?php

// Generated from schema.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'http://json-schema.org/draft-07/schema#',
    '$id' => 'http://json-schema.org/draft-07/schema#',
    'title' => 'Core schema meta-schema',
    'definitions' => (object) [
        'schemaArray' => (object) [
            'type' => 'array',
            'minItems' => 1,
            'items' => (object) [
                '$ref' => '#',
            ],
        ],
        'nonNegativeInteger' => (object) [
            'type' => 'integer',
            'minimum' => 0,
        ],
        'nonNegativeIntegerDefault0' => (object) [
            'allOf' => [
                (object) [
                    '$ref' => '#/definitions/nonNegativeInteger',
                ],
                (object) [
                    'default' => 0,
                ],
            ],
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
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        '$id' => (object) [
            'type' => 'string',
            'format' => 'uri-reference',
        ],
        '$schema' => (object) [
            'type' => 'string',
            'format' => 'uri',
        ],
        '$ref' => (object) [
            'type' => 'string',
            'format' => 'uri-reference',
        ],
        '$comment' => (object) [
            'type' => 'string',
        ],
        'title' => (object) [
            'type' => 'string',
        ],
        'description' => (object) [
            'type' => 'string',
        ],
        'default' => true,
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
            '$ref' => '#/definitions/nonNegativeInteger',
        ],
        'minLength' => (object) [
            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
        ],
        'pattern' => (object) [
            'type' => 'string',
            'format' => 'regex',
        ],
        'additionalItems' => (object) [
            '$ref' => '#',
        ],
        'items' => (object) [
            'anyOf' => [
                (object) [
                    '$ref' => '#',
                ],
                (object) [
                    '$ref' => '#/definitions/schemaArray',
                ],
            ],
            'default' => true,
        ],
        'maxItems' => (object) [
            '$ref' => '#/definitions/nonNegativeInteger',
        ],
        'minItems' => (object) [
            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
        ],
        'uniqueItems' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        'contains' => (object) [
            '$ref' => '#',
        ],
        'maxProperties' => (object) [
            '$ref' => '#/definitions/nonNegativeInteger',
        ],
        'minProperties' => (object) [
            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
        ],
        'required' => (object) [
            '$ref' => '#/definitions/stringArray',
        ],
        'additionalProperties' => (object) [
            '$ref' => '#',
        ],
        'definitions' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$ref' => '#',
            ],
            'default' => (object) [],
        ],
        'properties' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$ref' => '#',
            ],
            'default' => (object) [],
        ],
        'patternProperties' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                '$ref' => '#',
            ],
            'propertyNames' => (object) [
                'format' => 'regex',
            ],
            'default' => (object) [],
        ],
        'dependencies' => (object) [
            'type' => 'object',
            'additionalProperties' => (object) [
                'anyOf' => [
                    (object) [
                        '$ref' => '#',
                    ],
                    (object) [
                        '$ref' => '#/definitions/stringArray',
                    ],
                ],
            ],
        ],
        'propertyNames' => (object) [
            '$ref' => '#',
        ],
        'const' => true,
        'enum' => (object) [
            'type' => 'array',
            'items' => true,
            'minItems' => 1,
            'uniqueItems' => true,
        ],
        'type' => (object) [
            'anyOf' => [
                (object) [
                    '$ref' => '#/definitions/simpleTypes',
                ],
                (object) [
                    'type' => 'array',
                    'items' => (object) [
                        '$ref' => '#/definitions/simpleTypes',
                    ],
                    'minItems' => 1,
                    'uniqueItems' => true,
                ],
            ],
        ],
        'format' => (object) [
            'type' => 'string',
        ],
        'contentMediaType' => (object) [
            'type' => 'string',
        ],
        'contentEncoding' => (object) [
            'type' => 'string',
        ],
        'if' => (object) [
            '$ref' => '#',
        ],
        'then' => (object) [
            '$ref' => '#',
        ],
        'else' => (object) [
            '$ref' => '#',
        ],
        'allOf' => (object) [
            '$ref' => '#/definitions/schemaArray',
        ],
        'anyOf' => (object) [
            '$ref' => '#/definitions/schemaArray',
        ],
        'oneOf' => (object) [
            '$ref' => '#/definitions/schemaArray',
        ],
        'not' => (object) [
            '$ref' => '#',
        ],
    ],
    'default' => true,
];
