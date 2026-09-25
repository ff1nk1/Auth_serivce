import { useState } from 'react';
import { api } from '../auth/api' 
import '../css/profile.css';

export default function Profile({ user }) {
  const [name, setName] = useState(user.name || '');
  const [number, setNumber] = useState(user.number || '');

  const [loadingField, setLoadingField] = useState(null);
  const [status, setStatus] = useState({ message: '', type: '' });

  const handleUpdate = async (field, value) => {
    setLoadingField(field);
    setStatus({ message: '', type: '' });

    try {
      // Используем твой api, он сам подставит baseURL, куки и токены (withCredentials: true)
      // Если бэкенд вернет 401, твой интерсептор сам обновит сессию и повторит этот запрос!
      await api.patch('/profile', { [field]: value });

      setStatus({ message: 'Данные успешно обновлены!', type: 'success' });
    } catch (error) {
      // Axios кладет ответ сервера в error.response
      const errorMessage = error.response?.data?.message || 'Ошибка при изменении данных';
      setStatus({ message: errorMessage, type: 'error' });
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