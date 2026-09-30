export const RATE_LIMIT_MESSAGE = 'Too many requests. Please wait a moment and try again.';

export class ApiError extends Error {
    constructor(message, { errors = {}, status = 0 } = {}) {
        super(message);
        this.errors = errors;
        this.status = status;
    }
}

export async function api(path, { method = 'GET', body, params } = {}) {
    const headers = { Accept: 'application/json' };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const query = new URLSearchParams(
        Object.entries(params ?? {}).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    ).toString();

    const response = await fetch(`/api${path}${query ? `?${query}` : ''}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ApiError(data.message ?? 'Something went wrong.', {
            errors: data.errors,
            status: response.status,
        });
    }

    return data;
}

// Laravel's own 429 body says "Too Many Attempts.", which means little to a user.
export function errorMessage(error) {
    if (!(error instanceof ApiError)) {
        return 'Something went wrong.';
    }

    return error.status === 429 ? RATE_LIMIT_MESSAGE : error.message;
}
