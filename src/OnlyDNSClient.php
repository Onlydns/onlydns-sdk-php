<?php

declare(strict_types=1);

namespace OnlyDNS;

use OnlyDNS\Exceptions\ApiException;
use OnlyDNS\Exceptions\AuthenticationException;
use OnlyDNS\Exceptions\NotFoundException;
use OnlyDNS\Exceptions\OnlyDNSException;
use OnlyDNS\Exceptions\ValidationException;
use OnlyDNS\Models\DNSRecord;
use OnlyDNS\Models\DNSSECStatus;
use OnlyDNS\Models\RecordType;
use OnlyDNS\Models\TsigAlgorithm;
use OnlyDNS\Models\TsigKey;

class OnlyDNSClient
{
    public const DEFAULT_BASE_URL = 'https://api.onlydns.com/v1';

    private readonly string $apiToken;
    private readonly string $baseUrl;
    private readonly float $timeout;

    public function __construct(
        ?string $apiToken = null,
        ?string $baseUrl = null,
        float $timeout = 30.0
    ) {
        $token = $apiToken
            ?? (getenv('ONLYDNS_API_KEY') ?: null)
            ?? (getenv('ONLYDNS_API_TOKEN') ?: null)
            ?? ($_ENV['ONLYDNS_API_KEY'] ?? null)
            ?? ($_ENV['ONLYDNS_API_TOKEN'] ?? null);

        if (!$token) {
            throw new AuthenticationException(
                'API token is required. Pass apiToken or set the ONLYDNS_API_KEY environment variable.'
            );
        }

        $this->apiToken = (string) $token;
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $timeout;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array<string, mixed> $params
     * @param mixed $body
     * @return mixed
     * @throws OnlyDNSException
     */
    protected function request(
        string $method,
        string $path,
        array $params = [],
        mixed $body = null
    ): mixed {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($params)) {
            $filtered = array_filter($params, fn($v) => $v !== null);
            if (!empty($filtered)) {
                $url .= '?' . http_build_query($filtered);
            }
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Accept: application/json',
            'User-Agent: OnlyDNS-PHP-SDK/1.0.0',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) ceil($this->timeout));

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $payload = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false && $httpCode === 0) {
            throw new OnlyDNSException("cURL network error: $curlError");
        }

        if ($httpCode === 204 || $responseBody === '' || $responseBody === false) {
            return null;
        }

        $decoded = null;
        if (is_string($responseBody) && $responseBody !== '') {
            $decoded = json_decode($responseBody, true);
        }

        if ($httpCode >= 400) {
            $title = '';
            $details = [];
            if (is_array($decoded)) {
                $title = (string) ($decoded['title'] ?? '');
                if (isset($decoded['description'])) {
                    if (is_array($decoded['description'])) {
                        $details = array_map('strval', $decoded['description']);
                    } else {
                        $details = [(string) $decoded['description']];
                    }
                }
            }

            $message = $title !== '' ? $title : "HTTP $httpCode error";
            return match ($httpCode) {
                400 => throw new ValidationException($message, $httpCode, $details),
                401 => throw new AuthenticationException($message ?: 'Access token is missing or invalid', $httpCode, $details),
                404 => throw new NotFoundException($message ?: 'Resource not found', $httpCode, $details),
                default => throw new ApiException($message, $httpCode, $details),
            };
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Domains & DNS Records API
    // -------------------------------------------------------------------------

    /**
     * Create a new DNS domain.
     *
     * @param string $domain
     * @return void
     */
    public function createDomain(string $domain): void
    {
        $this->request('POST', '/dns', [], ['domain' => $domain]);
    }

    /**
     * List all domain names in the account.
     *
     * @param int $offset
     * @param int $limit
     * @return array<string>
     */
    public function listDomains(int $offset = 0, int $limit = 200): array
    {
        $res = $this->request('GET', '/dns', [
            'offset' => $offset,
            'limit' => $limit,
        ]);

        return is_array($res) ? array_map('strval', $res) : [];
    }

    /**
     * Get all DNS records for a domain.
     *
     * @param string $domainName
     * @return array<DNSRecord>
     */
    public function getRecords(string $domainName): array
    {
        $encoded = rawurlencode($domainName);
        $res = $this->request('GET', "/dns/$encoded/records");

        if (!is_array($res)) {
            return [];
        }

        $records = [];
        foreach ($res as $item) {
            if (is_array($item)) {
                $records[] = DNSRecord::fromArray($item);
            }
        }
        return $records;
    }

    /**
     * Replace all DNS records on a domain (atomic bulk replace).
     *
     * @param string $domainName
     * @param array<DNSRecord|array<string, mixed>> $records
     * @return void
     */
    public function replaceRecords(string $domainName, array $records): void
    {
        $payload = array_map(function ($record) {
            if ($record instanceof DNSRecord) {
                return $record->toArray();
            }
            return (array) $record;
        }, $records);

        $encoded = rawurlencode($domainName);
        $this->request('PUT', "/dns/$encoded/records", [], $payload);
    }

    // -------------------------------------------------------------------------
    // High-Level Atomic Record Helpers
    // -------------------------------------------------------------------------

    /**
     * Add or merge DNS record(s) on a domain.
     *
     * @param string $domainName
     * @param string $name Hostname (e.g. '@', 'www', 'api')
     * @param RecordType|string $type
     * @param string|array<string> $records Value or list of values
     * @param int $ttl
     * @return array<DNSRecord> Updated records list
     */
    public function addRecord(
        string $domainName,
        string $name,
        RecordType|string $type,
        string|array $records,
        int $ttl = 300
    ): array {
        $typeStr = strtoupper($type instanceof RecordType ? $type->value : (string) $type);
        $values = is_array($records) ? array_values($records) : [(string) $records];

        $currentRecords = $this->getRecords($domainName);
        $found = false;

        foreach ($currentRecords as $record) {
            if (
                strcasecmp($record->name, $name) === 0 &&
                strcasecmp($record->getTypeString(), $typeStr) === 0
            ) {
                foreach ($values as $val) {
                    if (!in_array($val, $record->records, true)) {
                        $record->records[] = $val;
                    }
                }
                $record->ttl = $ttl;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $currentRecords[] = new DNSRecord(
                name: $name,
                type: RecordType::tryFrom($typeStr) ?? $typeStr,
                ttl: $ttl,
                records: $values
            );
        }

        $this->replaceRecords($domainName, $currentRecords);
        return $currentRecords;
    }

    /**
     * Remove matching DNS record(s) or a specific record value from a domain.
     *
     * @param string $domainName
     * @param string $name Hostname
     * @param RecordType|string|null $type Optional type filter
     * @param string|null $value Optional specific value to remove
     * @return array<DNSRecord> Updated records list
     */
    public function removeRecord(
        string $domainName,
        string $name,
        RecordType|string|null $type = null,
        ?string $value = null
    ): array {
        $typeStr = $type !== null
            ? strtoupper($type instanceof RecordType ? $type->value : (string) $type)
            : null;

        $currentRecords = $this->getRecords($domainName);
        $updated = [];

        foreach ($currentRecords as $record) {
            $nameMatches = strcasecmp($record->name, $name) === 0;
            $typeMatches = $typeStr === null || strcasecmp($record->getTypeString(), $typeStr) === 0;

            if ($nameMatches && $typeMatches) {
                if ($value !== null) {
                    $record->records = array_values(array_filter(
                        $record->records,
                        fn($v) => $v !== $value
                    ));
                    if (!empty($record->records)) {
                        $updated[] = $record;
                    }
                }
                // If value is null, record is discarded
            } else {
                $updated[] = $record;
            }
        }

        $this->replaceRecords($domainName, $updated);
        return $updated;
    }

    /**
     * Overwrite values and TTL for a specific hostname + type combination.
     *
     * @param string $domainName
     * @param string $name
     * @param RecordType|string $type
     * @param string|array<string> $records
     * @param int|null $ttl
     * @return array<DNSRecord> Updated records list
     */
    public function updateRecord(
        string $domainName,
        string $name,
        RecordType|string $type,
        string|array $records,
        ?int $ttl = null
    ): array {
        $typeStr = strtoupper($type instanceof RecordType ? $type->value : (string) $type);
        $values = is_array($records) ? array_values($records) : [(string) $records];

        $currentRecords = $this->getRecords($domainName);
        $found = false;

        foreach ($currentRecords as $record) {
            if (
                strcasecmp($record->name, $name) === 0 &&
                strcasecmp($record->getTypeString(), $typeStr) === 0
            ) {
                $record->records = $values;
                if ($ttl !== null) {
                    $record->ttl = $ttl;
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            $currentRecords[] = new DNSRecord(
                name: $name,
                type: RecordType::tryFrom($typeStr) ?? $typeStr,
                ttl: $ttl ?? 300,
                records: $values
            );
        }

        $this->replaceRecords($domainName, $currentRecords);
        return $currentRecords;
    }

    /**
     * Filter records on a domain without changing zone data.
     *
     * @param string $domainName
     * @param string|null $name
     * @param RecordType|string|null $type
     * @param string|null $value
     * @return array<DNSRecord>
     */
    public function findRecords(
        string $domainName,
        ?string $name = null,
        RecordType|string|null $type = null,
        ?string $value = null
    ): array {
        $typeStr = $type !== null
            ? strtoupper($type instanceof RecordType ? $type->value : (string) $type)
            : null;
        $nameLower = $name !== null ? strtolower(trim($name)) : null;
        $valLower = $value !== null ? strtolower(trim($value)) : null;

        $records = $this->getRecords($domainName);
        $filtered = [];

        foreach ($records as $record) {
            if ($nameLower !== null && !str_contains(strtolower($record->name), $nameLower)) {
                continue;
            }
            if ($typeStr !== null && strcasecmp($record->getTypeString(), $typeStr) !== 0) {
                continue;
            }
            if ($valLower !== null) {
                $matched = false;
                foreach ($record->records as $v) {
                    if (str_contains(strtolower($v), $valLower)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            }
            $filtered[] = $record;
        }

        return $filtered;
    }

    // -------------------------------------------------------------------------
    // TSIG Key Management API
    // -------------------------------------------------------------------------

    /**
     * Get all DNS TSIG keys for the account.
     *
     * @return array<TsigKey>
     */
    public function getTsigKeys(): array
    {
        $res = $this->request('GET', '/dns/tsig');
        if (is_array($res) && isset($res['tsigKeys']) && is_array($res['tsigKeys'])) {
            return array_map(fn($k) => TsigKey::fromArray($k), $res['tsigKeys']);
        }
        return [];
    }

    /**
     * Create a new TSIG key for the account.
     *
     * @param TsigAlgorithm|string|null $algorithm
     * @return TsigKey
     */
    public function createTsigKey(TsigAlgorithm|string|null $algorithm = null): TsigKey
    {
        $body = [];
        if ($algorithm !== null) {
            $body['algorithm'] = $algorithm instanceof TsigAlgorithm ? $algorithm->value : (string) $algorithm;
        }

        $res = $this->request('POST', '/dns/tsig', [], !empty($body) ? $body : null);
        return TsigKey::fromArray((array) $res);
    }

    /**
     * Get details for a specific TSIG key by name.
     *
     * @param string $keyName
     * @return TsigKey
     */
    public function getTsigKey(string $keyName): TsigKey
    {
        $encoded = rawurlencode($keyName);
        $res = $this->request('GET', "/dns/tsig/$encoded");
        return TsigKey::fromArray((array) $res);
    }

    /**
     * Delete all TSIG keys from a domain.
     *
     * @param string $domainName
     * @return void
     */
    public function deleteDomainTsig(string $domainName): void
    {
        $encoded = rawurlencode($domainName);
        $this->request('DELETE', "/dns/$encoded/tsig");
    }

    /**
     * Update TSIG key permissions on a domain.
     *
     * @param string $domainName
     * @param string $keyName
     * @param bool $allowAxfr
     * @param bool $allowDnsUpdate
     * @return void
     */
    public function updateDomainTsig(
        string $domainName,
        string $keyName,
        bool $allowAxfr,
        bool $allowDnsUpdate
    ): void {
        $encodedDomain = rawurlencode($domainName);
        $encodedKey = rawurlencode($keyName);
        $this->request('PATCH', "/dns/$encodedDomain/tsig/$encodedKey", [], [
            'allowAxfr' => $allowAxfr,
            'allowDnsUpdate' => $allowDnsUpdate,
        ]);
    }

    // -------------------------------------------------------------------------
    // DNSSEC Management API
    // -------------------------------------------------------------------------

    /**
     * Get DNSSEC status and DS keys for a domain.
     *
     * @param string $domainName
     * @return DNSSECStatus
     */
    public function getDnssecStatus(string $domainName): DNSSECStatus
    {
        $encoded = rawurlencode($domainName);
        $res = $this->request('GET', "/dns/$encoded/dnssec");
        return DNSSECStatus::fromArray(is_array($res) ? $res : []);
    }

    /**
     * Enable DNSSEC for a domain.
     *
     * @param string $domainName
     * @return void
     */
    public function enableDnssec(string $domainName): void
    {
        $encoded = rawurlencode($domainName);
        $this->request('POST', "/dns/$encoded/dnssec");
    }

    /**
     * Disable DNSSEC for a domain.
     *
     * @param string $domainName
     * @return void
     */
    public function disableDnssec(string $domainName): void
    {
        $encoded = rawurlencode($domainName);
        $this->request('DELETE', "/dns/$encoded/dnssec");
    }
}
