<?php

// Generated from schema.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2019-09/schema',
    '$id' => 'https://json-schema.org/draft/2019-09/schema',
    '$vocabulary' => (object) [
        'https://json-schema.org/draft/2019-09/vocab/core' => true,
        'https://json-schema.org/draft/2019-09/vocab/applicator' => true,
        'https://json-schema.org/draft/2019-09/vocab/validation' => true,
        'https://json-schema.org/draft/2019-09/vocab/meta-data' => true,
        'https://json-schema.org/draft/2019-09/vocab/format' => false,
        'https://json-schema.org/draft/2019-09/vocab/content' => true,
    ],
    '$recursiveAnchor' => true,
    'title' => 'Core and Validation specifications meta-schema',
    'allOf' => [
        (object) [
            '$ref' => 'meta/core',
        ],
        (object) [
            '$ref' => 'meta/applicator',
        ],
        (object) [
            '$ref' => 'meta/validation',
        ],
        (object) [
            '$ref' => 'meta/meta-data',
        ],
        (object) [
            '$ref' => 'meta/format',
        ],
        (object) [
            '$ref' => 'meta/content',
        ],
    ],
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        'definitions' => (object) [
            '$comment' => 'While no longer an official keyword as it is replaced by $defs, this keyword is retained in the meta-schema to prevent incompatible extensions as it remains in common use.',
            'type' => 'object',
            'additionalProperties' => (object) [
                '$recursiveRef' => '#',
            ],
            'default' => (object) [],
        ],
        'dependencies' => (object) [
            '$comment' => '"dependencies" is no longer a keyword, but schema authors should avoid redefining it to facilitate a smooth transition to "dependentSchemas" and "dependentRequired"',
            'type' => 'object',
            'additionalProperties' => (object) [
                'anyOf' => [
                    (object) [
                        '$recursiveRef' => '#',
                    ],
                    (object) [
                        '$ref' => 'meta/validation#/$defs/stringArray',
                    ],
                ],
            ],
        ],
    ],
];
