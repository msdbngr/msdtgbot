<?php
require_once __DIR__ . '/bot-specific/BotConfig.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/TelegramBot.php';
require_once __DIR__ . '/bot-specific/MenuManager.php';

$db = new Database('BotConfig');
$bot = new TelegramBot('BotConfig');

$update = json_decode(file_get_contents('php://input'), true);
if (isset($update['message']) || isset($update['callback_query'])) {
    $chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'];
    $menu = new MenuManager($db, $bot, $chat_id);
    if (isset($update['message'])) {
        $menu->saveUser($update);
        $menu->handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        $menu->handleCallbackQuery($chat_id, $update['callback_query']);
    }
}

$db->close();
?>