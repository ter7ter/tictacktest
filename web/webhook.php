<?php
require_once 'db.php';
require_once 'config.php'; // Подключаем конфигурацию

// --- LOGIC ---
$input = file_get_contents('php://input');
file_put_contents('webhook_log.txt', $input . PHP_EOL, FILE_APPEND); // Лог для отладки

$update = json_decode($input, true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];
    $telegram_username = $message['from']['username'] ?? null; // Сохраняем ник

    if (strpos($text, '/start ') === 0) {
        $token = trim(substr($text, 7));

        if (strlen($token) === 32) {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("UPDATE telegram_auth SET chat_id = ?, telegram_username = ?, status = 'verified' WHERE token = ? AND status = 'pending'");
                $stmt->execute([$chat_id, $telegram_username, $token]);

                if ($stmt->rowCount() > 0) {
                    $reply_message = "✅ Вы успешно авторизованы! Можете вернуться на страницу с игрой.";
                } else {
                    $reply_message = "🤔 Этот код для авторизации уже использован или недействителен. Пожалуйста, обновите страницу с игрой, чтобы получить новый.";
                }
                
                $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id={$chat_id}&text=" . urlencode($reply_message);
                @file_get_contents($url);

            } catch (PDOException $e) {
                file_put_contents('webhook_log.txt', 'DB Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            }
        }
    } else {
        $chat_id = $update['message']['chat']['id'];
        $reply_message = "Чтобы начать игру, пожалуйста, перейдите на сайт и нажмите кнопку авторизации.";
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id={$chat_id}&text=" . urlencode($reply_message);
        @file_get_contents($url);
    }
}

http_response_code(200);
echo 'OK';
