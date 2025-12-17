FROM php:8.2-apache

# Устанавливаем расширения для работы с MySQL (для локальной разработки) и PostgreSQL (для Render)
RUN docker-php-ext-install pdo_mysql pdo_pgsql

# Включаем модуль Apache для поддержки чистых URL (хорошая практика)
RUN a2enmod rewrite

# Копируем все файлы из текущей директории (контекста сборки) в рабочую папку Apache внутри контейнера
COPY . /var/www/html/

# Устанавливаем правильные права на файлы, чтобы Apache мог их читать
RUN chown -R www-data:www-data /var/www/html