import React, { useState } from 'react';
import { useLoaderData, Link } from 'react-router-dom';
import { api } from '../auth/api';
import '../css/notifications.css';

export default function NotificationDetail() {
  const notification = useLoaderData();
  const [isResending, setIsResending] = useState(false);
  const [resendStatus, setResendStatus] = useState(null);

  // Обработчик повторной отправки
  const handleResend = async () => {
    setIsResending(true);
    setResendStatus(null);

    const id = notification._id || notification.id;

    try {
      // Запрос к ручке бэкенда для повторной публикации в Kafka/отправки
      await api.post(`/notifications/${id}/resend`);
      setResendStatus({
        type: 'success',
        message: 'Запрос на повторную отправку успешно отправлен!',
      });
    } catch (error) {
      console.error('Resend error:', error);
      setResendStatus({
        type: 'error',
        message: error.response?.data?.message || 'Не удалось отправить повторно.',
      });
    } finally {
      setIsResending(false);
    }
  };

  const notificationId = notification._id || notification.id;

  return (
    <div className="admin-page">
      <div className="admin-card">
        {/* Шапка с кнопками */}
        <div className="admin-header">
          <h2>Детали уведомления</h2>
          <div className="header-actions">
            <button
              type="button"
              onClick={handleResend}
              disabled={isResending}
              className="btn btn-primary resend-btn"
            >
              {isResending ? 'Отправка...' : 'Отправить ещё раз'}
            </button>
            <Link to="/notifications" className="btn btn-secondary">
              ← Назад к списку
            </Link>
          </div>
        </div>

        {/* Уведомление о результате повторной отправки */}
        {resendStatus && (
          <div className={`alert-banner alert-${resendStatus.type}`}>
            {resendStatus.message}
          </div>
        )}

        {/* Контент детализации */}
        <div className="detail-content">
          <div className="detail-row">
            <strong>ID записи:</strong> <span>{notificationId}</span>
          </div>

          <div className="detail-row">
            <strong>Message ID:</strong> <span>{notification.message_id || '—'}</span>
          </div>

          <div className="detail-row">
            <strong>Имя получателя:</strong> <span>{notification.name || '—'}</span>
          </div>

          <div className="detail-row">
            <strong>Email:</strong> <span>{notification.email}</span>
          </div>

          <div className="detail-row">
            <strong>Статус:</strong>{' '}
            <span className={`status-badge status-${notification.status}`}>
              {notification.status}
            </span>
          </div>

          <div className="detail-row">
            <strong>Попыток отправки (Retries):</strong> <span>{notification.retries ?? 0}</span>
          </div>

          <div className="detail-row">
            <strong>Отправлено в DLQ:</strong>{' '}
            <span className="dlq-status">
              {notification.moved_to_dlq ? 'Да (Ошибка)' : 'Нет'}
            </span>
          </div>

          {notification.error_message && (
            <div className="error-box">
              <strong className="error-title">Ошибка отправки:</strong>
              <p className="error-text">{notification.error_message}</p>
            </div>
          )}

          <div className="detail-row payload-container">
            <strong>Payload (содержимое Kafka сообщения):</strong>
            <pre className="payload-pre">
              {JSON.stringify(notification.payload, null, 2)}
            </pre>
          </div>
        </div>
      </div>
    </div>
  );
}