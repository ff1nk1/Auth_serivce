<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Подтверждение заказа</title>
</head>
<body>
    <h2>Здравствуйте, {{ $payload['name'] ?? 'покупатель' }}!</h2>
    <p>Ваш заказ №{{ $payload['order_id'] ?? '—' }} подтверждён.</p>
    <p>Сумма: {{ $payload['total_amount'] ?? '—' }}.</p>
    <p>Мы начали обработку заказа.</p>
</body>
</html>
