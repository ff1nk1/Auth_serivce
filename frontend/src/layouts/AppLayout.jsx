import { useLoaderData, Outlet } from 'react-router-dom'
import AppHeader from '../components/AppHeader'
import { api } from '../api/apiClient'

/** Soft-load current user for the header; do not redirect on 401. */
export async function appLayoutLoader() {
  try {
    const { data } = await api.get('/user')
    return data
  } catch {
    return null
  }
}

export default function AppLayout() {
  const user = useLoaderData()

  return (
    <>
      <AppHeader user={user} />
      <Outlet />
    </>
  )
}
