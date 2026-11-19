<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Features;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class UpdateMyPassword
{
    public function __construct(private UpdateUserPassword $action) {}

    /** @param array{input: array{current_password: string, password: string, password_confirmation: string}} $args */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        if (! Features::enabled(Features::updatePasswords())) {
            throw new AuthorizationException;
        }

        /** @var User $user */
        $user = $context->user();
        $this->action->update($user, $args['input']);
        Password::broker(config('fortify.passwords'))->deleteToken($user);
        event(new PasswordUpdatedViaController($user));

        return true;
    }
}
