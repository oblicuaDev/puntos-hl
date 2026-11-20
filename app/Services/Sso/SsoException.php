<?php

namespace App\Services\Sso;

use RuntimeException;
use Throwable;

class SsoException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
