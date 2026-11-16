<?php

namespace App\Pixelforge;

use RuntimeException;
use Throwable;

class PixelforgeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $requestWasAccepted = false,
        public readonly ?int $statusCode = null,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
