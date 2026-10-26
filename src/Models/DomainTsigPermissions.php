<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

class DomainTsigPermissions
{
    /**
     * @param bool $allowAxfr
     * @param bool $allowDnsUpdate
     * @param array<string>|null $subdomains
     */
    public function __construct(
        public bool $allowAxfr,
        public bool $allowDnsUpdate,
        public ?array $subdomains = null
    ) {
    }

    /**
     * @return array{allowAxfr: bool, allowDnsUpdate: bool, subdomains?: array<string>}
     */
    public function toArray(): array
    {
        $data = [
            'allowAxfr' => $this->allowAxfr,
            'allowDnsUpdate' => $this->allowDnsUpdate,
        ];

        if ($this->subdomains !== null) {
            $data['subdomains'] = $this->subdomains;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            allowAxfr: (bool) ($data['allowAxfr'] ?? false),
            allowDnsUpdate: (bool) ($data['allowDnsUpdate'] ?? false),
            subdomains: isset($data['subdomains']) && is_array($data['subdomains']) ? array_values($data['subdomains']) : null
        );
    }
}
