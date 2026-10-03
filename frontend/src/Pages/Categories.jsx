import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../auth/api'; 
import '../css/catalog.css'; 


export default function Categories() {
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/categories')
            .then(response => {
                setCategories(response.data);
            })
            .catch(error => console.error("Ошибка загрузки категорий:", error))
            .finally(() => setLoading(false));
    }, []);

    return (
        <div className="catalog-page">
            <div className="catalog-card">
                <div className="catalog-header">
                    <h1>Каталог</h1>
                    <p>Выберите интересующий вас раздел</p>
                </div>

                {loading ? (
                    <div className="status-message">Загрузка категорий...</div>
                ) : (
                    <ul className="category-list">
                        {categories.map(category => (
                            <li key={category.id} className="category-item">
                                <Link to={`/categories/${category.slug}`} className="category-link">
                                    {category.name}
                                    <span className="arrow">→</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}