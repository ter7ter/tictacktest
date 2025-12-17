<?php
function get_db_connection() {
    $db_url = getenv('DATABASE_URL'); 
    if (empty($db_url)) {
        // --- Локальная разработка в Docker ---
        $host = 'db'; 
        $dbname = 'mydatabase'; 
        $user = 'user'; 
        $pass = 'password';
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    } else {
        // --- PostgreSQL на Render.com ---
        $db_parts = parse_url($db_url);

        // Извлекаем данные для подключения, обрабатывая отсутствующие части
        $host = $db_parts['host'] ?? null;
        $user = $db_parts['user'] ?? null;
        $pass = $db_parts['pass'] ?? null;
        $dbname = isset($db_parts['path']) ? ltrim($db_parts['path'], '/') : null;
        $port = $db_parts['port'] ?? 5432; // Используем порт по умолчанию для PostgreSQL, если он не указан

        if (!$host || !$user || !$pass || !$dbname) {
            die("Неверная переменная окружения DATABASE_URL. Не удалось разобрать данные.");
        }
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    }
    
    try {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // Логируем ошибку, не показывая пароль публично
        error_log("PDO Connection Error for user " . $user);
        die("Ошибка подключения к базе данных. Проверьте логи приложения.");
    }
}

function ensure_table_exists($pdo) {
    // 1. Создаем таблицу, если ее не существует
    $create_sql = "
    CREATE TABLE IF NOT EXISTS telegram_auth (
        id SERIAL PRIMARY KEY,
        token VARCHAR(32) NOT NULL,
        chat_id BIGINT DEFAULT NULL,
        telegram_username VARCHAR(255) DEFAULT NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (token)
    );
    ";
    try {
        $pdo->exec($create_sql);
    } catch (PDOException $e) {
        die("Table creation failed: " . $e->getMessage());
    }

    // 2. Проверяем, существует ли колонка 'telegram_username' (для обратной совместимости)
    // Для PostgreSQL запрос к information_schema выглядит немного иначе
    try {
        $check_column_sql = "SELECT 1 FROM information_schema.columns WHERE table_name='telegram_auth' AND column_name='telegram_username'";
        $stmt = $pdo->query($check_column_sql);
        if ($stmt->rowCount() == 0) {
             $alter_sql = "ALTER TABLE telegram_auth ADD COLUMN telegram_username VARCHAR(255) DEFAULT NULL;";
             $pdo->exec($alter_sql);
        }
    } catch (PDOException $e) {
        // Игнорируем ошибку, если колонка уже существует, но была добавлена в транзакции
    }
}
