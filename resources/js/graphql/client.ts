import {
    ensureCsrfCookie,
    http,
    isRequestAborted,
    normalizeApiError,
    notifyUnauthenticated,
} from '@/http/http';
import type { TypedDocumentString } from '@/graphql/generated/graphql';
import type { LaravelValidationErrors, NormalizedApiError } from '@/types/http';

type GraphQLError = {
    message: string;
    path?: Array<string | number>;
    extensions?: {
        validation?: Record<string, string[]>;
        code?: string;
        guards?: string[];
        [key: string]: unknown;
    };
};

type GraphQLResponse<TData> = {
    data?: TData | null;
    errors?: GraphQLError[];
};

type GraphQLRequestOptions = {
    signal?: AbortSignal;
    operationName?: string;
    csrf?: boolean;
};

function normalizeGraphQLErrors(errors: GraphQLError[], status: number): NormalizedApiError {
    const validationErrors: LaravelValidationErrors = {};

    for (const error of errors) {
        const validation = error.extensions?.validation;

        if (!validation) continue;

        for (const [path, messages] of Object.entries(validation)) {
            const field = path.startsWith('input.') ? path.slice(6) : path;

            validationErrors[field] = [...(validationErrors[field] ?? []), ...messages];
        }
    }

    if (Object.keys(validationErrors).length > 0) {
        return {
            kind: 'validation',
            status,
            message: 'Please check the entered data',
            errors: validationErrors,
        };
    }

    const unauthenticated = errors.some(
        (error) =>
            error.extensions?.code === 'UNAUTHENTICATED' || Array.isArray(error.extensions?.guards),
    );

    if (unauthenticated) {
        return {
            kind: 'unauthenticated',
            status,
            message: 'Please sign in to continue',
            errors: {},
        };
    }

    const forbidden = errors.some((error) => error.extensions?.code === 'FORBIDDEN');

    if (forbidden) {
        return {
            kind: 'forbidden',
            status,
            message: 'This action is unauthorized',
            errors: {},
        };
    }

    return {
        kind: 'unknown',
        status,
        message: 'Unable to complete the request',
        errors: {},
    };
}

export async function graphql<TData, TVariables extends object>(
    document: TypedDocumentString<TData, TVariables>,
    variables: TVariables,
    options: GraphQLRequestOptions = {},
): Promise<TData> {
    try {
        if (options.csrf) await ensureCsrfCookie(options.signal);

        const response = await http.post<GraphQLResponse<TData>>(
            '/graphql',
            {
                query: document.toString(),
                variables,
                ...(options.operationName ? { operationName: options.operationName } : {}),
            },
            {
                signal: options.signal,
            },
        );
        const payload = response.data;

        if (payload.errors?.length) {
            const error = normalizeGraphQLErrors(payload.errors, response.status);
            if (error.kind === 'unauthenticated') {
                notifyUnauthenticated();
            }
            throw error;
        }

        if (payload.data === undefined || payload.data === null) {
            throw {
                kind: 'unknown',
                status: response.status,
                message: 'The server returned no GraphQL data',
                errors: {},
            } satisfies NormalizedApiError;
        }

        return payload.data;
    } catch (error) {
        if (isRequestAborted(error)) {
            throw error;
        }

        throw normalizeApiError(error);
    }
}
