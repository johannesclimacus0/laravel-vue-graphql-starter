<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

final class DeleteUserAccount
{
    /** @param array<string, mixed> $input */
    public function delete(User $user, array $input, Request $request): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', 'current_password:web'],
        ])->validate();

        Auth::guard('web')->logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();
    }
}
