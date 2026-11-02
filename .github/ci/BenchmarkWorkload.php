<?php

declare(strict_types=1);

namespace Eventjet\Json\Ci;

final readonly class BenchmarkWorkload
{
    public const REPORT_GROUPS = [
        'documents' => 'Realistic example documents',
        'batches' => 'Synthetic batches',
        'diagnostic' => 'Focused diagnostics',
        'stress' => 'Stress diagnostics',
        'errors' => 'Expected errors',
        'uncategorized' => 'Uncategorized workloads',
    ];

    public function __construct(
        public string $class,
        public string $subject,
        public string $parameter,
        public string $category,
        public bool $cold,
    ) {}

    /** Fresh CLI processes compile the whole library each time, which a long-running server does once per deploy. */
    public function blocks(): bool
    {
        return !$this->cold;
    }

    public function identity(): string
    {
        return $this->class . '::' . $this->subject . '[' . $this->parameter . ']';
    }

    /** @return array{string, string} */
    public function filters(): array
    {
        return [
            '--filter=^' . preg_quote($this->class . '::' . $this->subject, '{') . '$',
            '--variant=^' . preg_quote($this->parameter, '{') . '$',
        ];
    }
}
