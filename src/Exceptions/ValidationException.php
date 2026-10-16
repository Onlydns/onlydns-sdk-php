<?php

declare(strict_types=1);

namespace OnlyDNS\Exceptions;

class ValidationException extends OnlyDNSException
{
    public function __construct(string $message = 'Bad request or invalid parameters', int $statusCode = 400, array $details = [])
    {
        parent::__construct($message, $statusCode, $details);
    }
}
