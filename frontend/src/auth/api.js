// api.js
import axios from 'axios'
import { refreshSession } from './auth'

export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '',
    headers: { Accept: 'application/json' },
    withCredentials: true,
})

// Флаг-блокировка: идет ли сейчас процесс рефреша?
let isRefreshing = false;
// Очередь для запросов, которые получили 401, пока шел рефреш
let failedQueue = [];

const processQueue = (error, token = null) => {
    failedQueue.forEach(prom => {
        if (error) {
            prom.reject(error);
        } else {
            prom.resolve(token);
        }
    });
    failedQueue = [];
};

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        // Если это не 401 или запрос уже повторяли — прокидываем ошибку дальше
        if (error.response?.status !== 401 || !originalRequest || originalRequest._retry) {
            return Promise.reject(error);
        }

        // Если рефреш УЖЕ ИДЕТ, ставим этот (второй/третий) запрос в очередь
        if (isRefreshing) {
            return new Promise(function(resolve, reject) {
                failedQueue.push({ resolve, reject });
            })
            .then(() => {
                // Когда рефреш закончится, повторяем оригинальный запрос
                return api(originalRequest);
            })
            .catch(err => {
                return Promise.reject(err);
            });
        }

        // Если мы здесь, значит это ПЕРВЫЙ запрос, получивший 401.
        // Включаем блокировку и начинаем рефреш.
        originalRequest._retry = true;
        isRefreshing = true;

        try {
            const refreshed = await refreshSession();
            
            if (!refreshed) {
                // Если рефреш не удался (например, токен реально протух)
                processQueue(new Error('Refresh failed'), null);
                window.location.href = '/login';
                return Promise.reject(error);
            }

            // Рефреш успешен! Разблокируем очередь и повторяем все ждущие запросы
            processQueue(null, 'Success');
            return api(originalRequest);
            
        } catch (err) {
            processQueue(err, null);
            window.location.href = '/login';
            return Promise.reject(err);
        } finally {
            // В любом случае снимаем блокировку в конце
            isRefreshing = false;
        }
    }
)