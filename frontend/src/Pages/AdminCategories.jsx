import React, { useState, useEffect } from 'react';
import { api } from '../auth/api';
import "../css/admin_cats_and_products.css"


export default function AdminCategories() {
    const [categories, setCategories] = useState([]);
    const [formData, setFormData] = useState({ name: '', slug: '', parent_id: '' });
    const [editingId, setEditingId] = useState(null);
    const [loading, setLoading] = useState(true);
    
    // Стейты пагинации
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);

    useEffect(() => {
        setLoading(true);
        // Добавляем параметр page к запросу
        api.get(`admin/categories?page=${currentPage}`)
            .then(response => {
                const data = response.data;
                if (data.data) { // Если пришла пагинация Laravel
                    setCategories(data.data);
                    setCurrentPage(data.current_page);
                    setLastPage(data.last_page);
                } else {
                    setCategories(data);
                }
            })
            .catch(error => console.error("Ошибка загрузки категорий:", error))
            .finally(() => setLoading(false));
    }, [currentPage]); // Перезапрашиваем при смене страницы

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const url = editingId ? `admin/categories/${editingId}` : 'admin/categories';
        
        try {
            if (editingId) {
                await api.put(url, formData);
            } else {
                await api.post(url, formData);
            }
            alert('Успешно сохранено');
            window.location.reload();
        } catch (error) {
            if (error.response?.data?.errors) {
                alert('Ошибка валидации: ' + JSON.stringify(error.response.data.errors));
            } else {
                console.error("Ошибка сохранения:", error);
            }
        }
    };

    const handleEdit = (category) => {
        setEditingId(category.id);
        setFormData({ name: category.name, slug: category.slug, parent_id: category.parent_id || '' });
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Удалить категорию?')) return;
        try {
            await api.delete(`admin/categories/${id}`);
            // Если удаляем, просто запрашиваем страницу заново, чтобы таблица обновилась корректно
            setCurrentPage(currentPage); 
            window.location.reload();
        } catch (error) {
            console.error("Ошибка удаления:", error);
            alert("Не удалось удалить категорию");
        }
    };

    if (loading && categories.length === 0) return <div className="admin-page-container">Загрузка...</div>;

    return (
        <div className="admin-page-container">
            <h1 className="admin-page-title">Управление категориями</h1>

            <div className="admin-content-wrapper">
                <div className="admin-form-section">
                    <h2 className="admin-form-title">{editingId ? 'Редактировать' : 'Добавить'}</h2>
                    <form className="admin-form" onSubmit={handleSubmit}>
                        <div className="form-group">
                            <label className="form-label">Название</label>
                            <input className="form-input" type="text" name="name" value={formData.name} onChange={handleChange} required />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Slug (URL)</label>
                            <input className="form-input" type="text" name="slug" value={formData.slug} onChange={handleChange} required />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Родительская (ID)</label>
                            <input className="form-input" type="number" name="parent_id" value={formData.parent_id} onChange={handleChange} />
                        </div>
                        <div className="form-actions">
                            <button className="btn-submit" type="submit">Сохранить</button>
                            {editingId && (
                                <button className="btn-cancel" type="button" onClick={() => { setEditingId(null); setFormData({name:'', slug:'', parent_id:''}); }}>Отмена</button>
                            )}
                        </div>
                    </form>
                </div>

                <div className="admin-list-section">
                    <table className="admin-table">
                        <thead className="admin-table-header">
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Slug</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody className="admin-table-body">
                            {categories.map(cat => (
                                <tr key={cat.id} className="admin-table-row">
                                    <td className="admin-table-cell">{cat.id}</td>
                                    <td className="admin-table-cell">{cat.name}</td>
                                    <td className="admin-table-cell">{cat.slug}</td>
                                    <td className="admin-table-cell action-cells">
                                        <button className="btn-edit" onClick={() => handleEdit(cat)}>Ред.</button>
                                        <button className="btn-delete" onClick={() => handleDelete(cat.id)}>Удалить</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {/* ПАГИНАЦИЯ */}
                    {lastPage > 1 && (
                        <div className="pagination-container">
                            <button 
                                className="page-btn" 
                                disabled={currentPage === 1} 
                                onClick={() => setCurrentPage(prev => prev - 1)}
                            >
                                Назад
                            </button>
                            <span className="page-info">
                                Страница {currentPage} из {lastPage}
                            </span>
                            <button 
                                className="page-btn" 
                                disabled={currentPage === lastPage} 
                                onClick={() => setCurrentPage(prev => prev + 1)}
                            >
                                Вперед
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}