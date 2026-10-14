import { Link, NavLink, Outlet, useLoaderData } from 'react-router-dom'
import '../css/admin.css'
import '../css/notifications.css'

export default function AdminLayout() {
  const user = useLoaderData()
  const isAdmin = user?.role?.slug === 'admin'

  return (
    <div className="admin-page">
      <div className="admin-card admin-shell">
        <div className="admin-header">
          <div>
            <h2>Админка</h2>
            <p className="admin-subtitle">
              {user?.name || user?.email}
              {user?.role?.name || user?.role?.slug
                ? ` · ${user.role.name || user.role.slug}`
                : ''}
            </p>
          </div>
          <Link to="/categories" className="btn btn-secondary">
            ← В каталог
          </Link>
        </div>

        <nav className="admin-nav">
          <NavLink to="/admin" end className={({ isActive }) => (isActive ? 'admin-nav-link active' : 'admin-nav-link')}>
            Обзор
          </NavLink>
          {isAdmin && (
            <NavLink to="/admin/users" className={({ isActive }) => (isActive ? 'admin-nav-link active' : 'admin-nav-link')}>
              Пользователи
            </NavLink>
          )}
          <NavLink to="/admin/orders" className={({ isActive }) => (isActive ? 'admin-nav-link active' : 'admin-nav-link')}>
            Заказы
          </NavLink>
          <NavLink to="/admin/notifications" className={({ isActive }) => (isActive ? 'admin-nav-link active' : 'admin-nav-link')}>
            Уведомления
          </NavLink>
          <NavLink to="/admin/analytics/users" className={({ isActive }) => (isActive ? 'admin-nav-link active' : 'admin-nav-link')}>
            Пользователи (аналитика)
          </NavLink>
          <NavLink to="/admin/analytics/orders" className={({ isActive }) => (isActive ? 'admin-nav-link active' : 'admin-nav-link')}>
            Заказы (аналитика)
          </NavLink>
          <NavLink to="/admin/analytics/search" className={({ isActive }) => (isActive ? 'admin-nav-link active' : 'admin-nav-link')}>
            Поиск (аналитика)
          </NavLink>
          <a
            href="http://localhost:8123/play"
            target="_blank"
            rel="noreferrer"
            className="admin-nav-link"
          >
            ClickHouse Play
          </a>
        </nav>

        <Outlet context={{ user }} />
      </div>
    </div>
  )
}
