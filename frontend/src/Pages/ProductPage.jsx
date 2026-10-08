import { useState, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { catalogApi } from '../api/catalogClient';
import { orderApi } from '../api/orderClient';
import { formatImageUrl, PLACEHOLDER_IMAGE } from '../utils/formatImageUrl';
import '../css/product_page.css';
import '../css/orders.css';

export default function ProductPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    
    const [product, setProduct] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [quantity, setQuantity] = useState(1);
    const [ordering, setOrdering] = useState(false);
    const [orderMessage, setOrderMessage] = useState('');
    const [orderError, setOrderError] = useState('');

    useEffect(() => {
        setLoading(true);
        catalogApi.get(`catalog/products/${id}`)
            .then(response => {
                setProduct(response.data.product);
            })
            .catch(err => {
                console.error("Ошибка загрузки товара:", err);
                setError('Не удалось загрузить информацию о товаре.');
            })
            .finally(() => setLoading(false));
    }, [id]);

    const handleOrder = async () => {
        if (!product || ordering) return;
        setOrdering(true);
        setOrderMessage('');
        setOrderError('');
        try {
            const { data } = await orderApi.post(
                '/orders',
                {
                    items: [{ product_id: product.id, quantity: Number(quantity) || 1 }],
                },
                {
                    headers: {
                        'Idempotency-Key': crypto.randomUUID(),
                    },
                }
            );
            setOrderMessage('Заказ создан.');
            navigate(`/orders/${data.id}`);
        } catch (err) {
            const msg =
                err.response?.data?.message ||
                err.response?.data?.errors?.items?.[0] ||
                'Не удалось создать заказ. Нужен локальный снапшот товара в order_service.';
            setOrderError(msg);
        } finally {
            setOrdering(false);
        }
    };

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

                    <h1>{product.name}</h1>
                    <div className="product-price-large">{product.price} ₽</div>
                    
                    {/* Бейджи с категорией и магазином */}
                    <div className="product-badges">
                        {product.category && (
                            <span className="badge">Категория: {product.category.name}</span>
                        )}
                        {product.store && (
                            <Link
                                to={`/stores/${product.store.id || product.store_id}/products`}
                                className="badge"
                            >
                                Магазин: {product.store.name}
                            </Link>
                        )}
                        <span className="badge">Артикул: #{product.id}</span>
                    </div>

                    <div className="order-qty-row">
                        <label htmlFor="order-qty">Количество</label>
                        <input
                            id="order-qty"
                            type="number"
                            min={1}
                            value={quantity}
                            onChange={(e) => setQuantity(Math.max(1, Number(e.target.value) || 1))}
                        />
                        <button
                            className="order-btn order-btn-primary"
                            onClick={handleOrder}
                            disabled={ordering}
                        >
                            {ordering ? 'Оформление...' : 'Заказать'}
                        </button>
                    </div>
                    {orderMessage && <div className="status-message success">{orderMessage}</div>}
                    {orderError && <div className="status-message error">{orderError}</div>}
                    <p>
                        <Link to="/orders">Мои заказы →</Link>
                    </p>
                    
                    {/* Блок характеристик (показываем, если есть attributes) */}
                    {product.attributes && product.attributes.length > 0 && (
                        <div className="product-attributes-block">
                            <h3>Характеристики</h3>
                            <ul className="attributes-list">
                                {product.attributes.map(attr => (
                                    <li key={attr.id} className="attribute-item">
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