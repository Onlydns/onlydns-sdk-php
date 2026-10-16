<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

enum DNSSECKeyAlgorithm: int
{
    case RSA_MD5 = 1;
    case DIFFIE_HELLMAN = 2;
    case DSA_SHA1 = 3;
    case RSA_SHA1 = 5;
    case DSA_NSEC3_SHA1 = 6;
    case RSASHA1_NSEC3_SHA1 = 7;
    case RSA_SHA256 = 8;
    case RSA_SHA512 = 10;
    case GOST_R_34_10_2001 = 12;
    case ECDSA_P256_SHA256 = 13;
    case ECDSA_P384_SHA384 = 14;
    case ED25519 = 15;
    case ED448 = 16;
    case SM2SM3 = 17;
    case ECC_GOST12 = 23;
}
