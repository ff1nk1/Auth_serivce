<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Отмена заказа</title>
</head>
<body>
    <h2>Здравствуйте, {{ $payload['name'] ?? 'покупатель' }}!</h2>
    <p>Ваш заказ №{{ $payload['order_id'] ?? '—' }} отменён.</p>
    @if(($payload['reason'] ?? null) === 'insufficient_stock')
        <p>К сожалению, одного или нескольких товаров нет в наличии, поэтому заказ не удалось зарезервировать.</p>
    @else
        <p>Заказ был отменён{{ ($payload['reason'] ?? null) === 'user_cancelled' ? ' по вашему запросу' : '' }}.</p>
    @endif
    @if(!empty($payload['total_amount']))
        <p>Сумма заказа: {{ $payload['total_amount'] }}.</p>
    @endif
</body>
</html>
