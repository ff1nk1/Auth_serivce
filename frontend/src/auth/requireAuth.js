import { redirect } from 'react-router-dom'
import { api } from '../api/apiClient'

/**
 * Loader that requires a valid session. Redirects to /login on 401.
 */
export async function requireAuth() {
    try {
        const { data } = await api.get('/user')
        return data
    } catch {
        throw redirect('/login')
    }
}

/**
 * Require admin or analyst role (notifications).
 */
export async function requireAnalyst() {
    const user = await requireAuth()
    const slug = user?.role?.slug
    if (slug !== 'admin' && slug !== 'analyst') {
        throw redirect('/categories')
    }
    return user
}
