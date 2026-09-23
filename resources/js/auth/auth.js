import axios from 'axios'

let refreshPromise = null

const authClient = axios.create({
    headers: {
        Accept: 'application/json',
    },

    withCredentials: true,

    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    withXSRFToken: true,
})


export function refreshSession() {
    /*
     * Если refresh уже выполняется,
     * остальные запросы ждут тот же Promise.
     */
    if (refreshPromise) {
        return refreshPromise
    }

    refreshPromise = authClient
        .post('/refresh')
        .then(() => {
            console.log('[AUTH] Refresh successful')

            return true
        })
        .catch((error) => {
            console.error(
                '[AUTH] Refresh failed:',
                error.response?.status
            )

            return false
        })
        .finally(() => {
            refreshPromise = null
        })

    return refreshPromise
}


export async function logout() {
    try {
        await authClient.post('/logout')
    } finally {
        window.location.href = '/login'
    }
}