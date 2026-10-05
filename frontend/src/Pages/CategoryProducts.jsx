import { useState, useEffect } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { api } from '../auth/api';

export default function CategoryProducts() {
    const { slug } = useParams();
    const [searchParams, setSearchParams] = useSearchParams();
    
    const [category, setCategory] = useState(null);
    const [products, setProducts] = useState([]);
    
    // НОВОЕ: состояние для хранения данных о страницах
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1
    });
    
    const [loading, setLoading] = useState(true);

    const [minPrice, setMinPrice] = useState(searchParams.get('min_price') || '');
    const [maxPrice, setMaxPrice] = useState(searchParams.get('max_price') || '');
    const [sortBy, setSortBy] = useState(searchParams.get('sort') || 'new');

    // Функция применения фильтров (ВАЖНО: сбрасываем страницу на 1)
    const applyFilters = (e) => {
        e.preventDefault();
        setSearchParams({
            min_price: minPrice,
            max_price: maxPrice,
            sort: sortBy,
            page: 1 // При новом фильтре всегда возвращаемся на первую страницу
        });
    };

    // НОВОЕ: Функция переключения страницы
    const handlePageChange = (newPage) => {
        setSearchParams(prevParams => {
            prevParams.set('page', newPage); // Добавляем или обновляем параметр page в URL
            return prevParams;
        });
        
        // Опционально: плавная прокрутка вверх при смене страницы
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    useEffect(() => {
        setLoading(true);
        // api.get сам возьмет ?page=2 из searchParams и отправит в Laravel
        api.get(`catalog/categories/${slug}/products`, { params: searchParams })
            .then(response => {
                const data = response.data;
                setCategory(data.category);
                
                // Сохраняем массив товаров
                setProducts(data.products.data);
                
                // Сохраняем информацию о пагинации
                setPagination({
                    current_page: data.products.current_page,
                    last_page: data.products.last_page
                });
            })
            .catch(error => console.error("Ошибка загрузки товаров:", error))
            .finally(() => setLoading(false));
    }, [slug, searchParams]);

    return (
        <div className="catalog-page">
            <div className="catalog-card products-container">
                <div className="catalog-header">
                    <h1>{category ? category.name : 'Загрузка...'}</h1>
                    {/* Можно вывести общее количество товаров, если нужно: data.products.total */}
                </div>
                
                <form className="filters-form" onSubmit={applyFilters}>
                    <div className="filters-wrapper">
                        <input 
                            className="profile-input filter-input" 
                            type="number" 
                            placeholder="Мин. цена" 
                            value={minPrice} 
                            onChange={e => setMinPrice(e.target.value)} 
                        />
                        <input 
                            className="profile-input filter-input" 
                            type="number" 
                            placeholder="Макс. цена" 
                            value={maxPrice} 
                            onChange={e => setMaxPrice(e.target.value)} 
                        />
                        <select 
                            className="profile-input filter-select" 
                            value={sortBy} 
                            onChange={e => setSortBy(e.target.value)}
                        >
                            <option value="new">Сначала новые</option>
                            <option value="price_asc">Сначала дешевые</option>
                            <option value="price_desc">Сначала дорогие</option>
                        </select>
                        <button className="save-button filter-button" type="submit">Применить</button>
                    </div>
                </form>

                {loading ? (
                    <div className="status-message">Загрузка товаров...</div>
                ) : (
                    <>
                        <div className="products-grid">
                            {products.length === 0 ? (
                                <div className="status-message error" style={{ gridColumn: '1 / -1' }}>
                                    По вашему запросу товары не найдены.
                                </div>
                            ) : null}
                            
                            {products.map(product => (
                                <div key={product.id} className="product-card">
                                    <div className="product-info">
                                        <h3 className="product-title">{product.name}</h3>
                                        <div className="product-price">{product.price} ₽</div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* НОВОЕ: Блок пагинации (показываем, если страниц больше 1) */}
                        {pagination.last_page > 1 && (
                            <div className="pagination">
                                <button 
                                    className="pagination-button"
                                    disabled={pagination.current_page === 1}
                                    onClick={() => handlePageChange(pagination.current_page - 1)}
                                >
                                    ← Назад
                                </button>
                                
                                <span className="pagination-info">
                                    Страница {pagination.current_page} из {pagination.last_page}
                                </span>
                                
                                <button 
                                    className="pagination-button"
                                    disabled={pagination.current_page === pagination.last_page}
                                    onClick={() => handlePageChange(pagination.current_page + 1)}
                                >
                                    Вперед →
                                </button>
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}