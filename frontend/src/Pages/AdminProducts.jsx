import React, { useState, useEffect } from 'react';
import { api } from '../auth/api';
import "../css/admin_cats_and_products.css"


export default function AdminProducts() {
    const [products, setProducts] = useState([]);
    const [categories, setCategories] = useState([]);
    const [editingId, setEditingId] = useState(null);
    const [loading, setLoading] = useState(true);
    
    // Стейты пагинации товаров
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);

    const initialForm = { store_id: '', category_id: '', name: '', description: '', image_url: '', price: '' };
    const [formData, setFormData] = useState(initialForm);

    // 1. Загружаем категории ОДИН раз (для выпадающего списка)
    useEffect(() => {
        api.get('admin/categories')
            .then(res => setCategories(res.data.data || res.data))
            .catch(err => console.error("Ошибка загрузки категорий:", err));
    }, []);

    // 2. Загружаем товары каждый раз при смене страницы
    useEffect(() => {
        setLoading(true);
        api.get(`admin/products?page=${currentPage}`)
            .then(response => {
                const data = response.data;
                if (data.data) {
                    setProducts(data.data);
                    setCurrentPage(data.current_page);
                    setLastPage(data.last_page);
                } else {
                    setProducts(data);
                }
            })
            .catch(error => console.error("Ошибка загрузки товаров:", error))
            .finally(() => setLoading(false));
    }, [currentPage]);

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        const url = editingId ? `admin/products/${editingId}` : 'admin/products';

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

    const handleEdit = (product) => {
        setEditingId(product.id);
        setFormData({
            store_id: product.store_id,
            category_id: product.category_id,
            name: product.name,
            description: product.description || '',
            image_url: product.image_url || '',
            price: product.price
        });
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Удалить товар?')) return;
        try {
            await api.delete(`admin/products/${id}`);
            window.location.reload();
        } catch (error) {
            console.error("Ошибка удаления:", error);
            alert("Не удалось удалить товар");
        }
    };

    if (loading && products.length === 0) return <div className="admin-page-container">Загрузка...</div>;

    return (
        <div className="admin-page-container">
            <h1 className="admin-page-title">Управление товарами</h1>

            <div className="admin-content-wrapper">
                <div className="admin-form-section">
                    <h2 className="admin-form-title">{editingId ? 'Редактировать товар' : 'Добавить товар'}</h2>
                    <form className="admin-form" onSubmit={handleSubmit}>
                        
                        <div className="form-group-row">
                            <div className="form-group">
                                <label className="form-label">Название</label>
                                <input className="form-input" type="text" name="name" value={formData.name} onChange={handleChange} required />
                            </div>
                            <div className="form-group">
                                <label className="form-label">Цена</label>
                                <input className="form-input" type="number" step="0.01" name="price" value={formData.price} onChange={handleChange} required />
                            </div>
                        </div>

                        <div className="form-group-row">
                            <div className="form-group">
                                <label className="form-label">Категория</label>
                                <select className="form-select" name="category_id" value={formData.category_id} onChange={handleChange} required>
                                    <option value="">Выберите категорию</option>
                                    {categories.map(cat => <option key={cat.id} value={cat.id}>{cat.name}</option>)}
                                </select>
                            </div>
                            <div className="form-group">
                                <label className="form-label">ID Магазина</label>
                                <input className="form-input" type="number" name="store_id" value={formData.store_id} onChange={handleChange} required />
                            </div>
                        </div>

                        <div className="form-group">
                            <label className="form-label">URL Картинки</label>
                            <input className="form-input" type="text" name="image_url" value={formData.image_url} onChange={handleChange} />
                        </div>

                        <div className="form-group">
                            <label className="form-label">Описание</label>
                            <textarea className="form-textarea" name="description" value={formData.description} onChange={handleChange} rows="3"></textarea>
                        </div>

                        <div className="form-actions">
                            <button className="btn-submit" type="submit">Сохранить</button>
                            {editingId && (
                                <button className="btn-cancel" type="button" onClick={() => { setEditingId(null); setFormData(initialForm); }}>Отмена</button>
                            )}
                        </div>
                    </form>
                </div>

                <div className="admin-list-section">
                    <table className="admin-table">
                        <thead className="admin-table-header">
                            <tr>
                                <th>Название</th>
                                <th>Цена</th>
                                <th>Категория ID</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody className="admin-table-body">
                            {products.map(prod => (
                                <tr key={prod.id} className="admin-table-row">
                                    <td className="admin-table-cell">{prod.name}</td>
                                    <td className="admin-table-cell">{prod.price} ₽</td>
                                    <td className="admin-table-cell">{prod.category_id}</td>
                                    <td className="admin-table-cell action-cells">
                                        <button className="btn-edit" onClick={() => handleEdit(prod)}>Ред.</button>
                                        <button className="btn-delete" onClick={() => handleDelete(prod.id)}>Удалить</button>
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