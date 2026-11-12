<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema\Regex;

use function html_entity_decode;
use function preg_match;

/**
 * Reads the name of a group or of a reference to one, such as `year` or `\u{1D400}`, after the `<`.
 *
 * @internal
 */
final readonly class GroupName
{
    private UnicodeEscape $unicode;

    public function __construct(
        private Cursor $cursor,
    ) {
        $this->unicode = new UnicodeEscape($cursor);
    }

    /** Reads the name up to and including the closing `>` and returns it with its escapes decoded. */
    public function read(): string|null
    {
        $name = '';
        while (true) {
            $char = $this->cursor->peek();
            if ($char === null) {
                return null;
            }
            $this->cursor->pos++;
            if ($char === '>') {
                break;
            }
            if ($char === '\\' && $this->cursor->peek() === 'u') {
                $this->cursor->pos++;
                // Leaves an invalid escape, a surrogate or a C1 control as an entity, which no name may contain.
                $char = html_entity_decode('&#' . ($this->unicode->read() ?? '') . ';');
            }
            $name .= $char;
        }
        $valid =
            preg_match('/^[\p{L}\p{Nl}$_][\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}$_\x{200C}\x{200D}]*$/uD', $name) === 1;
        return $valid ? $name : null;
    }
}
