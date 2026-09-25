import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'

// Импортируйте ваши перенесенные страницы
import Login from './Pages/Login'
import Registration from './Pages/Registration'
import Profile from './Pages/Profile'
import ProfilePage from './Pages/ProfilePage'


export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Авторизация и регистрация */}
        <Route path="/login" element={<Login />} />
        <Route path="/registration" element={<Registration />} />
        <Route path="/profile" element={<ProfilePage/>} />


        {/* Редирект по умолчанию для неизвестных маршрутов (404) */}
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    </BrowserRouter>
  )
}