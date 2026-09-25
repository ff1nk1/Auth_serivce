import axios from 'axios'
import { refreshSession } from './auth'

export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '',
    headers: {
        Accept: 'application/json',
    },

    withCredentials: true,

    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    withXSRFToken: true,
})

api.interceptors.response.use(
    (response) => {
        return response
    },

    async (error) => {
        const originalRequest = error.config

        /*
         * Нас интересует только 401 ошибка.
         */
        if (
            error.response?.status !== 401 ||
            !originalRequest
        ) {
            throw error
        }

        /*
         * Уже пытались повторить этот запрос.
         * Значит refresh больше не помог, выбрасываем на логин.
         */
        if (originalRequest._retry) {
            window.location.href = '/login'
            throw error
        }

        /*
         * Помечаем запрос,
         * чтобы не получить бесконечный цикл.
         */
        originalRequest._retry = true

        console.log(
            '[API] 401 received, refreshing session...'
        )

        const refreshed = await refreshSession()

        if (!refreshed) {
            window.location.href = '/login'
            throw error
        }

        console.log(
            '[API] Retry original request:',
            originalRequest.url
        )

        /*
         * Повторяем исходный запрос.
         */
        return api(originalRequest)
    }
)