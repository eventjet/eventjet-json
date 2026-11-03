<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Attribute;

/**
 * Marks the public property that collects every member of a JSON object that no declared field claims, keyed by member
 * name. Declare it as `array<array-key, JsonValue>`. The class must implement JsonSerializable and write these members
 * next to its declared fields, as MappedJsonFields does.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class OtherMembers {}
