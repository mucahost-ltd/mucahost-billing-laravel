<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a provisioning step against Enhance fails. Carries the
 * pipeline stage so the caller/job can decide whether to roll back
 * partial state (e.g. an org was created but the website step failed).
 */
class EnhanceProvisioningException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $stage, // 'org' | 'subscription' | 'website' | 'suspend' | 'terminate'
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
