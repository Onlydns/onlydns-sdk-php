<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

class DNSSECStatus
{
    /**
     * @param bool $enabled
     * @param string|null $nsec3param
     * @param array<DNSSECDsKey> $keys
     */
    public function __construct(
        public bool $enabled = false,
        public ?string $nsec3param = null,
        public array $keys = []
    ) {
    }

    /**
     * @return array{enabled: bool, nsec3param?: string|null, keys: array<array{keyTag: int, algorithm: int, digestType: int, digest: string}>}
     */
    public function toArray(): array
    {
        $data = [
            'enabled' => $this->enabled,
            'keys' => array_map(fn (DNSSECDsKey $k) => $k->toArray(), $this->keys),
        ];
        if ($this->nsec3param !== null) {
            $data['nsec3param'] = $this->nsec3param;
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $rawKeys = $data['keys'] ?? [];
        $keys = [];
        if (is_array($rawKeys)) {
            foreach ($rawKeys as $k) {
                if (is_array($k)) {
                    $keys[] = DNSSECDsKey::fromArray($k);
                }
            }
        }

        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            nsec3param: isset($data['nsec3param']) ? (string) $data['nsec3param'] : null,
            keys: $keys
        );
    }
}
