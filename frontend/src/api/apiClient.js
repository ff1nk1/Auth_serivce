import { createClient } from './createClient'
import { refreshSession } from '../auth/auth'

export const api = createClient(import.meta.env.VITE_API_URL || '/api')

// Backward-compatible aliases — everything goes through auth gateway
export const authApi = api
export const catalogApi = api
export const notificationApi = api
export const orderApi = api

let isRefreshing = false
let failedQueue = []

const processQueue = (error, token = null) => {
    failedQueue.forEach((prom) => {
        if (error) {
            prom.reject(error)
        } else {
            prom.resolve(token)
        }
    })
    failedQueue = []
}

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config

        if (error.response?.status !== 401 || !originalRequest || originalRequest._retry) {
            return Promise.reject(error)
        }

        // Don't retry refresh itself
        if (originalRequest.url?.includes('/refresh')) {
            return Promise.reject(error)
        }

        if (isRefreshing) {
            return new Promise(function (resolve, reject) {
                failedQueue.push({ resolve, reject })
            })
                .then(() => api(originalRequest))
                .catch((err) => Promise.reject(err))
        }

        isRefreshing = true
        originalRequest._retry = true

        try {
            const refreshed = await refreshSession()

            if (!refreshed) {
                processQueue(new Error('Refresh failed'), null)
                window.location.href = '/login'
                return Promise.reject(error)
            }

            processQueue(null, 'Success')
            return api(originalRequest)
        } catch (err) {
            processQueue(err, null)
            window.location.href = '/login'
            return Promise.reject(err)
        } finally {
            isRefreshing = false
        }
    }
)
