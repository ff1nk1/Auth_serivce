import axios from 'axios'

const apiBase = import.meta.env.VITE_API_URL || '/api'

export const refreshSession = async () => {
    try {
        await axios.post(
            `${apiBase}/refresh`,
            {},
            {
                withCredentials: true,
                headers: {
                    Accept: 'application/json',
                },
            }
        )

        return true
    } catch (error) {
        console.error('[AUTH] Не удалось обновить сессию', error)
        return false
    }
}
