<?php

// Generated from schema.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    '$id' => 'https://json-schema.org/draft/2020-12/schema',
    '$vocabulary' => (object) [
        'https://json-schema.org/draft/2020-12/vocab/core' => true,
        'https://json-schema.org/draft/2020-12/vocab/applicator' => true,
        'https://json-schema.org/draft/2020-12/vocab/unevaluated' => true,
        'https://json-schema.org/draft/2020-12/vocab/validation' => true,
        'https://json-schema.org/draft/2020-12/vocab/meta-data' => true,
        'https://json-schema.org/draft/2020-12/vocab/format-annotation' => true,
        'https://json-schema.org/draft/2020-12/vocab/content' => true,
    ],
    '$dynamicAnchor' => 'meta',
    'title' => 'Core and Validation specifications meta-schema',
    'allOf' => [
        (object) [
            '$ref' => 'meta/core',
        ],
        (object) [
            '$ref' => 'meta/applicator',
        ],
        (object) [
            '$ref' => 'meta/unevaluated',
        ],
        (object) [
            '$ref' => 'meta/validation',
        ],
        (object) [
            '$ref' => 'meta/meta-data',
        ],
        (object) [
            '$ref' => 'meta/format-annotation',
        ],
        (object) [
            '$ref' => 'meta/content',
        ],
    ],
    'type' => [
        'object',
        'boolean',
    ],
    '$comment' => 'This meta-schema also defines keywords that have appeared in previous drafts in order to prevent incompatible extensions as they remain in common use.',
    'properties' => (object) [
        'definitions' => (object) [
            '$comment' => '"definitions" has been replaced by "$defs".',
            'type' => 'object',
            'additionalProperties' => (object) [
                '$dynamicRef' => '#meta',
            ],
            'deprecated' => true,
            'default' => (object) [],
        ],
        'dependencies' => (object) [
            '$comment' => '"dependencies" has been split and replaced by "dependentSchemas" and "dependentRequired" in order to serve their differing semantics.',
            'type' => 'object',
            'additionalProperties' => (object) [
                'anyOf' => [
                    (object) [
                        '$dynamicRef' => '#meta',
                    ],
                    (object) [
                        '$ref' => 'meta/validation#/$defs/stringArray',
                    ],
                ],
            ],
            'deprecated' => true,
            'default' => (object) [],
        ],
        '$recursiveAnchor' => (object) [
            '$comment' => '"$recursiveAnchor" has been replaced by "$dynamicAnchor".',
            '$ref' => 'meta/core#/$defs/anchorString',
            'deprecated' => true,
        ],
        '$recursiveRef' => (object) [
            '$comment' => '"$recursiveRef" has been replaced by "$dynamicRef".',
            '$ref' => 'meta/core#/$defs/uriReferenceString',
            'deprecated' => true,
        ],
    ],
];
