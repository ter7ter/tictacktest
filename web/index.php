<?php
session_start();

// Handle user change request
if (isset($_GET['new_user'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

require_once 'db.php';
require_once 'config.php'; // Подключаем конфигурацию

// --- DB & AUTH LOGIC ---
$pdo = get_db_connection();
ensure_table_exists($pdo);

// Helper to get player's Telegram data
function get_player_telegram_data($pdo, $token) {
    $stmt = $pdo->prepare("SELECT chat_id, telegram_username FROM telegram_auth WHERE token = ? AND status = 'verified'");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$is_authenticated = false;
$player_telegram_data = null;
if (isset($_SESSION['game_token'])) {
    $player_telegram_data = get_player_telegram_data($pdo, $_SESSION['game_token']);
    if ($player_telegram_data) {
        $is_authenticated = true;
    } else {
        // Token might be invalid or not verified yet, clear session token to re-authenticate
        unset($_SESSION['game_token']);
    }
}

if (!$is_authenticated && !isset($_SESSION['game_token'])) {
    // New user or expired/invalid token: create a token
    try {
        $token = bin2hex(random_bytes(16));
        $_SESSION['game_token'] = $token;
        $stmt = $pdo->prepare("INSERT INTO telegram_auth (token) VALUES (?)");
        $stmt->execute([$token]);
    } catch (Exception $e) {
        die('Failed to generate token or save to DB: ' . $e->getMessage());
    }
}

$login_url = 'https://t.me/' . TELEGRAM_BOT_NAME . '?start=' . ($_SESSION['game_token'] ?? '');


// --- GAME LOGIC (integrated) ---
// These are included only if authenticated, so variables are only declared once.
if ($is_authenticated) {
    // --- Configurations ---
    $player_marker = '🌸';
    $computer_marker = '🌿';

    // --- Game Functions ---
    function send_telegram_message($target_chat_id, $message, $bot_token) {
        if (empty($bot_token) || empty($target_chat_id)) {
            error_log("Cannot send Telegram message: bot token or chat ID is missing.");
            return;
        }
        $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
        $data = ['chat_id' => $target_chat_id, 'text' => $message, 'parse_mode' => 'HTML'];
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 1, // Уменьшено до 1 секунды
                'ignore_errors' => true // Не считать HTTP-ошибки фатальными
            ]
        ];
        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
    }

    function generate_promocode() { return random_int(10000, 99999); }
    function new_game() {
        $_SESSION['board'] = array_fill(0, 9, null);
        $_SESSION['winner'] = null;
        $_SESSION['promocode'] = null;
    }

    function check_winner() {
        $board = $_SESSION['board'];
        $winning_combinations = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
        foreach ($winning_combinations as $combination) {
            if ($board[$combination[0]] !== null && $board[$combination[0]] === $board[$combination[1]] && $board[$combination[1]] === $board[$combination[2]]) {
                return $board[$combination[0]];
            }
        }
        return in_array(null, $board, true) ? null : 'draw';
    }

    function computer_move() {
        global $player_marker, $computer_marker;
        $board = $_SESSION['board'];
        $available_moves = array_keys($board, null, true);
        if (empty($available_moves) || $_SESSION['winner']) return;

        foreach ($available_moves as $move) {
            $temp_board = $board; $temp_board[$move] = $computer_marker;
            if (check_winner_for_board($temp_board) === $computer_marker) { $_SESSION['board'][$move] = $computer_marker; return; }
        }
        if ((random_int(1, 100) > 40)) { // 60% chance to block
            foreach ($available_moves as $move) {
                $temp_board = $board; $temp_board[$move] = $player_marker;
                if (check_winner_for_board($temp_board) === $player_marker) { $_SESSION['board'][$move] = $computer_marker; return; }
            }
        }
        $strategic_moves = [4, 0, 2, 6, 8];
        foreach ($strategic_moves as $move) {
            if (in_array($move, $available_moves)) { $_SESSION['board'][$move] = $computer_marker; return; }
        }
        $random_move = $available_moves[array_rand($available_moves)];
        $_SESSION['board'][$random_move] = $computer_marker;
    }

    function check_winner_for_board($board) {
        $winning_combinations = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
        foreach ($winning_combinations as $combination) {
            if ($board[$combination[0]] !== null && $board[$combination[0]] === $board[$combination[1]] && $board[$combination[1]] === $board[$combination[2]]) {
                return $board[$combination[0]];
            }
        }
        return null;
    }

    // Handle new game request
    if (!isset($_SESSION['board']) || isset($_GET['new_game'])) {
        new_game();
        session_write_close(); // Закрываем сессию перед редиректом
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?')); // Redirect to clean URL
        exit;
    }

    // Handle player's move
    if (isset($_GET['move']) && $_SESSION['winner'] === null) {
        $move = (int)$_GET['move'];
        if ($_SESSION['board'][$move] === null) {
            $_SESSION['board'][$move] = $player_marker;
            $winner_after_player = check_winner();

            if ($winner_after_player === null) {
                computer_move();
                $_SESSION['winner'] = check_winner();
                if ($_SESSION['winner'] === $computer_marker) {
                    send_telegram_message($player_telegram_data['chat_id'], "😢 <b>Компьютер победил.</b> Попробуйте еще раз!", TELEGRAM_BOT_TOKEN);
                }
            } else {
                $_SESSION['winner'] = $winner_after_player;
            }
            
            if ($_SESSION['winner'] === $player_marker) {
                $promocode = generate_promocode();
                $_SESSION['promocode'] = $promocode;
                send_telegram_message($player_telegram_data['chat_id'], "🥳 <b>Поздравляем! Вы победили!</b>\n\nВаш промокод: <code>" . $promocode . "</code>", TELEGRAM_BOT_TOKEN);
            }
            
            // Redirect to show the result of the move
            session_write_close(); // Закрываем сессию перед редиректом
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }
    }

    $winner = $_SESSION['winner'];
    $message = '';
    if ($winner === $player_marker) $message = "Поздравляем, вы победили!";
    elseif ($winner === $computer_marker) $message = "Компьютер победил. Попробуйте еще раз!";
    elseif ($winner === 'draw') $message = "Ничья! Сыграем снова?";
} // End of if ($is_authenticated)

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_authenticated ? 'Крестики-нолики' : 'Авторизация'; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<?php if ($is_authenticated): ?>
    
    <div class="game-container">
        <header>
            <h1>Крестики-нолики</h1>
            <p>Игрок: <span class="username-display">@<?php echo htmlspecialchars($player_telegram_data['telegram_username'] ?? 'Гость'); ?></span> (<a href="?new_user=1">сменить</a>)</p>
        </header>

        <div class="board">
            <?php for ($i = 0; $i < 9; $i++): ?>
                <a href="<?php echo ($_SESSION['board'][$i] === null && !$winner) ? "?move=$i" : '#'; ?>" class="cell">
                    <?php echo $_SESSION['board'][$i]; ?>
                </a>
            <?php endfor; ?>
        </div>

        <?php if ($winner): ?>
            <div class="result-message">
                <p><?php echo $message; ?></p>
                <?php if ($winner === $player_marker && isset($_SESSION['promocode'])): ?>
                    <p class="promocode">Ваш промокод: <strong><?php echo $_SESSION['promocode']; ?></strong></p>
                    <p class="small-text">Мы отправили вам промокод в Telegram!</p>
                <?php endif; ?>
                <a href="?new_game=1" class="play-again-button">Сыграть ещё раз</a>
            </div>
        <?php else: ?>
            <a href="?new_game=1" class="reset-button">Начать заново</a>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="welcome-container">
        <div class="welcome-form">
            <h1>Крестики-нолики</h1>
            <p>Чтобы начать игру и получать призы, пожалуйста, авторизуйтесь через нашего Telegram-бота.</p>
            <a href="<?php echo $login_url; ?>" target="_blank" class="telegram-button">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.444 3.484a1.203 1.203 0 0 0-1.556-.31l-16.45 6.643a1.192 1.192 0 0 0-.25 2.193l4.63 1.458 10.84-6.84-8.736 7.893.303 4.54a1.2 1.2 0 0 0 2.054.83l2.49-2.4 4.354 3.23a1.19 1.19 0 0 0 1.834-.73l2.7-13.08a1.192 1.192 0 0 0-.53-1.354Z" fill="#fff"/></svg>
                <span>Войти через Telegram</span>
            </a>
            <p class="small-text">После нажатия "Start" в боте, эта страница обновится автоматически.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const interval = setInterval(function() {
                fetch('check_auth.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'verified') {
                            clearInterval(interval);
                            window.location.reload();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking auth status:', error);
                    });
            }, 3000); // Check every 3 seconds
        });
    </script>

<?php endif; ?>

</body>
</html>