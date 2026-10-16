<?php

declare(strict_types=1);

namespace OnlyDNS\Exceptions;

class NotFoundException extends OnlyDNSException
{
    public function __construct(string $message = 'Resource not found', int $statusCode = 404, array $details = [])
    {
        parent::__construct($message, $statusCode, $details);
    }
}
