<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Приветствие</title>
</head>
<body>
    <h2>Здравствуйте, {{ $payload['name'] ?? 'пользователь' }}!</h2>
    <p>Спасибо за регистрацию на нашем сервисе.</p>
    <p>Ваш email: {{ $payload['email'] }}</p>
</body>
</html>