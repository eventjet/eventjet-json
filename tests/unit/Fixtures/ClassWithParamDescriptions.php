<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Fixtures;

final class ClassWithParamDescriptions
{
    /**
     * @param string $name The user's full name
     * @param int $age The user's age in years
     * @param string $email The user's email address
     * @param string $notes Additional notes
     */
    public function __construct(
        public string $name,
        /**
         * The age of the person.
         *
         * Must be positive.
         */
        public int $age,
        /**
         * @var string
         */
        public string $email,
        public string $notes,
    ) {
    }
}
