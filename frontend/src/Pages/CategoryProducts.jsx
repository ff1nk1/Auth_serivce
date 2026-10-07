import { useState, useEffect } from 'react';
import { useParams, useSearchParams, Link } from 'react-router-dom';
import { catalogApi } from '../api/catalogClient';

// Автономная SVG-заглушка в формате Data URI (не зависит от внешних серверов)
const PLACEHOLDER_IMAGE = "data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='150' height='150' viewBox='0 0 150 150'%3E%3Crect width='100%25' height='100%25' fill='%23f3f4f6'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='14' fill='%239ca3af'%3EНет фото%3C/text%3E%3C/svg%3E";
// Вспомогательная функция для корректного формирования URL изображения
const formatImageUrl = (url) => {
    if (!url) return PLACEHOLDER_IMAGE;
    if (url.startsWith('data:')) return url;

    // Если в БД случайно попал внутренний имя сервиса Docker (minio:9000)
    if (url.includes('minio:9000')) {
        return url.replace('http://minio:9000', 'https://127.0.0.1/storage');
    }

    // Если ссылка уже абсолютная (http/https)
    if (url.startsWith('http://') || url.startsWith('https://')) {
        return url;
    }

    // Относительный путь (/storage/media/...) перенаправляем на HTTPS Nginx
    const cleanPath = url.startsWith('/') ? url : `/${url}`;
    return `https://127.0.0.1${cleanPath}`;
};

export default function CategoryProducts() {
    const { slug } = useParams();
    const [searchParams, setSearchParams] = useSearchParams();
    
    const [category, setCategory] = useState(null);
    const [products, setProducts] = useState([]);
    
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1
    });
    
    const [loading, setLoading] = useState(true);

    const [minPrice, setMinPrice] = useState(searchParams.get('min_price') || '');
    const [maxPrice, setMaxPrice] = useState(searchParams.get('max_price') || '');
    const [sortBy, setSortBy] = useState(searchParams.get('sort') || 'new');

    const applyFilters = (e) => {
        e.preventDefault();
        setSearchParams({
            min_price: minPrice,
            max_price: maxPrice,
            sort: sortBy,
            page: 1
        });
    };

    const handlePageChange = (newPage) => {
        setSearchParams(prevParams => {
            prevParams.set('page', newPage);
            return prevParams;
        });
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    useEffect(() => {
        setLoading(true);
        catalogApi.get(`catalog/categories/${slug}/products`, { params: searchParams })
            .then(response => {
                const data = response.data;
                setCategory(data.category);
                
                setProducts(data.products.data);
                
                setPagination({
                    current_page: data.products.current_page,
                    last_page: data.products.last_page
                });
            })
            .catch(error => console.error("Ошибка загрузки товаров:", error))
            .finally(() => setLoading(false));
    }, [slug, searchParams]);
    console.log('Товары из API:', products.map(p => p.image_url));
    return (
        <div className="catalog-page">
            <div className="catalog-card products-container">
                <div className="catalog-header">
                    <h1>{category ? category.name : 'Загрузка...'}</h1>
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
                                <Link 
                                    to={`/products/${product.id}`} 
                                    key={product.id} 
                                    className="product-card"
                                >
                                    {/* БЛОК КАРТИНКИ */}
                                    <div className="product-image-container">
                                        <img 
                                            src={formatImageUrl(product.image_url)} 
                                            alt={product.name}
                                            className="product-image"
                                            onError={(e) => {
                                                e.target.onerror = null;
                                                e.target.src = PLACEHOLDER_IMAGE;
                                            }}
                                        />
                                    </div>

                                    {/* ИНФОРМАЦИЯ О ТОВАРЕ */}
                                    <div className="product-info">
                                        <h3 className="product-title">{product.name}</h3>
                                        <div className="product-price">{product.price} ₽</div>
                                    </div>
                                </Link>
                            ))}
                        </div>

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