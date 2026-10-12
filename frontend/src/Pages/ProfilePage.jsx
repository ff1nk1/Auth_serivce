import { useLoaderData } from 'react-router-dom'
import Profile from './Profile'

export default function ProfilePage() {
  const user = useLoaderData()

  if (!user) {
    return null
  }

  return <Profile user={user} />
}
