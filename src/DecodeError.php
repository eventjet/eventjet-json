<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\FieldPath;
use JsonException;
use RuntimeException;
use Throwable;

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

    /** @internal */
    public function __construct(
        public readonly DecodeErrorKind $kind,
        string $message,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /**
     * Creates the error of an invalid value. Its message names the path of the value once whileCreating() renders it.
     *
     * @param string $detail The text after the path, such as "must be of type int, string given."
     * @internal
     */
    public static function invalidValue(string $detail): self
    {
        $error = new self(DecodeErrorKind::InvalidValue, $detail);
        $error->detail = $detail;
        return $error;
    }

    /**
     * Adds the member of a JSON object that holds the invalid value to the front of its path.
     *
     * @internal
     */
    public function inMember(string $name): self
    {
        $this->path = $this->startsWithMember ? $name . '.' . $this->path : $name . $this->path;
        $this->startsWithMember = true;
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
        $key = FieldPath::key($key);
        $this->path = $this->startsWithMember ? $key . '.' . $this->path : $key . $this->path;
        $this->startsWithMember = false;
        return $this;
    }

    /**
     * Names the class whose object could not be created, and renders the path of an invalid value into the message. An
     * error that already names a class, such as an error from a nested object, keeps it.
     *
     * @internal
     */
    public function whileCreating(string $class): self
    {
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
}
