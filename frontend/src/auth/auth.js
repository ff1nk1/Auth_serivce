import axios from 'axios'

export const refreshSession = async () => {
    try {
        // Используем оригинальный axios, чтобы не триггерить интерсептор из api.js
        const response = await axios.post(
            `${import.meta.env.VITE_API_URL}/refresh`,
            {},
            {
                withCredentials: true,
                headers: {
                    Accept: 'application/json'
                }
            }
        );

        return true; // Рефреш прошел успешно
        
    } catch (error) {
        console.error('[AUTH] Не удалось обновить сессию', error);
        return false; // Рефреш провалился, пользователя выкинет на /login
    }
}