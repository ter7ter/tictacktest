<?php
/*function get_db_connection() {
    $host = 'db'; // The service name from docker-compose.yml
    $dbname = 'mydatabase';
    $user = 'user';
    $pass = 'password';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection error. Please check logs.");
    }
}*/
function get_db_connection()
{
    $db_url = getenv('DATABASE_URL'); // Render предоставит эту переменную
    if (empty($db_url)) {
        // Локальные настройки для Docker
        $host = 'db';
        $dbname = 'mydatabase';
        $user = 'user';
        $pass = 'password';
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    } else {
        // Настройки для PostgreSQL на Render
        $db_parts = parse_url($db_url);
        $host = $db_parts['host'];
        $port = $db_parts['port'];
        $dbname = ltrim($db_parts['path'], '/');
        $user = $db_parts['user'];
        $pass = $db_parts['pass'];
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    }
    try {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

function ensure_table_exists($pdo) {
    // 1. Создаем таблицу, если ее не существует
    $create_sql = "
    CREATE TABLE IF NOT EXISTS telegram_auth (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(32) NOT NULL,
        chat_id BIGINT DEFAULT NULL,
        status ENUM('pending', 'verified') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    try {
        $pdo->exec($create_sql);
    } catch (PDOException $e) {
        die("Table creation failed: " . $e->getMessage());
    }

    // 2. Проверяем, существует ли колонка 'telegram_username'
    $check_column_sql = "
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'telegram_auth' AND COLUMN_NAME = 'telegram_username';
    ";
    $stmt = $pdo->query($check_column_sql);
    $column_exists = $stmt->fetchColumn();

    // 3. Если колонки нет, добавляем ее
    if ($column_exists == 0) {
        try {
            $alter_sql = "ALTER TABLE telegram_auth ADD COLUMN telegram_username VARCHAR(255) DEFAULT NULL AFTER chat_id;";
            $pdo->exec($alter_sql);
        } catch (PDOException $e) {
            die("Failed to alter table: " . $e->getMessage());
        }
    }
}