<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Actions\Settings\GetUserSecuritySettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class MySecuritySettings
{
    public function __construct(private GetUserSecuritySettings $action) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array{canManageTwoFactor: bool, twoFactorEnabled: bool, requiresConfirmation: bool, passwordRules: string}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        /** @var User $user */
        $user = $context->user();

        if (! $user->hasVerifiedEmail()) {
            throw new AuthorizationException('Email address is not verified');
        }

        return $this->action->get($user);
    }
}
