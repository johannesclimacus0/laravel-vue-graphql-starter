import { graphql } from '@/graphql/client';
import {
    DeleteMyAccountDocument,
    MyPreferencesDocument,
    MySecuritySettingsDocument,
    UpdateMyPasswordDocument,
    UpdateMyPreferencesDocument,
    UpdateMyProfileDocument,
    type DeleteMyAccountInput,
    type UpdateMyPasswordInput,
    type UpdateMyProfileInput,
} from '@/graphql/generated/graphql';
import { http, isRequestAborted, normalizeApiError } from '@/http/http';
import type { PasswordConfirmationStatus } from '@/types/auth/session';
import type { UserPreferences } from '@/types/settings/preferences';
import type { SecuritySettings } from '@/types/settings/security';
import { confirmation as confirmedPasswordStatusRoute } from '@/routes/password';

export type { UserPreferences } from '@/types/settings/preferences';
export type { SecuritySettings } from '@/types/settings/security';
export type { PasswordConfirmationStatus } from '@/types/auth/session';

export class SettingsService {
    async updateProfile(payload: UpdateMyProfileInput): Promise<void> {
        await graphql(UpdateMyProfileDocument, { input: payload }, { csrf: true });
    }

    async updatePreferences(preferences: UserPreferences): Promise<UserPreferences> {
        const data = await graphql(
            UpdateMyPreferencesDocument,
            { input: preferences },
            { csrf: true },
        );

        return data.updateMyPreferences;
    }

    async updatePassword(payload: UpdateMyPasswordInput): Promise<void> {
        await graphql(UpdateMyPasswordDocument, { input: payload }, { csrf: true });
    }

    async deleteAccount(payload: DeleteMyAccountInput): Promise<void> {
        await graphql(DeleteMyAccountDocument, { input: payload }, { csrf: true });
    }

    async fetchPreferences(options?: { signal?: AbortSignal }): Promise<UserPreferences> {
        const data = await graphql(
            MyPreferencesDocument,
            {},
            { signal: options?.signal, csrf: true },
        );

        return data.myPreferences;
    }

    async fetchSecuritySettings(options?: { signal?: AbortSignal }): Promise<SecuritySettings> {
        const data = await graphql(
            MySecuritySettingsDocument,
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
