FROM php:8.2-apache

# 1. Обновляем список пакетов и устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    libpq-dev \
    default-libmysqlclient-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Устанавливаем расширения, явно указав сначала базовый PDO, а затем драйверы
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql

# 3. Включаем модуль Apache
RUN a2enmod rewrite

# 4. Копируем файлы приложения
COPY . /var/www/html/

# 5. Устанавливаем права на файлы
RUN chown -R www-data:www-data /var/www/html

