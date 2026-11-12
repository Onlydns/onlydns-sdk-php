<?php

declare(strict_types=1);

namespace OnlyDNS\Tests;

require_once __DIR__ . '/../src/Exceptions/OnlyDNSException.php';
require_once __DIR__ . '/../src/Exceptions/AuthenticationException.php';
require_once __DIR__ . '/../src/Exceptions/NotFoundException.php';
require_once __DIR__ . '/../src/Exceptions/ValidationException.php';
require_once __DIR__ . '/../src/Exceptions/ApiException.php';
require_once __DIR__ . '/../src/Models/RecordType.php';
require_once __DIR__ . '/../src/Models/TsigAlgorithm.php';
require_once __DIR__ . '/../src/Models/DNSSECKeyAlgorithm.php';
require_once __DIR__ . '/../src/Models/DNSSECKeyDigestType.php';
require_once __DIR__ . '/../src/Models/DNSSECDsKey.php';
require_once __DIR__ . '/../src/Models/DNSSECStatus.php';
require_once __DIR__ . '/../src/Models/DNSRecord.php';
require_once __DIR__ . '/../src/Models/TsigKey.php';
require_once __DIR__ . '/../src/Models/DomainTsigPermissions.php';
require_once __DIR__ . '/../src/OnlyDNSClient.php';

use OnlyDNS\Exceptions\AuthenticationException;
use OnlyDNS\Models\DNSRecord;
use OnlyDNS\Models\DNSSECKeyAlgorithm;
use OnlyDNS\Models\DNSSECKeyDigestType;
use OnlyDNS\Models\DNSSECStatus;
use OnlyDNS\Models\RecordType;
use OnlyDNS\Models\TsigAlgorithm;
use OnlyDNS\OnlyDNSClient;

// Mock client subclass to test without live network
class MockOnlyDNSClient extends OnlyDNSClient
{
    /** @var array<array{method: string, path: string, params: array, body: mixed, response: mixed}> */
    public array $mockResponses = [];
    /** @var array<array{method: string, path: string, params: array, body: mixed}> */
    public array $recordedRequests = [];

    public function queueResponse(mixed $response): void
    {
        $this->mockResponses[] = $response;
    }

    protected function request(string $method, string $path, array $params = [], mixed $body = null): mixed
    {
        $this->recordedRequests[] = [
            'method' => $method,
            'path' => $path,
            'params' => $params,
            'body' => $body,
        ];

        return array_shift($this->mockResponses);
    }
}

