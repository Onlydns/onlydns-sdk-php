<?php

declare(strict_types=1);

namespace OnlyDNS\Exceptions;

use Exception;

class OnlyDNSException extends Exception
{
    /**
     * @param string $message
     * @param int $statusCode
     * @param array<string> $details
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly array $details = []
    ) {
        $formattedMessage = $statusCode > 0 ? "[$statusCode] $message" : $message;
        if (!empty($details)) {
            $formattedMessage .= ' - Details: ' . implode('; ', $details);
        }
        parent::__construct($formattedMessage, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
