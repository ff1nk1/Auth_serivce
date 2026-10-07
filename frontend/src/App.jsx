import {
  createBrowserRouter,
  RouterProvider,
  Route,
  createRoutesFromElements,
} from 'react-router-dom'

import Login from './Pages/Login'
import Registration from './Pages/Registration'
import ProfilePage from './Pages/ProfilePage'
import Notifications from './Pages/Notifications'
import NotificationDetail from './Pages/NotificationDetail'
import Categories from './Pages/Categories'
import CategoryProducts from './Pages/CategoryProducts'
import StoreProducts from './Pages/StoreProducts'
import ProductPage from './Pages/ProductPage'

import RootErrorBoundary from './RootErrorBoundary'
import { notificationsLoader, notificationDetailLoader } from './notifications/loaders'
import { requireAuth } from './auth/requireAuth'

const router = createBrowserRouter(
  createRoutesFromElements(
    <Route errorElement={<RootErrorBoundary />}>
      <Route path="/login" element={<Login />} />
      <Route path="/registration" element={<Registration />} />

      <Route path="/profile" element={<ProfilePage />} loader={requireAuth} />
      <Route path="/categories" element={<Categories />} loader={requireAuth} />
      <Route path="/categories/:slug" element={<CategoryProducts />} loader={requireAuth} />
      <Route path="/stores/:storeId/products" element={<StoreProducts />} loader={requireAuth} />
      <Route path="/products/:id" element={<ProductPage />} loader={requireAuth} />

      <Route path="/notifications" element={<Notifications />} loader={notificationsLoader} />
      <Route path="/notifications/:id" element={<NotificationDetail />} loader={notificationDetailLoader} />
    </Route>
  )
)

export default function App() {
  return <RouterProvider router={router} />
}
