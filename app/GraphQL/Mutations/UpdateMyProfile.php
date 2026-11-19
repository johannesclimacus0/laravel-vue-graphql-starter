<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Fortify\Features;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class UpdateMyProfile
{
    public function __construct(
        private UpdateUserProfileInformation $action,
    ) {}

    /**
     * @param  array{input: array{name: string, email: string}}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): User
    {
        if (! Features::enabled(Features::updateProfileInformation())) {
            throw new AuthorizationException;
        }

        /** @var User $user */
        $user = $context->user();

        $this->action->update($user, $args['input']);

        return $user->refresh();
    }
}
