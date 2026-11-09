<?php

// Generated from content.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2020-12/schema',
    '$id' => 'https://json-schema.org/draft/2020-12/meta/content',
    '$dynamicAnchor' => 'meta',
    'title' => 'Content vocabulary meta-schema',
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        'contentEncoding' => (object) [
            'type' => 'string',
        ],
        'contentMediaType' => (object) [
            'type' => 'string',
        ],
        'contentSchema' => (object) [
            '$dynamicRef' => '#meta',
        ],
    ],
];
