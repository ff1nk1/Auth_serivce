import { router } from '@inertiajs/react'
import { refreshSession } from './auth'

let lastGetVisit = null
let refreshingInertia = false

router.on('before', (event) => {
    const visit = event.detail.visit

    if (visit.method?.toLowerCase() !== 'get') {
        lastGetVisit = null
        return
    }

    lastGetVisit = {
        url: visit.url,
        method: visit.method,
        data: visit.data,
        replace: visit.replace,
        preserveState: visit.preserveState,
        preserveScroll: visit.preserveScroll,
        only: visit.only,
        except: visit.except,
    }
})

router.on('invalid', async (event) => {
    const response = event.detail.response

    if (response?.status !== 401) {
        return
    }

    if (refreshingInertia) {
        return
    }

    if (!lastGetVisit) {
        return
    }

    event.preventDefault()

    refreshingInertia = true

    const visit = lastGetVisit
    lastGetVisit = null

    console.log('[INERTIA] 401 received, refreshing session...')

    try {
        const refreshed = await refreshSession()

        if (!refreshed) {
            window.location.href = '/login'
            return
        }

        console.log('[INERTIA] Retry:', visit.url)

        router.visit(visit.url, {
            method: visit.method,
            data: visit.data,
            replace: visit.replace,
            preserveState: visit.preserveState,
            preserveScroll: visit.preserveScroll,
            only: visit.only,
            except: visit.except,
        })
    } finally {
        refreshingInertia = false
    }
})