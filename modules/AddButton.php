<?php
class AddButton {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function startAddButtonSession($chat_id) {
        Session::start($chat_id);
        Session::set('add_button', [
            'chat_id' => $chat_id,
            'step' => 'name'
        ]);
        Logger::log("شروع سشن افزودن دکمه جدید برای chat_id: $chat_id");
        TelegramAPI::sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'نام دکمه را وارد کنید:',
            'reply_markup' => json_encode([
                'keyboard' => [['بازگشت']],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    public function handleAddButtonSession($chat_id, $text) {
        Session::start($chat_id);
        $session = Session::get('add_button');

        if (!$session) {
            Logger::log("سشن افزودن دکمه جدید برای chat_id: $chat_id وجود ندارد.");
            TelegramAPI::sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => 'خطا: سشن افزودن دکمه جدید وجود ندارد. لطفاً دوباره تلاش کنید.'
            ]);
            return;
        }

        if ($text == 'بازگشت') {
            Logger::log("کاربر chat_id: $chat_id دکمه بازگشت را زد. سشن بسته شد.");
            Session::destroy();
            (new MainMenu($this->conn))->sendMainMenu($chat_id);
            return;
        }

        switch ($session['step']) {
            case 'name':
                $this->handleNameStep($chat_id, $text);
                break;
            case 'content':
                $this->handleContentStep($chat_id, $text);
                break;
            case 'row':
                $this->handleRowStep($chat_id, $text);
                break;
            case 'col':
                $this->handleColStep($chat_id, $text);
                break;
            default:
                Logger::log("مرحله نامعتبر برای chat_id: $chat_id");
                TelegramAPI::sendRequest('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => 'خطا: مرحله نامعتبر. لطفاً دوباره تلاش کنید.'
                ]);
                break;
        }
    }

    private function handleNameStep($chat_id, $text) {
        Session::set('add_button', [
            'chat_id' => $chat_id,
            'step' => 'content',
            'name' => $text
        ]);
        Logger::log("نام دکمه برای chat_id: $chat_id دریافت شد: $text");
        TelegramAPI::sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'نام دکمه دریافت شد. محتویات برای نمایش را وارد کنید:',
            'reply_markup' => json_encode([
                'keyboard' => [['بازگشت']],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    private function handleContentStep($chat_id, $text) {
        Session::set('add_button', [
            'chat_id' => $chat_id,
            'step' => 'row',
            'name' => Session::get('add_button')['name'],
            'content' => $text
        ]);
        Logger::log("محتویات دکمه برای chat_id: $chat_id دریافت شد: $text");
        TelegramAPI::sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'محتویات دریافت شد. سطر دکمه را وارد کنید:',
            'reply_markup' => json_encode([
                'keyboard' => [['بازگشت']],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    private function handleRowStep($chat_id, $text) {
        if (!is_numeric($text)) {
            Logger::log("ورودی نامعتبر برای سطر دکمه از chat_id: $chat_id: $text");
            TelegramAPI::sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => 'لطفاً یک عدد معتبر برای سطر وارد کنید.'
            ]);
            return;
        }

        Session::set('add_button', [
            'chat_id' => $chat_id,
            'step' => 'col',
            'name' => Session::get('add_button')['name'],
            'content' => Session::get('add_button')['content'],
            'row' => (int)$text
        ]);
        Logger::log("سطر دکمه برای chat_id: $chat_id دریافت شد: $text");
        TelegramAPI::sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => 'سطر دریافت شد. یک عدد از 1 تا 3 برای ستون دکمه انتخاب کنید:',
            'reply_markup' => json_encode([
                'keyboard' => [['بازگشت']],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    }

    private function handleColStep($chat_id, $text) {
        if (!is_numeric($text) || $text < 1 || $text > 3) {
            Logger::log("ورودی نامعتبر برای ستون دکمه از chat_id: $chat_id: $text");
            TelegramAPI::sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => 'لطفاً یک عدد معتبر بین 1 تا 3 برای ستون وارد کنید.'
            ]);
            return;
        }

        $row = Session::get('add_button')['row'];
        $col = (int)$text;

        // بررسی وجود دکمه در مکان مشخص شده
        $sql = "SELECT * FROM buttons WHERE row_position = ? AND col_position = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $row, $col);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            Logger::log("دکمه در مکان سطر=$row و ستون=$col از قبل وجود دارد.");
            TelegramAPI::sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "⚠️ دکمه در جایگاه سطر $row و ستون $col از قبل ایجاد شده است. لطفاً مکان جدید انتخاب نمایید."
            ]);
            return;
        }

        // ذخیره دکمه در دیتابیس
        $name = Session::get('add_button')['name'];
        $content = Session::get('add_button')['content'];

        $sql = "INSERT INTO buttons (button_name, button_content, row_position, col_position) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssii", $name, $content, $row, $col);

        if ($stmt->execute()) {
            Logger::log("دکمه با موفقیت برای chat_id: $chat_id ثبت شد: نام=$name, محتوا=$content, سطر=$row, ستون=$col");
            TelegramAPI::sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => 'دکمه با موفقیت ثبت شد.'
            ]);
        } else {
            Logger::log("خطا در ثبت دکمه برای chat_id: $chat_id: " . $stmt->error);
            TelegramAPI::sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => 'خطا در ثبت دکمه: ' . $stmt->error
            ]);
        }

        Session::destroy();
        (new MainMenu($this->conn))->sendMainMenu($chat_id);
    }
}
?>