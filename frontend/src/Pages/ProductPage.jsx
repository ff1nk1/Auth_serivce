import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../auth/api';
import '../css/product_page.css';

export default function ProductPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    
    const [product, setProduct] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        setLoading(true);
        api.get(`catalog/products/${id}`)
            .then(response => {
                setProduct(response.data.product);
            })
            .catch(err => {
                console.error("Ошибка загрузки товара:", err);
                setError('Не удалось загрузить информацию о товаре.');
            })
            .finally(() => setLoading(false));
    }, [id]);

    if (loading) {
        return (
            <div className="product-details-page">
                <div className="status-message">Загрузка информации о товаре...</div>
            </div>
        );
    }

    if (error || !product) {
        return (
            <div className="product-details-page">
                <div className="status-message error">{error || 'Товар не найден'}</div>
                <button className="back-button" onClick={() => navigate(-1)}>
                    ← Вернуться назад
                </button>
            </div>
        );
    }

    return (
        <div className="product-details-page">
            <div className="product-container">
                <button className="back-button" onClick={() => navigate(-1)}>
                    ← Назад в каталог
                </button>
                
                <div className="product-full-card">
                    {/* Вывод картинки, если поле image_url не пустое */}
                    {product.image_url && (
                        <div className="product-image-container">
                            <img 
                                src={product.image_url} 
                                alt={product.name} 
                                className="product-image" 
                            />
                        </div>
                    )}

                    <h1>{product.name}</h1>
                    <div className="product-price-large">{product.price} ₽</div>
                    
                    {/* Бейджи с категорией и магазином */}
                    <div className="product-badges">
                        {product.category && (
                            <span className="badge">Категория: {product.category.name}</span>
                        )}
                        {product.store && (
                            <span className="badge">Магазин: {product.store.name}</span>
                        )}
                        <span className="badge">Артикул: #{product.id}</span>
                    </div>
                    
                    {/* Блок характеристик (показываем, если есть attributes) */}
                    {product.attributes && product.attributes.length > 0 && (
                        <div className="product-attributes-block">
                            <h3>Характеристики</h3>
                            <ul className="attributes-list">
                                {product.attributes.map(attr => (
                                    <li key={attr.id} className="attribute-item">
                                        {/* 
                                          Предполагается, что в ProductAttribute есть поля name и value.
                                          Если у вас они называются иначе (например, key/value), поменяйте здесь 
                                        */}
                                        <span className="attr-name">{attr.name}</span>
                                        <span className="attr-dots"></span>
                                        <span className="attr-value">{attr.value}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Блок с описанием */}
                    <div className="product-description-block">
                        <h3>Описание</h3>
                        <div className="product-description">
                            {product.description || 'Описание для этого товара пока не добавлено.'}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}