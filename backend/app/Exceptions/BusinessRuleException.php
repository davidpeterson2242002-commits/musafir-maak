<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A business rule stopped the action (trip full, ID not verified, too late to cancel...).
 * Rendered as HTTP 422 {message, code}; `code` lets the app react (e.g. open the ID step).
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'rule_violation', public readonly array $context = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode] + $this->context, 422);
    }
}
