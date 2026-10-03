// App.jsx
import { 
  createBrowserRouter, 
  RouterProvider, 
  Route, 
  createRoutesFromElements, 
  Navigate 
} from 'react-router-dom';

// Импорты страниц
import Login from './Pages/Login'
import Registration from './Pages/Registration'
import ProfilePage from './Pages/ProfilePage'
import Admin from './Pages/Admin'
import Notifications from './Pages/Notifications'
import NotificationDetail from './Pages/NotificationDetail'

// НОВЫЕ ИМПОРТЫ
import Categories from './Pages/Categories'
import CategoryProducts from './Pages/CategoryProducts'
import StoreProducts from './Pages/StoreProducts'

// Импорт обработчика ошибок и загрузчиков
import RootErrorBoundary from './RootErrorBoundary'
import { adminLoader, notificationsLoader, notificationDetailLoader } from './notifications/loaders'

const router = createBrowserRouter(
  createRoutesFromElements(
    <Route errorElement={<RootErrorBoundary />}>
      
      {/* Публичные роуты */}
      <Route path="/login" element={<Login />} />
      <Route path="/registration" element={<Registration />} />
      
      {/* Роуты пользователя */}
      <Route path="/profile" element={<ProfilePage />} />
      
      {/* КАТАЛОГ И ТОВАРЫ */}
      <Route path="/categories" element={<Categories />} />
      {/* :slug - это динамический параметр (например, "laptops") */}
      <Route path="/categories/:slug" element={<CategoryProducts />} />
      
      {/* :storeId - динамический параметр магазина (например, "5") */}
      <Route path="/stores/:storeId/products" element={<StoreProducts />} />
      
      {/* ЗАЩИЩЕННЫЕ РОУТЫ */}
      <Route path="/admin" element={<Admin />} loader={adminLoader} />
      <Route path="/notifications" element={<Notifications />} loader={notificationsLoader} />
      <Route path="/notifications/:id" element={<NotificationDetail />} loader={notificationDetailLoader} />

    </Route>
  )
);

export default function App() {
  return <RouterProvider router={router} />
}