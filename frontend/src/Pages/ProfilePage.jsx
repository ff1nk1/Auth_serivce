// ProfilePage.jsx
import { useEffect, useState } from 'react';
import { api } from '../auth/api' 
import Profile from './Profile'; 

export default function ProfilePage() {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // 1. Как только пользователь заходит на /profile, срабатывает этот useEffect
    // 2. Делаем GET запрос на бэкенд, чтобы получить текущего юзера
    // (Укажи здесь правильный URL твоего бэкенда, например '/api/user' или '/me')
    api.get('/user') 
      .then((response) => {
        // 3. Бэкенд ответил 200 OK. Сохраняем пользователя в стейт
        setUser(response.data);
      })
      .catch((error) => {
        // 4. Если бэкенд ответил 401 (не авторизован), твой axios-перехватчик 
        // сам сделает refresh или выкинет на /login.
        console.error('Не удалось загрузить пользователя', error);
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, []); // Пустой массив означает: выполнить 1 раз при заходе на страницу

  // Пока ждем ответ от сервера, показываем крутилку/текст
  if (isLoading) {
    return <div style={{ textAlign: 'center', marginTop: '50px' }}>Загрузка профиля...</div>;
  }

  // Если юзер не загрузился (например, нет прав), ничего не показываем
  if (!user) {
    return null;
  }

  // 5. Данные получены! Рендерим твой компонент и передаем в него user
  return <Profile user={user} />;
}