<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

enum TsigAlgorithm: string
{
    case MAC_MD5 = 'mac-md5';
    case HMAC_SHA1 = 'hmac-sha1';
    case HMAC_SHA224 = 'hmac-sha224';
    case HMAC_SHA256 = 'hmac-sha256';
    case HMAC_SHA384 = 'hmac-sha384';
    case HMAC_SHA512 = 'hmac-sha512';
}
