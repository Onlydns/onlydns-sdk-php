<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

class DomainTsigPermissions
{
    public function __construct(
        public bool $allowAxfr,
        public bool $allowDnsUpdate
    ) {
    }

    /**
     * @return array{allowAxfr: bool, allowDnsUpdate: bool}
     */
    public function toArray(): array
    {
        return [
            'allowAxfr' => $this->allowAxfr,
            'allowDnsUpdate' => $this->allowDnsUpdate,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            allowAxfr: (bool) ($data['allowAxfr'] ?? false),
            allowDnsUpdate: (bool) ($data['allowDnsUpdate'] ?? false)
        );
    }
}
