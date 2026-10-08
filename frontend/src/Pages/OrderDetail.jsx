import { useState } from 'react'
import { Link, useLoaderData, useNavigate, useRevalidator } from 'react-router-dom'
import { orderApi } from '../api/orderClient'
import '../css/orders.css'

export default function OrderDetail() {
  const { order: initialOrder } = useLoaderData()
  const [order, setOrder] = useState(initialOrder)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const navigate = useNavigate()
  const revalidator = useRevalidator()

  const canCancel = order?.status === 'pending'

  const handleCancel = async () => {
    if (!canCancel || busy) return
    setBusy(true)
    setError('')
    setMessage('')
    try {
      const { data } = await orderApi.post(`/orders/${order.id}/cancel`)
      setOrder(data)
      setMessage('Заказ отменён.')
      revalidator.revalidate()
    } catch (err) {
      setError(err.response?.data?.message || 'Не удалось отменить заказ.')
    } finally {
      setBusy(false)
    }
  }

  if (!order) {
    return (
      <div className="orders-page">
        <div className="orders-container">
          <div className="status-message error">Заказ не найден</div>
          <button className="order-btn" onClick={() => navigate('/orders')}>
            К списку
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="orders-page">
      <div className="orders-container">
        <div className="orders-nav">
          <Link to="/orders">← Мои заказы</Link>
          <Link to="/categories">Каталог</Link>
        </div>
        <div className="orders-card">
          <div className="orders-header">
            <h1>Заказ #{order.id}</h1>
            <p>
              <span className={`order-status ${order.status}`}>{order.status}</span>
              {' · '}
              {order.total_amount} ₽
            </p>
          </div>

          {message && <div className="status-message success">{message}</div>}
          {error && <div className="status-message error">{error}</div>}

          <h3>Позиции</h3>
          <ul className="orders-list">
            {(order.items || []).map((item) => (
              <li key={item.id} className="order-item">
                <div className="order-item-row">
                  <strong>
                    {item.product_snapshot?.name || `Товар #${item.product_id}`}
                  </strong>
                  <span>
                    {item.quantity} × {item.unit_price} ₽
                  </span>
                </div>
              </li>
            ))}
          </ul>

          {canCancel && (
            <div className="order-actions">
              <button
                className="order-btn order-btn-danger"
                onClick={handleCancel}
                disabled={busy}
              >
                {busy ? 'Отмена...' : 'Отменить заказ'}
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
