<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

class DNSSECDsKey
{
    public function __construct(
        public int $keyTag,
        public DNSSECKeyAlgorithm|int $algorithm,
        public DNSSECKeyDigestType|int $digestType,
        public string $digest
    ) {
    }

    public function getAlgorithmInt(): int
    {
        return $this->algorithm instanceof DNSSECKeyAlgorithm ? $this->algorithm->value : (int) $this->algorithm;
    }

    public function getDigestTypeInt(): int
    {
        return $this->digestType instanceof DNSSECKeyDigestType ? $this->digestType->value : (int) $this->digestType;
    }

    /**
     * @return array{keyTag: int, algorithm: int, digestType: int, digest: string}
     */
    public function toArray(): array
    {
        return [
            'keyTag' => $this->keyTag,
            'algorithm' => $this->getAlgorithmInt(),
            'digestType' => $this->getDigestTypeInt(),
            'digest' => $this->digest,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $algInt = (int) ($data['algorithm'] ?? 0);
        $alg = DNSSECKeyAlgorithm::tryFrom($algInt) ?? $algInt;

        $dtInt = (int) ($data['digestType'] ?? 0);
        $dt = DNSSECKeyDigestType::tryFrom($dtInt) ?? $dtInt;

        return new self(
            keyTag: (int) ($data['keyTag'] ?? 0),
            algorithm: $alg,
            digestType: $dt,
            digest: (string) ($data['digest'] ?? '')
        );
    }
}
