<?php

namespace App\Exceptions;

use RuntimeException;

class AiProcessingException extends RuntimeException
{
    /**
     * Create an exception for a JSON parse failure.
     */
    public static function forParseFailure(string $rawResponse): self
    {
        return new self(
            'Failed to parse AI response as JSON. Raw response: '.mb_substr($rawResponse, 0, 200)
        );
    }

    /**
     * Create an exception for a missing required key in the AI response.
     */
    public static function forMissingKey(string $key, string $rawResponse): self
    {
        return new self(
            "AI response missing required key '{$key}'. Raw response: ".mb_substr($rawResponse, 0, 200)
        );
    }

    /**
     * Create an exception for a provider-level failure.
     */
    public static function forProviderFailure(string $reason): self
    {
        return new self("AI provider failure: {$reason}");
    }
}
