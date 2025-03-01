<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/UserManager.php';
require_once __DIR__ . '/BotConfig.php';
require_once __DIR__ . '/ButtonManager.php';
require_once __DIR__ . '/BroadcastManager.php';
require_once __DIR__ . '/SettingsManager.php';
require_once __DIR__ . '/BackHandler.php';

class MenuManager {
    private $db;
    private $bot;
    private $userManager;
    private $buttonManager;
    private $broadcastManager;
    private $settingsManager;
    private $backHandler;

    public function __construct($db, $bot, $user_id) {
        $this->db = $db;
        $this->bot = $bot;
        $this->userManager = new UserManager($db, $user_id, 'BotConfig');
        $this->buttonManager = new ButtonManager($db, $bot, $this->userManager, 'BotConfig');
        $this->broadcastManager = new BroadcastManager($db, $bot, $this->userManager, 'BotConfig');
        $this->settingsManager = new SettingsManager($db, $bot, $this->userManager, $this->buttonManager, 'BotConfig');
        $this->backHandler = new BackHandler($db, $bot, $this->userManager);
        $this->log("Initialized for user: $user_id");
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log = "[$timestamp] User: {$this->userManager->getUserId()} - $message\n";
        file_put_contents('log.txt', $log, FILE_APPEND);
    }

    public function saveUser($update) {
        $this->userManager->saveUser($update);
        $this->log("User saved/updated: " . $update['message']['chat']['id']);
    }

    public function showMainMenu($chat_id) {
        $this->userManager->setState('main');
        $buttons = $this->buttonManager->getButtons(null);
        $keyboard = $this->buttonManager->buildKeyboard($buttons);
        if ($this->userManager->isAdmin()) {
            $keyboard[] = ['⚙️ تنظیمات بات'];
        }
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];

        $intro_content = $this->buttonManager->getIntroContent();
        if ($intro_content) {
            $parts = explode('|', $intro_content, 3);
            if (count($parts) >= 2) {
                $type = $parts[0];
                $file_id = $parts[1];
                $caption = isset($parts[2]) ? $parts[2] : '';
                $this->bot->send($chat_id, $type, $file_id, $caption, $reply_markup);
            } else {
                $this->bot->send($chat_id, 'text', null, $intro_content, $reply_markup);
            }
        } else {
            $this->bot->send($chat_id, 'text', null, "به ربات خوش آمدید!", $reply_markup);
        }
        $this->log("Showed main menu");
    }

    public function handleMessage($message) {
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? 'NO_TEXT';
        $state = $this->userManager->getState();
        $this->log("Handling message: $text, State: $state");

        if ($text === '/start') {
            $this->showMainMenu($chat_id);
        } elseif ($text === '⚙️ تنظیمات بات' && $this->userManager->isAdmin()) {
            $this->settingsManager->showSettingsMenu($chat_id);
        } elseif ($text === '⬅️ بازگشت') {
            $this->backHandler->handle($chat_id, $state, [$this, 'showMenu']);
        } elseif ($this->settingsManager->handleSettingsMessage($chat_id, $text, $state)) {
            // تنظیمات مدیریت شده
        } elseif ($this->broadcastManager->handleBroadcastMessage($chat_id, $message, $state)) {
            // پخش پیام مدیریت شده
        } elseif ($this->buttonManager->handleButtonMessage($chat_id, $text, $state)) {
            // دکمه‌ها مدیریت شده
        } else {
            $this->buttonManager->showButtonContent($chat_id, $text);
        }
    }

    public function handleCallbackQuery($chat_id, $callback_query) {
        $this->buttonManager->handleCallbackQuery($chat_id, $callback_query);
    }

    public function showMenu($chat_id, $menu_type) {
        if ($menu_type === 'main') {
            $this->showMainMenu($chat_id);
        } elseif ($menu_type === 'settings') {
            $this->settingsManager->showSettingsMenu($chat_id);
        } elseif ($menu_type === 'user_management') {
            $this->settingsManager->showUserManagementMenu($chat_id);
        } elseif ($menu_type === 'button_management') {
            $this->buttonManager->showButtonManagementMenu($chat_id);
        } elseif (is_numeric($menu_type)) {
            $this->buttonManager->showButtonContent($chat_id, $this->buttonManager->getButtonName($menu_type));
        }
    }
}