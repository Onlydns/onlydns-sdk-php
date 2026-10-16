<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

class TsigKey
{
    public function __construct(
        public string $name,
        public TsigAlgorithm|string $algorithm,
        public string $key
    ) {
    }

    public function getAlgorithmString(): string
    {
        return $this->algorithm instanceof TsigAlgorithm ? $this->algorithm->value : (string) $this->algorithm;
    }

    /**
     * @return array{name: string, algorithm: string, key: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'algorithm' => $this->getAlgorithmString(),
            'key' => $this->key,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $algVal = (string) ($data['algorithm'] ?? 'hmac-sha256');
        $algorithm = TsigAlgorithm::tryFrom($algVal) ?? $algVal;

        return new self(
            name: (string) ($data['name'] ?? ''),
            algorithm: $algorithm,
            key: (string) ($data['key'] ?? '')
        );
    }
}
