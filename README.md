# OnlyDNS PHP SDK

Official PHP SDK for the [OnlyDNS System API](https://api-docs.onlydns.com/).

Designed for high developer utility and seamless AI agent tool calling in modern PHP 8.1+ applications.

## Features

- **PHP 8.1+ Native**: Strongly typed properties, Enums (`RecordType`, `TsigAlgorithm`), and strict types.
- **High-Level Atomic Helpers**: Simplify record updates via `addRecord()`, `removeRecord()`, `updateRecord()`, and `findRecords()`.
- **Zero Heavy Dependencies**: Uses standard PHP extensions (`ext-curl` and `ext-json`) with no framework coupling.
- **Environment Auto-Configuration**: Automatically resolves bearer tokens from `ONLYDNS_API_KEY` or `ONLYDNS_API_TOKEN`.

## Installation

```bash
composer require onlydns/onlydns-sdk
```

## Quick Start

```php
<?php

use OnlyDNS\OnlyDNSClient;
use OnlyDNS\Models\RecordType;

// Initialize client (falls back to getenv('ONLYDNS_API_KEY'))
$client = new OnlyDNSClient(apiToken: 'your_api_bearer_token');

// 1. List Domains
$domains = $client->listDomains();
print_r($domains);

// 2. Fetch DNS Records
$records = $client->getRecords('example.com');
foreach ($records as $record) {
    echo "{$record->name} ({$record->getTypeString()}): " . implode(', ', $record->records) . PHP_EOL;
}

// 3. High-Level Helper: Add an A Record
$client->addRecord(
    domainName: 'example.com',
    name: 'api',
    type: RecordType::A,
    records: '192.0.2.1',
    ttl: 300
);

// 4. High-Level Helper: Update / Upsert CNAME
$client->updateRecord(
    domainName: 'example.com',
    name: 'www',
    type: RecordType::CNAME,
    records: ['example.com'],
    ttl: 600
);

// 5. High-Level Helper: Remove Record or Specific Value
$client->removeRecord(
    domainName: 'example.com',
    name: 'api',
    type: RecordType::A,
    value: '192.0.2.1'
);
```

## TSIG Key Management

```php
use OnlyDNS\OnlyDNSClient;
use OnlyDNS\Models\TsigAlgorithm;

$client = new OnlyDNSClient();

// List TSIG keys
$keys = $client->getTsigKeys();

// Create new TSIG key
$newKey = $client->createTsigKey(algorithm: TsigAlgorithm::HMAC_SHA256);
echo "Created TSIG key: {$newKey->name}" . PHP_EOL;

// Update Domain TSIG permissions
$client->updateDomainTsig(
    domainName: 'example.com',
    keyName: $newKey->name,
    allowAxfr: true,
    allowDnsUpdate: true
);

// Delete all TSIG keys on a domain
$client->deleteDomainTsig('example.com');
```

## DNSSEC & Domain Creation

```php
// Create a new domain
$client->createDomain('my-new-domain.com');

// Get DNSSEC status & DS keys
$dnssec = $client->getDnssecStatus('my-new-domain.com');
echo "DNSSEC Enabled: " . ($dnssec->enabled ? 'yes' : 'no') . PHP_EOL;
foreach ($dnssec->keys as $key) {
    echo "KeyTag: {$key->keyTag}, Digest: {$key->digest}" . PHP_EOL;
}

// Enable DNSSEC
$client->enableDnssec('my-new-domain.com');

// Disable DNSSEC
$client->disableDnssec('my-new-domain.com');
```

## License

MIT License.
