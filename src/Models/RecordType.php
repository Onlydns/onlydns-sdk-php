<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

enum RecordType: string
{
    case A = 'A';
    case AAAA = 'AAAA';
    case MX = 'MX';
    case CNAME = 'CNAME';
    case TXT = 'TXT';
    case SRV = 'SRV';
    case CAA = 'CAA';
    case CERT = 'CERT';
    case HTTPS = 'HTTPS';
    case LOC = 'LOC';
    case NAPTR = 'NAPTR';
    case NS = 'NS';
    case SMIMEA = 'SMIMEA';
    case SSHFP = 'SSHFP';
    case SVCB = 'SVCB';
    case TLSA = 'TLSA';
    case URL = 'URL';
    case PROXY = 'PROXY';
}
