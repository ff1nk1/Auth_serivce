<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Оплата заказа</title>
</head>
<body>
    <h2>Здравствуйте, {{ $payload['name'] ?? 'покупатель' }}!</h2>
    <p>Мы получили оплату по заказу №{{ $payload['order_id'] ?? '—' }}.</p>
    <p>Сумма: {{ $payload['total_amount'] ?? '—' }}.</p>
    <p>Спасибо за покупку!</p>
</body>
</html>
