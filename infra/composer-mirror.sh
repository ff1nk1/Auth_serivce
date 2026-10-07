#!/usr/bin/env sh
# Зеркало Packagist (обходит медленный/таймаутящийся GitHub dist).
# Запуск из корня сервиса: ../infra/composer-mirror.sh
set -e
composer config -g repos.packagist composer https://mirrors.aliyun.com/composer/
composer config -g github-protocols https
export COMPOSER_PROCESS_TIMEOUT=600
composer install --no-interaction --prefer-dist "$@"
echo "composer install done (aliyun mirror)"
