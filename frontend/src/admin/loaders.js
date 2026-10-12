import { api } from '../api/apiClient'
import { requireAdmin, requireAnalyst } from '../auth/requireAuth'

export async function adminLayoutLoader() {
  return requireAnalyst()
}

export async function adminUsersLoader() {
  await requireAdmin()
  const [usersRes, rolesRes] = await Promise.all([
    api.get('/admin/users', { params: { page: 1, per_page: 15 } }),
    api.get('/admin/roles'),
  ])
  return {
    users: usersRes.data,
    roles: rolesRes.data?.data || rolesRes.data || [],
  }
}

export async function adminOrdersLoader() {
  await requireAnalyst()
  const response = await api.get('/admin/orders', { params: { page: 1, per_page: 15 } })
  return response.data
}

export async function adminNotificationsLoader() {
  await requireAnalyst()
  return null
}

export async function adminNotificationDetailLoader({ params }) {
  await requireAnalyst()
  const response = await api.get(`/notifications/${params.id}`)
  return response.data?.data || response.data
}
