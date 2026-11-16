<?php

// Generated from schema.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    'id' => 'http://json-schema.org/draft-04/schema#',
    '$schema' => 'http://json-schema.org/draft-04/schema#',
    'description' => 'Core schema meta-schema',
    'definitions' => (object) [
        'schemaArray' => (object) [
            'type' => 'array',
            'minItems' => 1,
            'items' => (object) [
                '$ref' => '#',
            ],
        ],
        'positiveInteger' => (object) [
            'type' => 'integer',
            'minimum' => 0,
        ],
        'positiveIntegerDefault0' => (object) [
            'allOf' => [
                (object) [
                    '$ref' => '#/definitions/positiveInteger',
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
            'minItems' => 1,
            'uniqueItems' => true,
        ],
    ],
    'type' => 'object',
    'properties' => (object) [
        'id' => (object) [
            'type' => 'string',
        ],
        '$schema' => (object) [
            'type' => 'string',
        ],
        'title' => (object) [
            'type' => 'string',
        ],
        'description' => (object) [
            'type' => 'string',
        ],
        'default' => (object) [],
        'multipleOf' => (object) [
            'type' => 'number',
            'minimum' => 0,
            'exclusiveMinimum' => true,
        ],
        'maximum' => (object) [
            'type' => 'number',
        ],
        'exclusiveMaximum' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        'minimum' => (object) [
            'type' => 'number',
        ],
        'exclusiveMinimum' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        'maxLength' => (object) [
            '$ref' => '#/definitions/positiveInteger',
        ],
        'minLength' => (object) [
            '$ref' => '#/definitions/positiveIntegerDefault0',
        ],
        'pattern' => (object) [
            'type' => 'string',
            'format' => 'regex',
        ],
        'additionalItems' => (object) [
            'anyOf' => [
                (object) [
                    'type' => 'boolean',
                ],
                (object) [
                    '$ref' => '#',
                ],
            ],
            'default' => (object) [],
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
            'default' => (object) [],
        ],
        'maxItems' => (object) [
            '$ref' => '#/definitions/positiveInteger',
        ],
        'minItems' => (object) [
            '$ref' => '#/definitions/positiveIntegerDefault0',
        ],
        'uniqueItems' => (object) [
            'type' => 'boolean',
            'default' => false,
        ],
        'maxProperties' => (object) [
            '$ref' => '#/definitions/positiveInteger',
        ],
        'minProperties' => (object) [
            '$ref' => '#/definitions/positiveIntegerDefault0',
        ],
        'required' => (object) [
            '$ref' => '#/definitions/stringArray',
        ],
        'additionalProperties' => (object) [
            'anyOf' => [
                (object) [
                    'type' => 'boolean',
                ],
                (object) [
                    '$ref' => '#',
                ],
            ],
            'default' => (object) [],
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
        'enum' => (object) [
            'type' => 'array',
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
    'dependencies' => (object) [
        'exclusiveMaximum' => [
            'maximum',
        ],
        'exclusiveMinimum' => [
            'minimum',
        ],
    ],
    'default' => (object) [],
];
