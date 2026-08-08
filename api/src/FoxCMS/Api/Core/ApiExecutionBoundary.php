<?php

declare(strict_types=1);

namespace FoxCMS\Api\Core;

use Closure;
use LogicException;
use Throwable;

/**
 * Shared execution boundary for public API applications.
 *
 * Applications own request parsing and domain behavior; this boundary owns the
 * stable HTTP representation of known API failures and sanitized fatal errors.
 */
final class ApiExecutionBoundary
{
    /**
     * @param callable(): mixed $operation
     * @param array<string, mixed>|callable(): array<string, mixed> $fatalDetails
     */
    public static function run(
        ApplicationContext $context,
        callable $operation,
        string $fatalErrorCode,
        int $fatalStatus = 503,
        array|callable $fatalDetails = [],
    ): never {
        try {
            Closure::fromCallable($operation)();
            throw new LogicException('API operation returned without producing a response.');
        } catch (HttpException $error) {
            JsonResponse::error(
                $error->errorCode(),
                $error->getMessage(),
                $error->statusCode(),
                $error->details(),
            );
        } catch (Throwable $error) {
            $details = is_callable($fatalDetails)
                ? Closure::fromCallable($fatalDetails)()
                : $fatalDetails;
            FatalResponse::send(
                $error,
                $context,
                $fatalErrorCode,
                $fatalStatus,
                RequestId::create(),
                is_array($details) ? $details : [],
            );
        }
    }
}
