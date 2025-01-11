<?php
class MainMenu {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * ارسال منوی اصلی به کاربر
     */
    public function sendMainMenu($chat_id) {
        $keyboard = $this->getButtonsFromDatabase();
        TelegramAPI::sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'لطفاً یک دکمه را انتخاب کنید:',
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    /**
     * دریافت دکمه‌ها از دیتابیس و ساخت کیبورد
     */
    private function getButtonsFromDatabase() {
        $sql = "SELECT * FROM buttons ORDER BY row_position, col_position";
        $result = $this->conn->query($sql);
        $keyboard = [];
        $currentRow = 1;
        $rowButtons = [];

        while ($row = $result->fetch_assoc()) {
            if ($row['row_position'] != $currentRow) {
                $keyboard[] = $rowButtons;
                $rowButtons = [];
                $currentRow = $row['row_position'];
            }
            $rowButtons[] = $row['button_name'];
        }

        if (!empty($rowButtons)) {
            $keyboard[] = $rowButtons;
        }

        // اضافه کردن دکمه "تنظیمات بات ⚙️"
        $keyboard[] = ['تنظیمات بات ⚙️'];
        return $keyboard;
    }

    /**
     * ارسال محتوای دکمه انتخاب شده به کاربر
     */
    public function sendButtonContent($chat_id, $text) {
        // بررسی آیا دکمه انتخاب شده یکی از دکمه‌های ثابت است
        if ($text == 'تنظیمات بات ⚙️' || $text == 'مدیریت دکمه ها') {
            return; // این دکمه‌ها در `bot.php` مدیریت می‌شوند
        }

        // جستجوی دکمه در دیتابیس
        $sql = "SELECT * FROM buttons WHERE button_name = ? COLLATE utf8mb4_unicode_ci";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            Logger::log("خطا در آماده‌سازی کوئری: " . $this->conn->error);
            return;
        }

        $stmt->bind_param("s", $text);
        if (!$stmt->execute()) {
            Logger::log("خطا در اجرای کوئری: " . $stmt->error);
            return;
        }

        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $buttonContent = $row['button_content'];

            // ارسال محتوای دکمه به کاربر
            TelegramAPI::sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $buttonContent,
                'reply_markup' => json_encode([
                    'keyboard' => [['بازگشت']],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ])
            ]);
        } else {
            // ارسال پیام خطا اگر دکمه معتبر نباشد
            TelegramAPI::sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => 'دکمه انتخاب شده معتبر نیست.'
            ]);
        }
    }
}
?>