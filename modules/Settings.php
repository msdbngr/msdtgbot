<?php
class Settings {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function sendSettingsMenu($chat_id) {
        Logger::log("ارسال منوی تنظیمات به chat_id: $chat_id");
        TelegramAPI::sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'تنظیمات بات:',
            'reply_markup' => json_encode([
                'keyboard' => [['مدیریت دکمه ها'], ['بازگشت']],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function sendManageButtonsMenu($chat_id) {
        Logger::log("ارسال منوی مدیریت دکمه‌ها به chat_id: $chat_id");
        TelegramAPI::sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'مدیریت دکمه ها:',
            'reply_markup' => json_encode([
                'keyboard' => [['اضافه کردن دکمه جدید'], ['ویرایش دکمه'], ['بازگشت']],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }
}
?>