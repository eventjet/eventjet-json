<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

/**
 * A complex entity.
 *
 * This is the first paragraph
 * that spans multiple lines.
 *
 * This is the second paragraph.
 */
final class ClassWithMultiParagraphDescription
{
    public function __construct(
        public string $id,
    ) {
    }
}
