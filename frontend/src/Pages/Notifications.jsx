import React, { useState, useEffect, useCallback } from 'react';
import '../css/notifications.css';
import { api } from '../auth/api';
import { Link } from 'react-router-dom'; 
// Пример статусов
const STATUS_OPTIONS = [
    { value: '', label: 'Все статусы' },
    { value: 'sent', label: 'Отправлено' },
    { value: 'pending', label: 'В очереди' },
    { value: 'failed', label: 'Ошибка' },
];

export const NotificationsPage = () => {
    // Состояние фильтров
    const [filters, setFilters] = useState({
        status: '',
        email: '',
        date_from: '',
        date_to: '',
    });

    // Состояние пагинации и данных
    const [page, setPage] = useState(1);
    const [logs, setLogs] = useState([]);
    const [pagination, setPagination] = useState({
        currentPage: 1,
        lastPage: 1,
        total: 0,
        from: 0,
        to: 0,
    });
    const [isLoading, setIsLoading] = useState(false);

    const fetchNotifications = useCallback(async (targetPage = 1, currentFilters = filters) => {
        setIsLoading(true);
        try {
            // Формируем объект GET-параметров для Axios
            const params = {
                page: targetPage,
                ...(currentFilters.status && { status: currentFilters.status }),
                ...(currentFilters.email && { email: currentFilters.email }),
                ...(currentFilters.date_from && { date_from: currentFilters.date_from }),
                ...(currentFilters.date_to && { date_to: currentFilters.date_to }),
            };

            
            const response = await api.get('/notifications', { params });
            const data = response.data;

            // Заполняем полученные данные из Laravel Paginator
            setLogs(data.data || []);
            setPagination({
                currentPage: data.current_page || 1,
                lastPage: data.last_page || 1,
                total: data.total || 0,
                from: data.from || 0,
                to: data.to || 0,
            });
        } catch (error) {
            console.error('Fetch error:', error);
        } finally {
            setIsLoading(false);
        }
    }, [filters]);

    // Первая загрузка при монтировании
    useEffect(() => {
        fetchNotifications(1);
    }, []);

    // Обработчики изменений фильтров
    const handleInputChange = (field, value) => {
        setFilters((prev) => ({ ...prev, [field]: value }));
    };

    // Применение фильтров
    const handleApplyFilters = (e) => {
        e.preventDefault();
        setPage(1);
        fetchNotifications(1, filters);
    };

    // Сброс фильтров
    const handleResetFilters = () => {
        const resetState = { status: '', email: '', date_from: '', date_to: '' };
        setFilters(resetState);
        setPage(1);
        fetchNotifications(1, resetState);
    };

    // Смена страницы
    const handlePageChange = (newPage) => {
        if (newPage >= 1 && newPage <= pagination.lastPage) {
            setPage(newPage);
            fetchNotifications(newPage, filters);
        }
    };

    // Форматирование даты
    const formatDate = (dateString) => {
        if (!dateString) return '—';
        return new Date(dateString).toLocaleString('ru-RU', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <div className="admin-page">
            <div className="admin-card">
                {/* Шапка */}
                <div className="admin-header">
                    <h2>Журнал уведомлений</h2>
                </div>

                {/* Форма фильтрации */}
                <form className="filter-bar" onSubmit={handleApplyFilters}>
                    <div className="filter-group">
                        {/* Выпадающий список Статусов */}
                        <select
                            className="role-select filter-select"
                            value={filters.status}
                            onChange={(e) => handleInputChange('status', e.target.value)}
                        >
                            {STATUS_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>

                        {/* Поиск по Email */}
                        <input
                            type="text"
                            className="search-input"
                            placeholder="Поиск по Email..."
                            value={filters.email}
                            onChange={(e) => handleInputChange('email', e.target.value)}
                        />

                        {/* Диапазон дат "От" */}
                        <div className="date-input-wrapper">
                            <span className="date-label">От:</span>
                            <input
                                type="date"
                                className="search-input date-input"
                                value={filters.date_from}
                                onChange={(e) => handleInputChange('date_from', e.target.value)}
                            />
                        </div>

                        {/* Диапазон дат "До" */}
                        <div className="date-input-wrapper">
                            <span className="date-label">До:</span>
                            <input
                                type="date"
                                className="search-input date-input"
                                value={filters.date_to}
                                onChange={(e) => handleInputChange('date_to', e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Кнопки управления */}
                    <div className="filter-actions">
                        <button type="submit" className="btn btn-primary" disabled={isLoading}>
                            Применить
                        </button>
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={handleResetFilters}
                            disabled={isLoading}
                        >
                            Сбросить
                        </button>
                    </div>
                </form>

                {/* Таблица */}
                <div className="table-wrapper">
                    {isLoading ? (
                        <div className="table-loading">Загрузка данных...</div>
                    ) : logs.length === 0 ? (
                        <div className="table-empty">Записи не найдены</div>
                    ) : (
                        <table className="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Получатель (Email)</th>
                                    <th>Статус</th>
                                    <th>Дата создания</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr key={log._id || log.id}>
                                        <td className="id-cell">
                                        <Link 
                                        to={`/notifications/${log._id || log.id}`}                                                >
                                        {log._id || log.id}
                                        </Link>
                                        </td>
                                        <td>{log.email || '—'}</td>
                                        <td>
                                            <span className={`status-badge status-${log.status}`}>
                                                {log.status || 'не указан'}
                                            </span>
                                        </td>
                                        <td>{formatDate(log.created_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Пагинация */}
                {!isLoading && logs.length > 0 && (
                    <div className="pagination">
                        <div className="pagination-info">
                            Показано {pagination.from}–{pagination.to} из {pagination.total}
                        </div>
                        <div className="pagination-controls">
                            <button
                                className="btn btn-secondary"
                                onClick={() => handlePageChange(page - 1)}
                                disabled={page <= 1}
                            >
                                Назад
                            </button>
                            <button
                                className="btn btn-secondary"
                                onClick={() => handlePageChange(page + 1)}
                                disabled={page >= pagination.lastPage}
                            >
                                Вперед
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default NotificationsPage;