<?php

declare(strict_types=1);

namespace OnlyDNS\Exceptions;

class AuthenticationException extends OnlyDNSException
{
    public function __construct(string $message = 'Access token is missing or invalid', int $statusCode = 401, array $details = [])
    {
        parent::__construct($message, $statusCode, $details);
    }
}