// Test Runner
function runTests(): void
{
    echo "Running PHP SDK Tests...\n";

    // 1. Test Authentication Requirement
    $authPassed = false;
    try {
        putenv('ONLYDNS_API_TOKEN');
        unset($_ENV['ONLYDNS_API_TOKEN']);
        new OnlyDNSClient(apiToken: null);
    } catch (AuthenticationException $e) {
        $authPassed = true;
    }
    assert($authPassed, "Expected AuthenticationException when no token provided");
    echo "  [OK] Auth validation\n";

    // 2. Test listDomains
    $client = new MockOnlyDNSClient('test_token');
    $client->queueResponse(['example.com', 'example.org']);
    $domains = $client->listDomains();
    assert($domains === ['example.com', 'example.org'], "listDomains failed");
    assert(count($client->recordedRequests) === 1);
    assert($client->recordedRequests[0]['method'] === 'GET');
    assert($client->recordedRequests[0]['path'] === '/dns');
    echo "  [OK] listDomains\n";

    // 3. Test getRecords & addRecord helper
    $client = new MockOnlyDNSClient('test_token');
    // First GET response
    $client->queueResponse([
        ['name' => 'mail', 'type' => 'A', 'ttl' => 300, 'records' => ['192.0.2.1']],
    ]);
    // PUT response
    $client->queueResponse(null);

    $updated = $client->addRecord('example.com', 'mail', RecordType::A, '192.0.2.2', 600);
    assert(count($updated) === 1, "Expected 1 record group");
    assert($updated[0]->records === ['192.0.2.1', '192.0.2.2'], "Values not merged properly");
    assert($updated[0]->ttl === 600, "TTL not updated");
    echo "  [OK] addRecord helper\n";

    // 4. Test removeRecord helper
    $client = new MockOnlyDNSClient('test_token');
    $client->queueResponse([
        ['name' => 'api', 'type' => 'A', 'ttl' => 300, 'records' => ['1.1.1.1', '2.2.2.2']],
        ['name' => 'web', 'type' => 'A', 'ttl' => 300, 'records' => ['3.3.3.3']],
    ]);
    $client->queueResponse(null);

    $afterRemove = $client->removeRecord('example.com', 'api', RecordType::A, '1.1.1.1');
    assert(count($afterRemove) === 2);
    assert($afterRemove[0]->records === ['2.2.2.2']);
    echo "  [OK] removeRecord helper\n";

    // 4b. Test findRecords helper
    $client = new MockOnlyDNSClient('test_token');
    $client->queueResponse([
        ['name' => 'api.example.com.', 'type' => 'A', 'ttl' => 300, 'records' => ['192.0.2.1']],
        ['name' => 'mail.example.com.', 'type' => 'A', 'ttl' => 300, 'records' => ['192.0.2.25']],
        ['name' => 'www.example.com.', 'type' => 'CNAME', 'ttl' => 600, 'records' => ['example.com.']],
    ]);
    $found = $client->findRecords('example.com', name: 'mail', value: '192.0.2.25');
    assert(count($found) === 1);
    assert($found[0]->name === 'mail.example.com.');

    $client->queueResponse([
        ['name' => 'api.example.com.', 'type' => 'A', 'ttl' => 300, 'records' => ['192.0.2.1']],
        ['name' => 'mail.example.com.', 'type' => 'A', 'ttl' => 300, 'records' => ['192.0.2.25']],
        ['name' => 'www.example.com.', 'type' => 'CNAME', 'ttl' => 600, 'records' => ['example.com.']],
    ]);
    $foundNameSub = $client->findRecords('example.com', name: 'example');
    assert(count($foundNameSub) === 3);

    echo "  [OK] findRecords helper\n";

    // 5. Test TSIG operations
    $client = new MockOnlyDNSClient('test_token');
    $client->queueResponse([
        'name' => 'key-abc',
        'algorithm' => 'hmac-sha256',
        'key' => 'secret123',
    ]);
    $key = $client->createTsigKey(TsigAlgorithm::HMAC_SHA256);
    assert($key->name === 'key-abc');
    assert($key->algorithm === TsigAlgorithm::HMAC_SHA256);

    // Test Domain TSIG Permissions Model & Update/Get
    $perm = \OnlyDNS\Models\DomainTsigPermissions::fromArray([
        'allowAxfr' => true,
        'allowDnsUpdate' => true,
        'subdomains' => ['_acme_challenge', 'mail'],
    ]);
    assert($perm->allowAxfr === true);
    assert($perm->allowDnsUpdate === true);
    assert($perm->subdomains === ['_acme_challenge', 'mail']);
    assert($perm->toArray() === [
        'allowAxfr' => true,
        'allowDnsUpdate' => true,
        'subdomains' => ['_acme_challenge', 'mail'],
    ]);

    $client->queueResponse(null); // updateDomainTsig
    $client->updateDomainTsig('example.com', 'key-abc', true, true, ['_acme_challenge']);
    assert(count($client->recordedRequests) === 2);
    assert($client->recordedRequests[1]['method'] === 'PATCH');
    assert($client->recordedRequests[1]['path'] === '/dns/example.com/tsig/key-abc');
    assert($client->recordedRequests[1]['body'] === [
        'allowAxfr' => true,
        'allowDnsUpdate' => true,
        'subdomains' => ['_acme_challenge'],
    ]);

    $client->queueResponse([
        'key-abc' => [
            'allowAxfr' => false,
            'allowDnsUpdate' => true,
            'subdomains' => ['_acme_challenge'],
        ],
    ]);
    $domainPerms = $client->getDomainTsig('example.com');
    assert(isset($domainPerms['key-abc']));
    assert($domainPerms['key-abc']->subdomains === ['_acme_challenge']);

    echo "  [OK] TSIG key creation and domain permissions\n";

    // 6. Test createDomain
    $client = new MockOnlyDNSClient('test_token');
    $client->queueResponse(null);
    $client->createDomain('newexample.com');
    assert(count($client->recordedRequests) === 1);
    assert($client->recordedRequests[0]['method'] === 'POST');
    assert($client->recordedRequests[0]['path'] === '/dns');
    assert($client->recordedRequests[0]['body'] === ['domain' => 'newexample.com']);
    echo "  [OK] createDomain\n";

    // 7. Test DNSSEC operations
    $client = new MockOnlyDNSClient('test_token');
    $client->queueResponse([
        'enabled' => true,
        'nsec3param' => '1 0 10 12345678',
        'keys' => [
            [
                'keyTag' => 2371,
                'algorithm' => 13,
                'digestType' => 2,
                'digest' => 'abc123def456',
            ],
        ],
    ]);
    $client->queueResponse(null); // enable
    $client->queueResponse(null); // disable

    $status = $client->getDnssecStatus('example.com');
    assert($status->enabled === true);
    assert($status->nsec3param === '1 0 10 12345678');
    assert(count($status->keys) === 1);
    assert($status->keys[0]->keyTag === 2371);
    assert($status->keys[0]->algorithm === DNSSECKeyAlgorithm::ECDSA_P256_SHA256);
    assert($status->keys[0]->digestType === DNSSECKeyDigestType::SHA256);
    assert($status->keys[0]->digest === 'abc123def456');

    $client->enableDnssec('example.com');
    $client->disableDnssec('example.com');
    echo "  [OK] DNSSEC operations\n";

    // 8. Test Account and OIDC operations
    $client = new MockOnlyDNSClient('test_token');
    $client->queueResponse([
        'accountId' => '018f1234-5678-7000-8000-000000000001',
        'activeAt' => '2024-01-01T00:00:00+00:00',
        'companyName' => 'Acme Corporation',
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'address' => [
            'street1' => '123 Innovation Way',
            'city' => 'Austin',
            'state' => 'TX',
            'countryId' => 'US',
            'postalCode' => '78701',
        ],
        'package' => [
            'id' => '018f1234-5678-7000-8000-000000000002',
            'description' => 'Enterprise Tier',
            'amount' => 49.99,
            'features' => [
                'domains' => 50,
                'records' => 5000,
                'queries' => 1000000,
                'dnssec' => true,
                'tsig' => true,
                'proxy' => true,
                'customSSO' => true,
            ],
        ],
    ]);
    $client->queueResponse([
        'configured' => true,
        'accountId' => '018f1234-5678-7000-8000-000000000001',
        'discoveryUrl' => 'https://idp.example.com/.well-known/openid-configuration',
        'clientId' => 'client-123',
        'clientSecretMasked' => 'secr*****',
        'enforceSso' => false,
        'breakGlassAdminsCount' => 1,
        'canEnforceSso' => true,
        'nonCompliantUsers' => [],
    ]);
    $client->queueResponse([
        'configured' => true,
        'discoveryUrl' => 'https://idp.example.com/.well-known/openid-configuration',
        'clientId' => 'client-123',
        'enforceSso' => true,
    ]);
    $client->queueResponse([
        'success' => true,
        'issuer' => 'https://idp.example.com',
    ]);
    $client->queueResponse([
        'success' => true,
        'message' => 'OIDC configuration removed successfully',
    ]);

    $account = $client->getAccount();
    assert($account['accountId'] === '018f1234-5678-7000-8000-000000000001');
    assert($account['companyName'] === 'Acme Corporation');
    assert($account['package']['features']['customSSO'] === true);

    $oidc = $client->getAccountOidc();
    assert($oidc['configured'] === true);
    assert($oidc['clientId'] === 'client-123');

    $savedOidc = $client->saveAccountOidc([
        'discoveryUrl' => 'https://idp.example.com/.well-known/openid-configuration',
        'clientId' => 'client-123',
        'clientSecret' => 'secret123',
        'enforceSso' => true,
    ]);
    assert($savedOidc['enforceSso'] === true);

    $testOidc = $client->testAccountOidc('https://idp.example.com/.well-known/openid-configuration');
    assert($testOidc['success'] === true);
    assert($testOidc['issuer'] === 'https://idp.example.com');

    $delOidc = $client->deleteAccountOidc();
    assert($delOidc['success'] === true);

    echo "  [OK] Account and OIDC operations\n";

    echo "All PHP SDK tests passed successfully!\n";
}

runTests();
