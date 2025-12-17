<?php
// --- TELEGRAM CONFIGURATION ---
// Вставьте ваши реальные данные сюда

// Читаем конфигурацию из переменных окружения на хостинге
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_BOT_NAME', getenv('TELEGRAM_BOT_NAME'));

// (Опционально) ID чата администратора для получения уведомлений
// define('TELEGRAM_ADMIN_CHAT_ID', 'YOUR_ADMIN_CHAT_ID');
