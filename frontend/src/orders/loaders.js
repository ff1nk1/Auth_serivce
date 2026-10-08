import { redirect } from 'react-router-dom'
import { requireAuth } from '../auth/requireAuth'
import { orderApi } from '../api/orderClient'

export async function ordersLoader() {
  await requireAuth()
  return null
}

export async function orderDetailLoader({ params }) {
  await requireAuth()
  try {
    const { data } = await orderApi.get(`/orders/${params.id}`)
    return { order: data }
  } catch {
    throw redirect('/orders')
  }
}
