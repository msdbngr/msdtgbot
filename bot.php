<?php
require __DIR__ . '/config.php';
require __DIR__ . '/core/Logger.php';
require __DIR__ . '/core/TelegramAPI.php';
require __DIR__ . '/core/Database.php';
require __DIR__ . '/core/Session.php';
require __DIR__ . '/modules/MainMenu.php';
require __DIR__ . '/modules/AddButton.php';
require __DIR__ . '/modules/Settings.php';

$db = new Database();
$conn = $db->getConnection();

$update = json_decode(file_get_contents("php://input"), true);

if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'];
    Logger::log("پیام دریافت شده از chat_id: $chat_id - متن: $text");

    // شروع سشن برای کاربر
    Session::start($chat_id);

    // بررسی حالت کاربر
    $session = Session::get('add_button');
    Logger::log("وضعیت سشن برای chat_id: $chat_id - " . json_encode($session));

    if ($text == '/start' || $text == 'بازگشت') {
        Logger::log("کاربر chat_id: $chat_id دستور /start یا بازگشت را زد.");
        Session::destroy(); // پایان سشن
        (new MainMenu($conn))->sendMainMenu($chat_id);
    } elseif ($text == 'تنظیمات بات ⚙️') {
        Logger::log("کاربر chat_id: $chat_id دکمه 'تنظیمات بات ⚙️' را زد.");
        (new Settings($conn))->sendSettingsMenu($chat_id);
    } elseif ($text == 'مدیریت دکمه ها') {
        Logger::log("کاربر chat_id: $chat_id دکمه 'مدیریت دکمه ها' را زد.");
        (new Settings($conn))->sendManageButtonsMenu($chat_id);
    } elseif ($text == 'اضافه کردن دکمه جدید') {
        Logger::log("کاربر chat_id: $chat_id دکمه 'اضافه کردن دکمه جدید' را زد.");
        (new AddButton($conn))->startAddButtonSession($chat_id);
    } elseif ($session && $session['step'] == 'name') {
        Logger::log("کاربر chat_id: $chat_id در حال افزودن دکمه جدید است (مرحله name).");
        (new AddButton($conn))->handleAddButtonSession($chat_id, $text);
    } elseif ($session && $session['step'] == 'content') {
        Logger::log("کاربر chat_id: $chat_id در حال افزودن دکمه جدید است (مرحله content).");
        (new AddButton($conn))->handleAddButtonSession($chat_id, $text);
    } elseif ($session && $session['step'] == 'row') {
        Logger::log("کاربر chat_id: $chat_id در حال افزودن دکمه جدید است (مرحله row).");
        (new AddButton($conn))->handleAddButtonSession($chat_id, $text);
    } else {
        Logger::log("کاربر chat_id: $chat_id دکمه‌ای از منوی اصلی را انتخاب کرد.");
        $mainMenu = new MainMenu($conn);
        $mainMenu->sendButtonContent($chat_id, $text);
    }
}

$db->closeConnection();
?>