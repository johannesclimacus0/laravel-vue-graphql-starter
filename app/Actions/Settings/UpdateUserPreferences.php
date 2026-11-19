<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

final class UpdateUserPreferences
{
    /** @param array<string, mixed> $input */
    public function update(User $user, array $input): void
    {
        $validated = Validator::make($input, [
            'timezone' => ['required', 'timezone'],
        ])->validate();

        $user->update($validated);
    }
}
