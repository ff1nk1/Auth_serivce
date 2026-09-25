import { useState } from 'react';
import '../../css/profile.css';

export default function Profile({ user }) {
  const [name, setName] = useState(user.name || '');
  const [number, setNumber] = useState(user.number || '');

  const [loadingField, setLoadingField] = useState(null);
  const [status, setStatus] = useState({ message: '', type: '' });

  const handleUpdate = async (field, value) => {
    setLoadingField(field);
    setStatus({ message: '', type: '' });

    try {
      const response = await fetch('/profile', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ [field]: value }),
      });

      if (!response.ok) {
        throw new Error('Ошибка при изменении данных');
      }

      setStatus({ message: 'Данные успешно обновлены!', type: 'success' });
    } catch (error) {
      setStatus({ message: error.message, type: 'error' });
    } finally {
      setLoadingField(null);
    }
  };

  const avatarInitial = (name || user.email || 'U').charAt(0).toUpperCase();

  return (
    <div className="profile-page">
      <div className="profile-card">
        
        {/* Заголовок с информацией о пользователе */}
        <div className="profile-header">
          <div className="profile-avatar">{avatarInitial}</div>
          <div className="profile-title-group">
            <h1>{name || 'Профиль'}</h1>
            <p>{user.email}</p>
          </div>
        </div>

        {/* Служебные данные (ID и Role ID) */}
        <div className="profile-badges">
          <span className="badge">ID: {user.id}</span>
          <span className="badge">Role ID: {user.role_id}</span>
        </div>

        {/* Статусное сообщение об успехе или ошибке */}
        {status.message && (
          <div className={`status-message ${status.type}`}>
            {status.message}
          </div>
        )}

        {/* Интерактивные поля ввода */}
        <div className="profile-form">
          <div className="field-group">
            <label htmlFor="profile-name">Имя</label>
            <div className="input-wrapper">
              <input
                id="profile-name"
                type="text"
                className="profile-input"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Введите имя"
              />
              <button
                className="save-button"
                onClick={() => handleUpdate('name', name)}
                disabled={loadingField === 'name'}
              >
                {loadingField === 'name' ? 'Сохранение...' : 'Изменить'}
              </button>
            </div>
          </div>

          <div className="field-group">
            <label htmlFor="profile-number">Номер телефона</label>
            <div className="input-wrapper">
              <input
                id="profile-number"
                type="tel"
                className="profile-input"
                value={number}
                onChange={(e) => setNumber(e.target.value)}
                placeholder="+7 (999) 000-00-00"
              />
              <button
                className="save-button"
                onClick={() => handleUpdate('number', number)}
                disabled={loadingField === 'number'}
              >
                {loadingField === 'number' ? 'Сохранение...' : 'Изменить'}
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}