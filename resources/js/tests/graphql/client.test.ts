import { graphql } from '@/graphql/client';
import { configureAuthSessionHandlers, http, setAuthSessionHandlersSuppressed } from '@/http/http';
import { CanceledError } from 'axios';
import { afterEach, expect, it, vi } from 'vite-plus/test';

const originalAdapter = http.defaults.adapter;

afterEach(() => {
    http.defaults.adapter = originalAdapter;
    configureAuthSessionHandlers({});
    setAuthSessionHandlersSuppressed(false);
});

function respondWith(data: unknown): void {
    http.defaults.adapter = async (config) => ({
        data,
        status: 200,
        statusText: 'OK',
        headers: {},
        config,
    });
}

it('notifies the session handler when a protected GraphQL operation loses authentication', async () => {
    const onUnauthenticated = vi.fn();
    configureAuthSessionHandlers({ onUnauthenticated });
    respondWith({
        errors: [{ message: 'Unauthenticated.', extensions: { code: 'UNAUTHENTICATED' } }],
    });

    await expect(graphql('{ myPreferences { timezone } }', {})).rejects.toMatchObject({
        kind: 'unauthenticated',
    });
    expect(onUnauthenticated).toHaveBeenCalledOnce();
});

it('suppresses the session handler during auth bootstrap', async () => {
    const onUnauthenticated = vi.fn();
    configureAuthSessionHandlers({ onUnauthenticated });
    setAuthSessionHandlersSuppressed(true);
    respondWith({
        errors: [{ message: 'Unauthenticated.', extensions: { code: 'UNAUTHENTICATED' } }],
    });

    await expect(graphql('{ me { id } }', {})).rejects.toMatchObject({ kind: 'unauthenticated' });
    expect(onUnauthenticated).not.toHaveBeenCalled();
});

it('rejects partial results when the operation contains errors', async () => {
    respondWith({ data: { me: { id: '1' } }, errors: [{ message: 'Internal error' }] });
    await expect(graphql('{ me { id } }', {})).rejects.toMatchObject({ kind: 'unknown' });
});

it('rejects responses with no data', async () => {
    respondWith({});
    await expect(graphql('{ me { id } }', {})).rejects.toMatchObject({ kind: 'unknown' });
});

it('does not send CSRF or GraphQL requests after cancellation', async () => {
    const adapter = vi.fn();
    http.defaults.adapter = adapter;
    const controller = new AbortController();
    controller.abort();

    await expect(
        graphql('{ me { id } }', {}, { csrf: true, signal: controller.signal }),
    ).rejects.toBeInstanceOf(CanceledError);
    expect(adapter).not.toHaveBeenCalled();
});
