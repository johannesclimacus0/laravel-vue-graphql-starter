import { graphql } from '@/graphql/client';
import { http, isRequestAborted, normalizeApiError } from '@/http/http';
import type { PasswordConfirmationStatus } from '@/types/auth/session';
import type { UserPreferences } from '@/types/settings/preferences';
import type { SecuritySettings } from '@/types/settings/security';
import { confirmation as confirmedPasswordStatusRoute } from '@/routes/password';

export type { UserPreferences } from '@/types/settings/preferences';
export type { SecuritySettings } from '@/types/settings/security';
export type { PasswordConfirmationStatus } from '@/types/auth/session';

type MyPreferencesData = {
    myPreferences: UserPreferences;
};

export class SettingsService {
    async updateProfile(payload: { name: string; email: string }): Promise<void> {
        await graphql<{ updateMyProfile: { id: string } }, { input: typeof payload }>(
            `
                mutation UpdateMyProfile($input: UpdateMyProfileInput!) {
                    updateMyProfile(input: $input) {
                        id
                    }
                }
            `,
            { input: payload },
            { csrf: true },
        );
    }

    async updatePreferences(preferences: UserPreferences): Promise<UserPreferences> {
        const data = await graphql<
            { updateMyPreferences: UserPreferences },
            { input: UserPreferences }
        >(
            `
                mutation UpdateMyPreferences($input: UpdateMyPreferencesInput!) {
                    updateMyPreferences(input: $input) {
                        timezone
                    }
                }
            `,
            { input: preferences },
            { csrf: true },
        );

        return data.updateMyPreferences;
    }

    async updatePassword(payload: {
        current_password: string;
        password: string;
        password_confirmation: string;
    }): Promise<void> {
        await graphql<{ updateMyPassword: boolean }, { input: typeof payload }>(
            `
                mutation UpdateMyPassword($input: UpdateMyPasswordInput!) {
                    updateMyPassword(input: $input)
                }
            `,
            { input: payload },
            { csrf: true },
        );
    }

    async deleteAccount(payload: { password: string }): Promise<void> {
        await graphql<{ deleteMyAccount: boolean }, { input: typeof payload }>(
            `
                mutation DeleteMyAccount($input: DeleteMyAccountInput!) {
                    deleteMyAccount(input: $input)
                }
            `,
            { input: payload },
            { csrf: true },
        );
    }

    async fetchPreferences(options?: { signal?: AbortSignal }): Promise<UserPreferences> {
        const data = await graphql<MyPreferencesData>(
            `
                query MyPreferences {
                    myPreferences {
                        timezone
                    }
                }
            `,
            {},
            { signal: options?.signal, csrf: true },
        );

        return data.myPreferences;
    }

    async fetchSecuritySettings(options?: { signal?: AbortSignal }): Promise<SecuritySettings> {
        const data = await graphql<{ mySecuritySettings: SecuritySettings }>(
            `
                query MySecuritySettings {
                    mySecuritySettings {
                        canManageTwoFactor
                        twoFactorEnabled
                        requiresConfirmation
                        passwordRules
                    }
                }
            `,
            {},
            { signal: options?.signal, csrf: true },
        );

        return data.mySecuritySettings;
    }

    async fetchPasswordConfirmationStatus(options?: {
        signal?: AbortSignal;
    }): Promise<PasswordConfirmationStatus> {
        try {
            const response = await http.request<PasswordConfirmationStatus>({
                ...confirmedPasswordStatusRoute(),
                signal: options?.signal,
            });

            return { confirmed: response.data.confirmed === true };
        } catch (error) {
            if (isRequestAborted(error)) {
                throw error;
            }

            throw normalizeApiError(error);
        }
    }
}

export const settingsService = new SettingsService();
