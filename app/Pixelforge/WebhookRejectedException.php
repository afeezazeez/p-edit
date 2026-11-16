<?php

namespace App\Pixelforge;

use RuntimeException;
use Throwable;

class WebhookRejectedException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
