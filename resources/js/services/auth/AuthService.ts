import { ensureCsrfCookie, http, isRequestAborted, normalizeApiError } from '@/http/http';
import { store as loginRoute } from '@/routes/login';
import {
    email as requestPasswordResetRoute,
    update as resetPasswordRoute,
} from '@/routes/password';
import { store as confirmPasswordRoute } from '@/routes/password/confirm';
import { store as registerRoute } from '@/routes/register';
import { send as resendVerificationEmailRoute } from '@/routes/verification';
import { store as twoFactorChallengeRoute } from '@/routes/two-factor/login';
import { logout as logoutRoute } from '@/routes';
import { graphql } from '@/graphql/client';
import type { User } from '@/types/auth/index';
import type { AuthStatusResponse as StatusResponse, LoginResult } from '@/types/auth/service';

export type { LoginResult } from '@/types/auth/service';

type CurrentUserData = {
    me: {
        id: string;
        name: string;
        email: string;
        email_verified_at: string | null;
    } | null;
};

const CURRENT_USER_QUERY = `
    query CurrentUser {
        me {
            id
            name
            email
            email_verified_at: emailVerifiedAt
        }
    }
`;

export class AuthService {
    async fetchCurrentUser(options?: { signal?: AbortSignal }): Promise<User | null> {
        try {
            const data = await graphql<CurrentUserData>(
                CURRENT_USER_QUERY,
                {},
                { signal: options?.signal, csrf: true },
            );

            if (data.me === null) {
                return null;
            }

            return {
                ...data.me,
                id: Number(data.me.id),
            };
        } catch (error) {
            if (isRequestAborted(error)) {
                throw error;
            }

            const normalized = normalizeApiError(error);

            if (normalized.kind === 'unauthenticated') {
                return null;
            }

            throw normalized;
        }
    }

    async login(credentials: {
        email: string;
        password: string;
        remember: boolean;
    }): Promise<LoginResult> {
        await ensureCsrfCookie();

        const response = await http.request<Partial<LoginResult>>({
            ...loginRoute(),
            data: {
                email: credentials.email,
                password: credentials.password,
                remember: credentials.remember,
            },
        });

        return { two_factor: response.data?.two_factor === true };
    }

    async register(payload: {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
    }): Promise<void> {
        await ensureCsrfCookie();
        await http.request({ ...registerRoute(), data: payload });
    }

    async requestPasswordReset(email: string): Promise<string> {
        await ensureCsrfCookie();

        const response = await http.request<StatusResponse>({
            ...requestPasswordResetRoute(),
            data: { email },
        });

        return (
            response.data.status ??
            response.data.message ??
            'We have emailed your password reset link.'
        );
    }

    async resetPassword(payload: {
        token: string;
        email: string;
        password: string;
        password_confirmation: string;
    }): Promise<string> {
        await ensureCsrfCookie();

        const response = await http.request<StatusResponse>({
            ...resetPasswordRoute(),
            data: payload,
        });

        return response.data.status ?? response.data.message ?? 'Your password has been reset.';
    }

    async resendVerificationEmail(): Promise<string> {
        await ensureCsrfCookie();

        const response = await http.request<StatusResponse>(resendVerificationEmailRoute());

        return response.data.status ?? response.data.message ?? 'verification-link-sent';
    }

    async confirmPassword(payload: { password: string }): Promise<void> {
        await ensureCsrfCookie();
        await http.request({ ...confirmPasswordRoute(), data: payload });
    }

    async submitTwoFactorChallenge(payload: {
        code?: string;
        recovery_code?: string;
    }): Promise<void> {
        await ensureCsrfCookie();
        await http.request({ ...twoFactorChallengeRoute(), data: payload });
    }

    async logout(): Promise<void> {
        await ensureCsrfCookie();
        await http.request(logoutRoute());
    }
}

export const authService = new AuthService();
