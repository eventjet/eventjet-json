<?php

// Generated from content.json by .github/ci/meta-schemas.php. Do not edit.

declare(strict_types=1);

return (object) [
    '$schema' => 'https://json-schema.org/draft/2019-09/schema',
    '$id' => 'https://json-schema.org/draft/2019-09/meta/content',
    '$recursiveAnchor' => true,
    'title' => 'Content vocabulary meta-schema',
    'type' => [
        'object',
        'boolean',
    ],
    'properties' => (object) [
        'contentMediaType' => (object) [
            'type' => 'string',
        ],
        'contentEncoding' => (object) [
            'type' => 'string',
        ],
        'contentSchema' => (object) [
            '$recursiveRef' => '#',
        ],
    ],
];
