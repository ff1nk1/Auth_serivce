import { api } from '../api/apiClient'
import { requireAnalyst } from '../auth/requireAuth'

export async function notificationsLoader() {
  await requireAnalyst()
  const response = await api.get('/notifications')
  return response.data
}

export async function notificationDetailLoader({ params }) {
  await requireAnalyst()
  const response = await api.get(`/notifications/${params.id}`)
  return response.data?.data || response.data
}
