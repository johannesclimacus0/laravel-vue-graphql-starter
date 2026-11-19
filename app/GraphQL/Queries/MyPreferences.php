<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\User;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MyPreferences
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{timezone: string}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        /** @var User $user */
        $user = $context->user();

        return ['timezone' => $user->timezone];
    }
}
