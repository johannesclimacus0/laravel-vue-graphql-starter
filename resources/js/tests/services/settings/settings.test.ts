import { http } from '@/http/http';
import { settingsService } from '@/services/settings/SettingsService';
import { csrfCookie } from '@/routes/sanctum';
import { afterEach, describe, expect, it } from 'vite-plus/test';

const originalAdapter = http.defaults.adapter;

afterEach(() => {
    http.defaults.adapter = originalAdapter;
});

function respondWith(data: unknown): Array<{ url: string; body: Record<string, unknown> }> {
    const requests: Array<{ url: string; body: Record<string, unknown> }> = [];
    http.defaults.adapter = async (config) => {
        requests.push({
            url: config.url ?? '',
            body: config.data ? JSON.parse(config.data as string) : {},
        });
        return {
            data: config.url === '/graphql' ? data : {},
            status: 200,
            statusText: 'OK',
            headers: {},
            config,
        };
    };
    return requests;
}

describe('GraphQL settings service', () => {
    it('loads preferences through GraphQL', async () => {
        const requests = respondWith({ data: { myPreferences: { timezone: 'UTC' } } });
        await expect(settingsService.fetchPreferences()).resolves.toEqual({ timezone: 'UTC' });
        expect(requests.map(({ url }) => url)).toEqual([csrfCookie().url, '/graphql']);
        expect(requests[1]?.body.query).toContain('myPreferences');
    });

    it('updates preferences and returns the saved values', async () => {
        const requests = respondWith({
            data: { updateMyPreferences: { timezone: 'Europe/Paris' } },
        });
        await expect(
            settingsService.updatePreferences({ timezone: 'Europe/Paris' }),
        ).resolves.toEqual({ timezone: 'Europe/Paris' });
        expect(requests[1]?.url).toBe('/graphql');
        expect(requests[1]?.body.variables).toEqual({ input: { timezone: 'Europe/Paris' } });
        expect(requests[1]?.body.query).toContain('updateMyPreferences');
    });

    it('updates the profile through GraphQL', async () => {
        const requests = respondWith({ data: { updateMyProfile: { id: '1' } } });
        await settingsService.updateProfile({ name: 'Jane', email: 'jane@example.com' });
        expect(requests[1]?.url).toBe('/graphql');
        expect(requests[1]?.body.variables).toEqual({
            input: { name: 'Jane', email: 'jane@example.com' },
        });
        expect(requests[1]?.body.query).toContain('updateMyProfile');
    });

    it('loads security state through GraphQL', async () => {
        const settings = {
            canManageTwoFactor: true,
            twoFactorEnabled: false,
            requiresConfirmation: true,
            passwordRules: 'minlength: 8;',
        };
        const requests = respondWith({ data: { mySecuritySettings: settings } });
        await expect(settingsService.fetchSecuritySettings()).resolves.toEqual(settings);
        expect(requests[1]?.url).toBe('/graphql');
        expect(requests[1]?.body.query).toContain('mySecuritySettings');
    });

    it('updates the password through GraphQL', async () => {
        const input = {
            current_password: 'current',
            password: 'new-password',
            password_confirmation: 'new-password',
        };
        const requests = respondWith({ data: { updateMyPassword: true } });
        await settingsService.updatePassword(input);
        expect(requests[1]?.url).toBe('/graphql');
        expect(requests[1]?.body.variables).toEqual({ input });
        expect(requests[1]?.body.query).toContain('updateMyPassword');
    });

    it('deletes the account through GraphQL', async () => {
        const requests = respondWith({ data: { deleteMyAccount: true } });
        await settingsService.deleteAccount({ password: 'current' });
        expect(requests[1]?.url).toBe('/graphql');
        expect(requests[1]?.body.variables).toEqual({ input: { password: 'current' } });
        expect(requests[1]?.body.query).toContain('deleteMyAccount');
    });

    it('preserves validation errors returned with HTTP 200', async () => {
        respondWith({
            errors: [
                {
                    message: 'Validation failed',
                    extensions: { validation: { 'input.timezone': ['Invalid timezone.'] } },
                },
            ],
        });
        await expect(
            settingsService.updatePreferences({ timezone: 'invalid' }),
        ).rejects.toMatchObject({
            kind: 'validation',
            errors: { timezone: ['Invalid timezone.'] },
        });
    });

    it('preserves authorization errors returned with HTTP 200', async () => {
        respondWith({
            data: null,
            errors: [{ message: 'Not verified', extensions: { code: 'FORBIDDEN' } }],
        });
        await expect(settingsService.fetchSecuritySettings()).rejects.toMatchObject({
            kind: 'forbidden',
        });
    });
});
