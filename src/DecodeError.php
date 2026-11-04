<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\FieldPath;
use JsonException;
use RuntimeException;
use Throwable;

use function assert;

/** @api */
final class DecodeError extends RuntimeException
{
    /** The class whose object could not be created, or null while the error names none. */
    private string|null $class = null;

    /**
     * The path of an invalid value below the value that the decoder currently handles. Each enclosing object, list, and
     * map adds its part while the error leaves it, so that decoding valid values builds no paths. The message shows the
     * path once whileCreating() renders it, which every object and every root decode calls.
     */
    private string $path = '';

    /** Whether the path starts with a member name, which needs a dot before it once something adds to the front. */
    private bool $startsWithMember = false;

    /** For an invalid value, the text after its path, from which whileCreating() renders the message. */
    private string|null $detail = null;

    /**
     * Errors of invalid values come from this constructor, with arguments by position: a named constructor adds a frame
     * to the trace that each exception records, and named arguments add work, which both make rejected values
     * measurably slower. For the same reason, it passes the parent constructor the default code instead of naming the
     * previous exception.
     *
     * @param bool $afterPath Whether the error is about an invalid value and the message is the text after its path,
     *     such as "must be of type int, string given." The message names the path once whileCreating() renders it.
     * @internal
     */
    public function __construct(
        public readonly DecodeErrorKind $kind,
        string $message,
        bool $afterPath = false,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->detail = $afterPath ? $message : null;
    }

    /**
     * Starts the path of a new error at the member of a JSON object that holds the invalid value. The objects around it
     * add their members through whileCreating().
     *
     * @internal
     */
    public function inMember(string $name): self
    {
        assert($this->path === '', description: 'Only a new error starts its path.');
        $this->prepend($name, true);
        return $this;
    }

    /**
     * Adds the key of the list item or map entry that holds the invalid value to the front of its path.
     *
     * @throws JsonException
     * @internal
     */
    public function inItem(int|string $key): self
    {
        $this->prepend(FieldPath::key($key), false);
        return $this;
    }

    /**
     * Names the class whose object could not be created, and renders the path of an invalid value into the message. An
     * error that already names a class, such as an error from a nested object, keeps it.
     *
     * @param string|null $member The member of the object that holds the invalid value, which this adds to the front of
     *     the path, so that each object that the error leaves needs one call.
     * @internal
     */
    public function whileCreating(string $class, string|null $member = null): self
    {
        if ($member !== null) {
            $this->prepend($member, true);
        }
        if ($this->detail === null) {
            if ($this->class === null) {
                $this->class = $class;
                $this->message = 'Could not create ' . $class . ' from the JSON object: ' . $this->getMessage();
            }
            return $this;
        }
        $this->class ??= $class;
        $this->message =
            'Could not create ' . $this->class . ' from the JSON object: Field ' . $this->path . ' ' . $this->detail;
        return $this;
    }

    private function prepend(string $segment, bool $isMember): void
    {
        $this->path = $this->startsWithMember ? $segment . '.' . $this->path : $segment . $this->path;
        $this->startsWithMember = $isMember;
    }
}
