<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;

class HttpException extends Exception
{
    private int $statusCode;

    public function __construct(int $statusCode, string $message = '')
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
