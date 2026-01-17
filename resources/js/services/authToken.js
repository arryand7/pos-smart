const TOKEN_STORAGE_KEY = 'smart.auth.token';

let currentToken = null;

function persistToken(token) {
    try {
        if (token) {
            window.localStorage.setItem(TOKEN_STORAGE_KEY, token);
        } else {
            window.localStorage.removeItem(TOKEN_STORAGE_KEY);
        }
    } catch (error) {
        console.warn('SMART: gagal menyimpan token ke storage', error);
    }
}

function applyAxiosAuthorization(token) {
    if (! window.axios) {
        return;
    }

    if (token) {
        window.axios.defaults.headers.common.Authorization = `Bearer ${token}`;
    } else {
        delete window.axios.defaults.headers.common.Authorization;
    }
}

export function setAuthToken(token, { persist = true } = {}) {
    currentToken = token;
    applyAxiosAuthorization(token);

    if (persist) {
        persistToken(token);
    }

    window.dispatchEvent(new CustomEvent('auth:token-set', { detail: { token } }));
}

export function clearAuthToken() {
    currentToken = null;
    applyAxiosAuthorization(null);
    persistToken(null);
    window.dispatchEvent(new CustomEvent('auth:logout'));
}

export function getAuthToken() {
    return currentToken;
}

export function initializeAuthToken() {
    try {
        const stored = window.localStorage.getItem(TOKEN_STORAGE_KEY);
        if (stored) {
            setAuthToken(stored, { persist: false });
        }
    } catch (error) {
        console.warn('SMART: tidak dapat memuat token tersimpan', error);
    }

    if (window.axios) {
        window.axios.interceptors.response.use(
            (response) => response,
            (error) => {
                if (error?.response?.status === 401) {
                    clearAuthToken();
                    window.dispatchEvent(new CustomEvent('auth:unauthorized'));
                }

                return Promise.reject(error);
            }
        );
    }
}

export function withAuthToken(token) {
    setAuthToken(token);
}
