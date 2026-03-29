<?php

declare(strict_types=1);

namespace Xima\XimaTwitterClient\Exception;

class TwitterApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $apiErrorCode = null,
        private readonly ?string $apiErrorTitle = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromApiResponse(object $response): self
    {
        $error = $response->errors[0] ?? null;

        if ($error === null) {
            return new self('Unknown Twitter API error', 1673361800);
        }

        $message = sprintf(
            'Twitter API error: %s (Type: %s)',
            $error->detail ?? $error->title ?? 'Unknown error',
            $error->type ?? 'unknown'
        );

        return new self(
            $message,
            1673361801,
            null,
            $error->type ?? null,
            $error->title ?? null
        );
    }

    public static function userNotFound(string $username): self
    {
        return new self(
            sprintf('Twitter user "%s" not found or could not be resolved', $username),
            1673281853
        );
    }

    public static function noTweetsFound(string $username): self
    {
        return new self(
            sprintf('No tweets found for user "%s"', $username),
            1673286318
        );
    }

    public function getApiErrorCode(): ?string
    {
        return $this->apiErrorCode;
    }

    public function getApiErrorTitle(): ?string
    {
        return $this->apiErrorTitle;
    }
}
