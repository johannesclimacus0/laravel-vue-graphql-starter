<?php

declare(strict_types=1);

namespace App\GraphQL;

use GraphQL\Error\Error;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Nuwave\Lighthouse\Execution\ErrorHandler;

final class ErrorCodeHandler implements ErrorHandler
{
    /** @return array<string, mixed>|null */
    public function __invoke(?Error $error, \Closure $next): ?array
    {
        $formatted = $next($error);
        $exception = $error?->getPrevious();
        $code = match (true) {
            $exception instanceof AuthenticationException => 'UNAUTHENTICATED',
            $exception instanceof AuthorizationException => 'FORBIDDEN',
            default => null,
        };

        if ($formatted !== null && $code !== null) {
            $formatted['extensions']['code'] = $code;
        }

        return $formatted;
    }
}
