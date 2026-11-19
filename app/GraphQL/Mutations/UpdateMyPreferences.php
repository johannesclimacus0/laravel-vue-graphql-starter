<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\Settings\UpdateUserPreferences;
use App\Models\User;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class UpdateMyPreferences
{
    public function __construct(private UpdateUserPreferences $action) {}

    /**
     * @param  array{input: array{timezone: string}}  $args
     * @return array{timezone: string}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        /** @var User $user */
        $user = $context->user();

        $this->action->update($user, $args['input']);

        return ['timezone' => $user->timezone];
    }
}
