// loaders.js
import { api } from '../auth/api'; 

export async function adminLoader() {
  // Запрос за админскими данными. Если у юзера нет прав, бэк вернет 403
  const response = await api.get('/users'); 
  return response.data;
}

export async function notificationsLoader() {
  // Запрос за уведомлениями
  const response = await api.get('/notifications');
  return response.data;
}

export async function notificationDetailLoader({ params }) {
  const response = await api.get(`/notifications/${params.id}`); // Проверьте также слэш /notifications/ vs /notification/
  
  // Если бэкенд возвращает { data: { _id: ... } }, берем response.data.data
  return response.data?.data || response.data;
}