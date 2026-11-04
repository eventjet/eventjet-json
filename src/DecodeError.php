<?php

declare(strict_types=1);

namespace Eventjet\Json;

use Eventjet\Json\Internal\FieldPath;
use JsonException;
use RuntimeException;
use Throwable;

use function substr;

/** @api */
final class DecodeError extends RuntimeException
{
    /** The class whose object could not be created, or null while the error names none. */
    private string|null $class = null;

    /**
     * The path of an invalid value below the value that the decoder currently handles. Each enclosing object, list, and
     * map adds its part while the error leaves it, so that decoding valid values builds no paths. The message shows the
     * path once whileCreating() renders it, which every object and every root decode calls. Each member name keeps the
     * dot before it, which rendering removes from the front of the path.
     */
    private string $path = '';

    /**
     * @param string|null $detail For an invalid value, the text after its path. whileCreating() renders the message from
     *     it. Null for an error without a path.
     * @internal
     */
    public function __construct(
        public readonly DecodeErrorKind $kind,
        string $message = '',
        Throwable|null $previous = null,
        private readonly string|null $detail = null,
    ) {
        parent::__construct($detail ?? $message, previous: $previous);
    }

    /**
     * Adds the member of a JSON object that holds the invalid value to the front of its path.
     *
     * @internal
     */
    public function inMember(string $name): self
    {
        $this->path = '.' . $name . $this->path;
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
        $this->path = FieldPath::key($key) . $this->path;
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
        $path = $this->path !== '' && $this->path[0] === '.' ? substr($this->path, offset: 1) : $this->path;
        $this->message =
            'Could not create ' . $this->class . ' from the JSON object: Field ' . $path . ' ' . $this->detail;
        return $this;
    }
}
