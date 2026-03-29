<?php

declare(strict_types=1);

namespace Xima\XimaTwitterClient\Exception;

class ConfigurationException extends \RuntimeException
{
    public static function missingApiCredentials(string $missingKey): self
    {
        return new self(
            sprintf('Missing Twitter API credential: %s. Please configure the extension settings.', $missingKey),
            1673335100
        );
    }

    public static function invalidImageStorage(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Invalid image storage path: %s. Please verify the path exists and is accessible.', $path),
            1673335110,
            $previous
        );
    }

    public static function invalidFetchType(string $fetchType): self
    {
        return new self(
            sprintf('FetchType "%s" does not implement FetchTypeInterface', $fetchType),
            1673335140
        );
    }

    public static function noAccountsFound(): self
    {
        return new self('No Twitter accounts found. Make sure you created database entries.', 1774769923);
    }
}
