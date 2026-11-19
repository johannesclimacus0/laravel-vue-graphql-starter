<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class UpdateMyProfile
{
    public function __construct(
        private UpdateUserProfileInformation $action,
    ) {}

    /**
     * @param array{input: array{name: string, email: string}} $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        /** @var User $user */
        $user = Auth::user();

        $this->action->update($user, $args['input']);

        return $user->refresh();
    }
}
