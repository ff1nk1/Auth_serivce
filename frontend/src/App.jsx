// App.jsx
import { 
  createBrowserRouter, 
  RouterProvider, 
  Route, 
  createRoutesFromElements 
} from 'react-router-dom';

import Login from './Pages/Login'
import Registration from './Pages/Registration'
import ProfilePage from './Pages/ProfilePage'
import Admin from './Pages/Admin'
import Notifications from './Pages/Notifications'
import NotificationDetail from './Pages/NotificationDetail'
import Categories from './Pages/Categories'
import CategoryProducts from './Pages/CategoryProducts'
import StoreProducts from './Pages/StoreProducts'
import ProductPage from './Pages/ProductPage'

import AdminCategories from './Pages/AdminCategories'
import AdminProducts from './Pages/AdminProducts'

import RootErrorBoundary from './RootErrorBoundary'
import { adminLoader, notificationsLoader, notificationDetailLoader } from './notifications/loaders'

const router = createBrowserRouter(
  createRoutesFromElements(
    <Route errorElement={<RootErrorBoundary />}>
      
      {/* Публичные роуты */}
      <Route path="/login" element={<Login />} />
      <Route path="/registration" element={<Registration />} />
      <Route path="/profile" element={<ProfilePage />} />
      <Route path="/categories" element={<Categories />} />
      <Route path="/categories/:slug" element={<CategoryProducts />} />
      <Route path="/stores/:storeId/products" element={<StoreProducts />} />
      <Route path="/products/:id" element={<ProductPage />} />
      
      {/* ЗАЩИЩЕННЫЕ РОУТЫ АДМИНА/АНАЛИТИКА */}
      <Route path="/admin" element={<Admin />} loader={adminLoader} />
      <Route path="/notifications" element={<Notifications />} loader={notificationsLoader} />
      <Route path="/notifications/:id" element={<NotificationDetail />} loader={notificationDetailLoader} />

      {/* НОВЫЕ РОУТЫ ДЛЯ УПРАВЛЕНИЯ КАТАЛОГОМ (ТОЛЬКО АДМИН) */}
      <Route path="/admin/categories" element={<AdminCategories />} />
      <Route path="/admin/products" element={<AdminProducts />} />

    </Route>
  )
);

export default function App() {
  return <RouterProvider router={router} />
}