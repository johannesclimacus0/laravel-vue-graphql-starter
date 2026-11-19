import { csrfCookie } from '@/routes/sanctum';
import { http } from '@/http/http';
import { authService } from '@/services/auth/AuthService';
import { AxiosError, CanceledError, type AxiosAdapter } from 'axios';
import { afterEach, describe, expect, it } from 'vite-plus/test';

const originalAdapter = http.defaults.adapter;

afterEach(() => {
    http.defaults.adapter = originalAdapter;
});

function respondWith(payload: unknown): string[] {
    const requests: string[] = [];
    http.defaults.adapter = async (config) => {
        requests.push(config.url ?? '');
        return {
            data: config.url === '/graphql' ? payload : {},
            status: 200,
            statusText: 'OK',
            headers: {},
            config,
        };
    };
    return requests;
}

describe('GraphQL current user', () => {
    it('loads me and preserves the existing SPA user format', async () => {
        const requests = respondWith({
            data: {
                me: {
                    id: '42',
                    name: 'Jane',
                    email: 'jane@example.com',
                    email_verified_at: '2026-11-19 10:00:00',
                },
            },
        });

        await expect(authService.fetchCurrentUser()).resolves.toEqual({
            id: 42,
            name: 'Jane',
            email: 'jane@example.com',
            email_verified_at: '2026-11-19 10:00:00',
        });
        expect(requests).toEqual([csrfCookie().url, '/graphql']);
    });

    it('returns null for a guest', async () => {
        respondWith({ data: { me: null } });
        await expect(authService.fetchCurrentUser()).resolves.toBeNull();
    });

    it('preserves an unverified user', async () => {
        respondWith({
            data: {
                me: { id: '1', name: 'Jane', email: 'jane@example.com', email_verified_at: null },
            },
        });
        await expect(authService.fetchCurrentUser()).resolves.toMatchObject({
            email_verified_at: null,
        });
    });

    it('does not treat GraphQL errors with HTTP 200 as a guest', async () => {
        respondWith({ errors: [{ message: 'Internal server error' }], data: { me: null } });
        await expect(authService.fetchCurrentUser()).rejects.toMatchObject({ kind: 'unknown' });
    });

    it('does not treat a network error as a guest', async () => {
        http.defaults.adapter = (() => {
            throw new AxiosError('Network Error', AxiosError.ERR_NETWORK);
        }) satisfies AxiosAdapter;
        await expect(authService.fetchCurrentUser()).rejects.toMatchObject({ kind: 'network' });
    });

    it('returns null for a GraphQL authentication error', async () => {
        respondWith({
            errors: [{ message: 'Unauthenticated.', extensions: { guards: ['sanctum'] } }],
            data: { me: null },
        });
        await expect(authService.fetchCurrentUser()).resolves.toBeNull();
    });

    it('normalizes GraphQL validation errors', async () => {
        respondWith({
            errors: [
                {
                    message: 'Validation failed.',
                    extensions: { validation: { 'input.email': ['Invalid email.'] } },
                },
            ],
        });
        await expect(authService.fetchCurrentUser()).rejects.toMatchObject({
            kind: 'validation',
            errors: { email: ['Invalid email.'] },
        });
    });

    it('preserves request cancellation', async () => {
        const requests = respondWith({ data: { me: null } });
        const controller = new AbortController();
        controller.abort();
        await expect(
            authService.fetchCurrentUser({ signal: controller.signal }),
        ).rejects.toBeInstanceOf(CanceledError);
        expect(requests).not.toContain('/graphql');
    });
});
