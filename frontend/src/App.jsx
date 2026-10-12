import {
  createBrowserRouter,
  RouterProvider,
  Route,
  createRoutesFromElements,
  Navigate,
  useParams,
} from 'react-router-dom'

import Login from './Pages/Login'
import Registration from './Pages/Registration'
import ProfilePage from './Pages/ProfilePage'
import Categories from './Pages/Categories'
import CategoryProducts from './Pages/CategoryProducts'
import Stores from './Pages/Stores'
import StoreProducts from './Pages/StoreProducts'
import ProductPage from './Pages/ProductPage'
import Orders from './Pages/Orders'
import OrderDetail from './Pages/OrderDetail'
import Cart from './Pages/Cart'
import Search from './Pages/Search'

import AdminLayout from './admin/AdminLayout'
import AdminHub from './admin/AdminHub'
import AdminUsers from './admin/AdminUsers'
import AdminOrders from './admin/AdminOrders'
import AdminNotifications from './admin/AdminNotifications'
import AdminNotificationDetail from './admin/AdminNotificationDetail'

import RootErrorBoundary from './RootErrorBoundary'
import {
  adminLayoutLoader,
  adminUsersLoader,
  adminOrdersLoader,
  adminNotificationsLoader,
  adminNotificationDetailLoader,
} from './admin/loaders'
import { ordersLoader, orderDetailLoader } from './orders/loaders'
import { requireAuth } from './auth/requireAuth'

const router = createBrowserRouter(
  createRoutesFromElements(
    <Route errorElement={<RootErrorBoundary />}>
      <Route path="/login" element={<Login />} />
      <Route path="/registration" element={<Registration />} />
      <Route path="/search" element={<Search />} />

      <Route path="/profile" element={<ProfilePage />} loader={requireAuth} />
      <Route path="/categories" element={<Categories />} loader={requireAuth} />
      <Route path="/categories/:slug" element={<CategoryProducts />} loader={requireAuth} />
      <Route path="/stores" element={<Stores />} loader={requireAuth} />
      <Route path="/stores/:storeId/products" element={<StoreProducts />} loader={requireAuth} />
      <Route path="/products/:id" element={<ProductPage />} loader={requireAuth} />
      <Route path="/cart" element={<Cart />} loader={requireAuth} />

      <Route path="/orders" element={<Orders />} loader={ordersLoader} />
      <Route path="/orders/:id" element={<OrderDetail />} loader={orderDetailLoader} />

      <Route path="/admin" element={<AdminLayout />} loader={adminLayoutLoader}>
        <Route index element={<AdminHub />} />
        <Route path="users" element={<AdminUsers />} loader={adminUsersLoader} />
        <Route path="orders" element={<AdminOrders />} loader={adminOrdersLoader} />
        <Route path="notifications" element={<AdminNotifications />} loader={adminNotificationsLoader} />
        <Route
          path="notifications/:id"
          element={<AdminNotificationDetail />}
          loader={adminNotificationDetailLoader}
        />
      </Route>

      <Route path="/notifications" element={<Navigate to="/admin/notifications" replace />} />
      <Route path="/notifications/:id" element={<RedirectNotificationDetail />} />
    </Route>
  )
)

function RedirectNotificationDetail() {
  const { id } = useParams()
  return <Navigate to={`/admin/notifications/${id}`} replace />
}

export default function App() {
  return <RouterProvider router={router} />
}
