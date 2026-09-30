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

// Импорт обработчика ошибок и загрузчиков
import RootErrorBoundary from './RootErrorBoundary'
import { adminLoader, notificationsLoader,notificationDetailLoader } from './notifications/loaders'

// 1. Создаем роутер вместо BrowserRouter
const router = createBrowserRouter(
  createRoutesFromElements(
    // Корневой Route БЕЗ пути ловит ошибки для ВСЕХ вложенных роутов
    <Route errorElement={<RootErrorBoundary />}>
      
      {/* Публичные роуты */}
      <Route path="/login" element={<Login />} />
      <Route path="/registration" element={<Registration />} />
      
      {/* Роуты пользователя */}
      <Route path="/profile" element={<ProfilePage />} />
      
      {/* ЗАЩИЩЕННЫЕ РОУТЫ (добавляем к ним loader) */}
      <Route 
        path="/admin" 
        element={<Admin />} 
        loader={adminLoader} 
      />
      <Route 
        path="/notifications" 
        element={<Notifications />} 
        loader={notificationsLoader} 
      />
      <Route 
        path="/notifications/:id" 
        element={<NotificationDetail />} 
        loader={notificationDetailLoader} 
      />


    </Route>
  )
);

// 2. В самом App возвращаем RouterProvider
export default function App() {
  return <RouterProvider router={router} />
}