import { Link, useOutletContext } from 'react-router-dom'

export default function AdminHub() {
  const { user } = useOutletContext()
  const isAdmin = user?.role?.slug === 'admin'

  return (
    <div className="admin-hub">
      <p className="admin-hub-intro">Разделы панели управления</p>
      <ul className="admin-hub-list">
        {isAdmin && (
          <li>
            <Link to="/admin/users" className="admin-hub-link">
              <strong>Пользователи</strong>
              <span>Список аккаунтов и смена ролей</span>
            </Link>
          </li>
        )}
        <li>
          <Link to="/admin/orders" className="admin-hub-link">
            <strong>Заказы</strong>
            <span>Все заказы в системе</span>
          </Link>
        </li>
        <li>
          <Link to="/admin/notifications" className="admin-hub-link">
            <strong>Уведомления</strong>
            <span>Email-сообщения из MongoDB</span>
          </Link>
        </li>
        {isAdmin && (
          <li>
            <a href="/admin-catalog" className="admin-hub-link">
              <strong>Каталог</strong>
              <span>Товары, категории и остатки (Filament)</span>
            </a>
          </li>
        )}
      </ul>
    </div>
  )
}
