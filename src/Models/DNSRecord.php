<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

class DNSRecord
{
    /**
     * @param string $name
     * @param RecordType|string $type
     * @param int $ttl
     * @param array<string> $records
     */
    public function __construct(
        public string $name,
        public RecordType|string $type,
        public int $ttl,
        public array $records = []
    ) {
    }

    public function getTypeString(): string
    {
        return $this->type instanceof RecordType ? $this->type->value : (string) $this->type;
    }

    /**
     * @return array{name: string, type: string, ttl: int, records: array<string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->getTypeString(),
            'ttl' => $this->ttl,
            'records' => array_values($this->records),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $typeVal = (string) ($data['type'] ?? 'A');
        $type = RecordType::tryFrom($typeVal) ?? $typeVal;

        return new self(
            name: (string) ($data['name'] ?? '@'),
            type: $type,
            ttl: (int) ($data['ttl'] ?? 300),
            records: isset($data['records']) && is_array($data['records']) ? array_map('strval', $data['records']) : []
        );
    }
}
