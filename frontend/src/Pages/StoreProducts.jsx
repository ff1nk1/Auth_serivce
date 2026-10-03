import { useState, useEffect } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { api } from '../auth/api';

export default function StoreProducts() {
    const { storeId } = useParams(); 
    const [searchParams, setSearchParams] = useSearchParams();
    
    const [products, setProducts] = useState([]);
    
    // Состояние пагинации
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1 });
    const [loading, setLoading] = useState(true);

    const [statusFilter, setStatusFilter] = useState(searchParams.get('status') || 'all');

    const applyFilters = (e) => {
        e.preventDefault();
        setSearchParams({ status: statusFilter, page: 1 }); // Сброс на 1 страницу
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
        api.get(`/products/stores/${storeId}`, { params: searchParams })
            .then(response => {
                setProducts(response.data.products.data);
                
                // Сохраняем инфу о страницах
                setPagination({
                    current_page: response.data.products.current_page,
                    last_page: response.data.products.last_page
                });
            })
            .catch(error => console.error("Ошибка загрузки товаров магазина:", error))
            .finally(() => setLoading(false));
    }, [storeId, searchParams]);

    return (
        <div className="catalog-page">
            <div className="catalog-card products-container">
                <div className="catalog-header">
                    <h1>Магазин #{storeId}</h1>
                    <p>Каталог товаров продавца</p>
                </div>
                
                <form className="filters-form" onSubmit={applyFilters}>
                    <div className="filters-wrapper">
                        <select 
                            className="profile-input filter-select" 
                            value={statusFilter} 
                            onChange={e => setStatusFilter(e.target.value)}
                        >
                            <option value="all">Все товары</option>
                            <option value="in_stock">В наличии</option>
                            <option value="discount">Со скидкой</option>
                        </select>
                        <button className="save-button filter-button" type="submit">Фильтровать</button>
                    </div>
                </form>

                {loading ? (
                    <div className="status-message">Загрузка...</div>
                ) : (
                    <>
                        <div className="products-grid">
                            {products.length === 0 ? (
                                <div className="status-message error" style={{ gridColumn: '1 / -1' }}>
                                    Товары не найдены.
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

                        {/* Блок пагинации */}
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