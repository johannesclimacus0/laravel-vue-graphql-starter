<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;

final class GetUserSecuritySettings
{
    /** @return array{canManageTwoFactor: bool, twoFactorEnabled: bool, requiresConfirmation: bool, passwordRules: string} */
    public function get(User $user): array
    {
        $canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        return [
            'canManageTwoFactor' => $canManageTwoFactor,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'twoFactorEnabled' => $canManageTwoFactor && $user->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => $canManageTwoFactor && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
        ];
    }
}
