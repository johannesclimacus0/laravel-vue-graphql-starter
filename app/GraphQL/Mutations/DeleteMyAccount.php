<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\Settings\DeleteUserAccount;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class DeleteMyAccount
{
    public function __construct(private DeleteUserAccount $action) {}

    /** @param array{input: array{password: string}} $args */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        $request = $context->request();
        if ($request === null || ! $request->hasSession()) {
            throw new AuthenticationException;
        }

        /** @var User $user */
        $user = $context->user();
        $this->action->delete($user, $args['input'], $request);
        $context->setUser(null);

        return true;
    }
}
