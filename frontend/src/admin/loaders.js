import { analyticApi, api } from '../api/apiClient'
import { requireAdmin, requireAnalyst } from '../auth/requireAuth'
import { defaultAnalyticsRange } from './analyticsDates'

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

function analyticsRangeParams(request) {
  const url = new URL(request.url)
  let from = url.searchParams.get('from')
  let to = url.searchParams.get('to')
  if (!from || !to) {
    const defaults = defaultAnalyticsRange()
    from = from || defaults.from
    to = to || defaults.to
  }
  return { from, to }
}

export async function adminAnalyticsUsersLoader({ request }) {
  await requireAnalyst()
  const response = await analyticApi.get('/analytics/users', {
    params: analyticsRangeParams(request),
  })
  return response.data
}

export async function adminAnalyticsOrdersLoader({ request }) {
  await requireAnalyst()
  const response = await analyticApi.get('/analytics/orders', {
    params: analyticsRangeParams(request),
  })
  return response.data
}

export async function adminAnalyticsSearchLoader({ request }) {
  await requireAnalyst()
  const response = await analyticApi.get('/analytics/search', {
    params: { ...analyticsRangeParams(request), limit: 20 },
  })
  return response.data
}

export async function adminAnalyticsStoresLoader({ request }) {
  await requireAnalyst()
  const url = new URL(request.url)
  const params = { ...analyticsRangeParams(request), limit: 20 }
  const storeId = url.searchParams.get('store_id')
  if (storeId) params.store_id = storeId
  const response = await analyticApi.get('/analytics/orders/stores', { params })
  return response.data
}

export async function adminAnalyticsCategoriesLoader({ request }) {
  await requireAnalyst()
  const url = new URL(request.url)
  const params = { ...analyticsRangeParams(request), limit: 20 }
  const categoryId = url.searchParams.get('category_id')
  if (categoryId) params.category_id = categoryId
  const response = await analyticApi.get('/analytics/orders/categories', { params })
  return response.data
}
