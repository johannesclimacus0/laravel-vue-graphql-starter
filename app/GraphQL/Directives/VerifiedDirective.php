<?php

declare(strict_types=1);

namespace App\GraphQL\Directives;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class VerifiedDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return 'directive @verified on FIELD_DEFINITION';
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (
            mixed $root,
            array $args,
            GraphQLContext $context,
            ResolveInfo $resolveInfo,
        ) use ($resolver): mixed {
            $user = $context->user();

            if ($user === null) {
                throw new AuthenticationException;
            }

            if (! $user instanceof MustVerifyEmail || ! $user->hasVerifiedEmail()) {
                throw new AuthorizationException('Email address is not verified');
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });
    }
}
