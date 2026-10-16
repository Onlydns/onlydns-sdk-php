<?php

declare(strict_types=1);

namespace OnlyDNS\Models;

enum DNSSECKeyDigestType: int
{
    case SHA1 = 1;
    case SHA256 = 2;
    case GOST_R_34_11_94 = 3;
    case SHA384 = 4;
}
