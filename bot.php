<?php
require_once 'config.php';

class Database {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection() {
        return $this->conn;
    }

    public function close() {
        $this->conn->close();
    }
}

class TelegramBot {
    private $token;
    private $apiUrl;

    public function __construct($token) {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}/";
    }

    public function send($chat_id, $type, $media = null, $caption = null, $keyboard = null) {
        $data = ['chat_id' => $chat_id];

        if ($type === 'text') {
            $data['text'] = $caption;
            $data['parse_mode'] = 'HTML';
            $url = $this->apiUrl . 'sendMessage';
        } elseif ($type === 'photo') {
            $data['photo'] = $media;
            if ($caption) {
                $data['caption'] = $caption;
                $data['parse_mode'] = 'HTML';
            }
            $url = $this->apiUrl . 'sendPhoto';
        } elseif ($type === 'video') {
            $data['video'] = $media;
            if ($caption) {
                $data['caption'] = $caption;
                $data['parse_mode'] = 'HTML';
            }
            $url = $this->apiUrl . 'sendVideo';
        } elseif ($type === 'audio') {
            $data['audio'] = $media;
            if ($caption) {
                $data['caption'] = $caption;
                $data['parse_mode'] = 'HTML';
            }
            $url = $this->apiUrl . 'sendAudio';
        } elseif ($type === 'voice') {
            $data['voice'] = $media;
            if ($caption) {
                $data['caption'] = $caption;
                $data['parse_mode'] = 'HTML';
            }
            $url = $this->apiUrl . 'sendVoice';
        } elseif ($type === 'document') {
            $data['document'] = $media;
            if ($caption) {
                $data['caption'] = $caption;
                $data['parse_mode'] = 'HTML';
            }
            $url = $this->apiUrl . 'sendDocument';
        } else {
            return false;
        }

        if ($keyboard) {
            $data['reply_markup'] = json_encode($keyboard);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function deleteMessage($chat_id, $message_id) {
        $data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ];
        $url = $this->apiUrl . 'deleteMessage';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function detectMessageType($message) {
        $result = ['type' => null, 'content' => null, 'caption' => null];

        if (isset($message['text'])) {
            $result['type'] = 'text';
            $result['content'] = $message['text'];
            $result['caption'] = $message['text'];
        } elseif (isset($message['photo'])) {
            $result['type'] = 'photo';
            $result['content'] = $message['photo'][count($message['photo']) - 1]['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        } elseif (isset($message['video'])) {
            $result['type'] = 'video';
            $result['content'] = $message['video']['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        } elseif (isset($message['audio'])) {
            $result['type'] = 'audio';
            $result['content'] = $message['audio']['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        } elseif (isset($message['voice'])) {
            $result['type'] = 'voice';
            $result['content'] = $message['voice']['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        } elseif (isset($message['document'])) {
            $result['type'] = 'document';
            $result['content'] = $message['document']['file_id'];
            $result['caption'] = $message['caption'] ?? '';
        }

        return $result;
    }
}

class MenuManager {
    private $db;
    private $bot;
    private $user_id;
    private $is_admin;

    public function __construct($db, $bot, $user_id) {
        $this->db = $db;
        $this->bot = $bot;
        $this->user_id = $user_id;
        $this->is_admin = $this->checkAdmin();
        $this->log("Initialized for user: $user_id");
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log = "[$timestamp] User: $this->user_id - $message\n";
        file_put_contents('log.txt', $log, FILE_APPEND);
    }

    private function checkAdmin() {
        $stmt = $this->db->getConnection()->prepare("SELECT is_admin FROM " . TABLE_USERS . " WHERE user_id = ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['is_admin'] == 1 : false;
    }

    public function saveUser($update) {
        $chat_id = $update['message']['chat']['id'];
        $username = $update['message']['chat']['username'] ?? null;
        $first_name = $update['message']['chat']['first_name'] ?? null;
        $last_name = $update['message']['chat']['last_name'] ?? null;

        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO " . TABLE_USERS . " (user_id, username, first_name, last_name, last_menu) 
             VALUES (?, ?, ?, ?, 'main') 
             ON DUPLICATE KEY UPDATE username = ?, first_name = ?, last_name = ?"
        );
        $stmt->bind_param("issssss", $chat_id, $username, $first_name, $last_name, $username, $first_name, $last_name);
        $stmt->execute();
        $this->log("User saved/updated: $chat_id");
    }

    private function setUserState($state) {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE " . TABLE_USERS . " SET last_menu = ? WHERE user_id = ?"
        );
        $stmt->bind_param("si", $state, $this->user_id);
        $stmt->execute();
        $this->log("Set state to: $state");
    }

    private function getUserState() {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT last_menu FROM " . TABLE_USERS . " WHERE user_id = ?"
        );
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $state = $result ? $result['last_menu'] : 'main';
        $this->log("Got state: $state");
        return $state;
    }

    private function getBroadcastMessages() {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT broadcast_data FROM " . TABLE_USERS . " WHERE user_id = ?"
        );
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result && $result['broadcast_data'] ? json_decode($result['broadcast_data'], true) : [];
    }

    private function saveBroadcastMessages($messages) {
        $json_data = json_encode($messages);
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE " . TABLE_USERS . " SET broadcast_data = ? WHERE user_id = ?"
        );
        $stmt->bind_param("si", $json_data, $this->user_id);
        $stmt->execute();
    }

    private function clearBroadcastMessages() {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE " . TABLE_USERS . " SET broadcast_data = NULL WHERE user_id = ?"
        );
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
    }

    private function getMatrixMessageId($chat_id) {
        $file = "matrix_$chat_id.txt";
        return file_exists($file) ? (int)file_get_contents($file) : null;
    }

    private function saveMatrixMessageId($chat_id, $message_id) {
        file_put_contents("matrix_$chat_id.txt", $message_id);
    }

    private function clearMatrixMessageId($chat_id) {
        $file = "matrix_$chat_id.txt";
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function showMainMenu($chat_id) {
        $matrix_id = $this->getMatrixMessageId($chat_id);
        if ($matrix_id) {
            $this->bot->deleteMessage($chat_id, $matrix_id);
            $this->clearMatrixMessageId($chat_id);
        }
        $this->setUserState('main');
        $buttons = $this->getButtons(null);
        $keyboard = $this->buildKeyboard($buttons);
        if ($this->is_admin) {
            $keyboard[] = ['⚙️ تنظیمات بات'];
        }
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];

        $intro_content = $this->getIntroContent();
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

    private function getButtons($prev_button) {
        $query = "SELECT id, button_name, row_position, col_position 
                  FROM " . TABLE_BUTTONS . " 
                  WHERE prev_button IS NULL";
        if ($prev_button !== null) {
            $query = "SELECT id, button_name, row_position, col_position 
                      FROM " . TABLE_BUTTONS . " 
                      WHERE prev_button = ?";
        }
        $stmt = $this->db->getConnection()->prepare($query);
        if ($prev_button !== null) {
            $stmt->bind_param("i", $prev_button);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function buildKeyboard($buttons) {
        $keyboard = array_fill(0, 9, []);
        foreach ($buttons as $button) {
            $row = max(0, min(8, $button['row_position'] - 1));
            $col = max(0, min(2, $button['col_position'] - 1));
            $keyboard[$row][$col] = $button['button_name'];
        }
        return array_filter($keyboard);
    }

    private function getIntroContent() {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT content FROM " . TABLE_BUTTONS . " WHERE is_intro = 1 LIMIT 1"
        );
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['content'] : null;
    }

    public function handleMessage($message) {
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? 'NO_TEXT';

        $state = $this->getUserState();
        $this->log("Handling message: $text, State: $state");

        if ($text === '/start') {
            $this->showMainMenu($chat_id);
        } elseif ($text === '⚙️ تنظیمات بات' && $this->is_admin) {
            $this->showSettingsMenu($chat_id);
        } elseif ($text === '⬅️ بازگشت') {
            $this->handleBack($chat_id);
        } elseif ($text === '📊 آمار بات' && $this->is_admin) {
            $this->showBotStats($chat_id);
        } elseif ($text === '📢 ارسال پیام به همه' && $this->is_admin) {
            $this->startBroadcast($chat_id);
        } elseif ($text === '✅ تأیید ارسال' && $this->is_admin && $state === 'broadcast') {
            $this->confirmBroadcast($chat_id);
        } elseif ($text === '📢 ارسال پیام دیگر' && $this->is_admin && $state === 'broadcast_done') {
            $this->startBroadcast($chat_id);
        } elseif ($text === '✏️ ویرایش اینترو' && $this->is_admin) {
            $this->startEditIntro($chat_id);
        } elseif ($state === 'edit_intro' && $this->is_admin) {
            $this->saveIntroContent($chat_id, $message);
        } elseif ($state === 'broadcast' && $this->is_admin) {
            $this->saveBroadcastMessage($chat_id, $message);
        } elseif ($text === '👥 مدیریت کاربران' && $this->is_admin) {
            $this->showUserManagementMenu($chat_id);
        } elseif ($text === '👤 مدیریت ادمین‌ها' && $this->is_admin) {
            $this->showAdminManagementMenu($chat_id);
        } elseif ($text === '➕ اضافه کردن ادمین' && $this->is_admin) {
            $this->startAddAdmin($chat_id);
        } elseif ($state === 'add_admin' && $this->is_admin) {
            $this->addAdmin($chat_id, $text);
        } elseif ($text === '🗑️ حذف ادمین' && $this->is_admin) {
            $this->showRemoveAdminMenu($chat_id);
        } elseif ($text === '🗑️ حذف کاربر' && $this->is_admin) {
            $this->startRemoveUser($chat_id);
        } elseif ($state === 'remove_user' && $this->is_admin) {
            $this->removeUser($chat_id, $text);
        } elseif ($text === '🛠️ مدیریت دکمه‌ها' && $this->is_admin) {
            $this->showButtonManagementMenu($chat_id);
        } elseif ($text === '➕ اضافه کردن دکمه جدید' && $this->is_admin) {
            $this->startAddButton($chat_id);
        } elseif ($state === 'add_button_name' && $this->is_admin) {
            $this->saveButtonName($chat_id, $text);
        } elseif ($state === 'add_button_content' && $this->is_admin) {
            if ($text === '✅ تأیید') {
                $this->saveButtonToDatabase($chat_id);
            } else {
                $this->saveButtonContent($chat_id, $message);
            }
        } elseif ($text === '🗑️ حذف دکمه' && $this->is_admin) {
            $this->showRemoveButtonMatrix($chat_id);
        } elseif ($state === 'remove_button_confirm' && $this->is_admin && $text === '✅ تأیید') {
            $this->confirmRemoveButton($chat_id);
        } elseif ($text === '✏️ ویرایش دکمه‌ها' && $this->is_admin) {
            $this->showEditButtonMatrix($chat_id);
        } elseif ($state === 'edit_button_name' && $this->is_admin) {
            $this->saveEditButtonName($chat_id, $text);
        } elseif ($state === 'edit_button_position' && $this->is_admin && $text === '✅ تأیید') {
            $this->requestEditButtonContent($chat_id);
        } elseif ($state === 'edit_button_content' && $this->is_admin) {
            if ($text === '✅ تأیید') {
                $this->saveEditedButtonToDatabase($chat_id);
            } else {
                $this->saveEditButtonContent($chat_id, $message);
            }
        } else {
            $this->showButtonContent($chat_id, $text);
        }
    }

    public function handleCallbackQuery($chat_id, $callback_query) {
        $data = $callback_query['data'];
        $message_id = $callback_query['message']['message_id'];

        if (strpos($data, 'remove_admin_') === 0) {
            $user_id = substr($data, strlen('remove_admin_'));
            $stmt = $this->db->getConnection()->prepare("SELECT username, is_admin FROM " . TABLE_USERS . " WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if ($result && $result['is_admin'] == 1) {
                $stmt = $this->db->getConnection()->prepare("UPDATE " . TABLE_USERS . " SET is_admin = 0 WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $this->log("Removed admin: @{$result['username']}");
                $this->bot->deleteMessage($chat_id, $message_id);
            }

            $this->showRemoveAdminMenu($chat_id);
        } elseif (strpos($data, 'button_position_') === 0) {
            $position = substr($data, strlen('button_position_'));
            list($row, $col) = explode('_', $position);
            $this->saveButtonPosition($chat_id, $row, $col);
            $this->bot->deleteMessage($chat_id, $message_id);
            $this->clearMatrixMessageId($chat_id); // اضافه شده برای پاک کردن فایل ماتریس
        } elseif ($data === 'occupied' && in_array($this->getUserState(), ['add_button_position', 'edit_button_position'])) {
            $this->bot->send($chat_id, 'text', null, "⚠️ این جایگاه قبلاً انتخاب شده! لطفاً جایگاه خالی (سبز) رو انتخاب کنید.");
            $this->bot->deleteMessage($chat_id, $message_id);
            if ($this->getUserState() === 'add_button_position') {
                $this->showButtonPositionMatrix($chat_id);
            } else {
                $this->showEditButtonPositionMatrix($chat_id);
            }
        } elseif (strpos($data, 'remove_button_') === 0) {
            $button_id = substr($data, strlen('remove_button_'));
            $this->requestRemoveButtonConfirmation($chat_id, $button_id, $message_id);
            $this->clearMatrixMessageId($chat_id); // اضافه شده برای پاک کردن فایل ماتریس
        } elseif ($data === 'undefined' && $this->getUserState() === 'remove_button') {
            $this->bot->send($chat_id, 'text', null, "⚠️ لطفاً دکمه موجود رو برای حذف انتخاب کنید!");
            $this->bot->deleteMessage($chat_id, $message_id);
            $this->showRemoveButtonMatrix($chat_id);
        } elseif (strpos($data, 'edit_button_') === 0) {
            $button_id = substr($data, strlen('edit_button_'));
            $this->startEditButtonName($chat_id, $button_id, $message_id);
            $this->clearMatrixMessageId($chat_id); // اضافه شده برای پاک کردن فایل ماتریس
        } elseif ($data === 'undefined' && $this->getUserState() === 'edit_button') {
            $this->bot->send($chat_id, 'text', null, "⚠️ لطفاً دکمه تعریف‌شده رو برای ویرایش انتخاب کنید!");
            $this->bot->deleteMessage($chat_id, $message_id);
            $this->showEditButtonMatrix($chat_id);
        } elseif (strpos($data, 'edit_position_') === 0) {
            $position = substr($data, strlen('edit_position_'));
            list($row, $col) = explode('_', $position);
            $this->saveEditButtonPosition($chat_id, $row, $col);
            $this->bot->deleteMessage($chat_id, $message_id);
            $this->clearMatrixMessageId($chat_id); // اضافه شده برای پاک کردن فایل ماتریس
        }
    }

    private function showButtonContent($chat_id, $button_name) {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT id, content, prev_button FROM " . TABLE_BUTTONS . " WHERE button_name = ?"
        );
        $stmt->bind_param("s", $button_name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) return;

        $content = json_decode($result['content'], true);
        $prev_button = $result['prev_button'];
        $button_id = $result['id'];

        $this->setUserState($prev_button === null ? 'main' : $prev_button);

        $sub_buttons = $this->getButtons($button_id);
        $keyboard = !empty($sub_buttons) ? $this->buildKeyboard($sub_buttons) : [];
        $keyboard[] = ['⬅️ بازگشت'];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];

        foreach ($content as $item) {
            list($type, $content_value, $caption) = $item;
            $this->bot->send($chat_id, $type, $type === 'text' ? null : $content_value, $type === 'text' ? $content_value : $caption, $reply_markup);
        }

        if (!empty($sub_buttons)) {
            $this->setUserState($button_id);
            $this->bot->send($chat_id, 'text', null, "زیرمنو:", $reply_markup);
        }
    }

    private function showSettingsMenu($chat_id) {
        $this->setUserState('settings');
        $keyboard = [
            ['📊 آمار بات'],
            ['📢 ارسال پیام به همه'],
            ['✏️ ویرایش اینترو'],
            ['🛠️ مدیریت دکمه‌ها'],
            ['👥 مدیریت کاربران'],
            ['⬅️ بازگشت']
        ];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "منوی تنظیمات:", $reply_markup);
        $this->log("Showed settings menu");
    }

    private function showBotStats($chat_id) {
        $this->setUserState('stats');
        $stmt = $this->db->getConnection()->prepare("SELECT COUNT(*) as user_count FROM " . TABLE_USERS);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $user_count = $result['user_count'];

        $message = "📈 <b>آمار ربات</b> 📈\n\n";
        $message .= "👥 تعداد کاربران: <b>$user_count</b> نفر\n";
        $message .= "🌟 ربات شما در حال رشد است!";

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Showed bot stats");
    }

    private function startBroadcast($chat_id) {
        $this->setUserState('broadcast');
        $this->saveBroadcastMessages([]);
        $message = "📢 <b>ارسال پیام به همه</b> 📢\n";
        $message .= "لطفاً پیام‌هایی که می‌خواهید به همه کاربران ارسال بشه رو بفرستید.\n";
        $message .= "هر تعداد پیام (متن، عکس، ویدئو و ...) می‌تونید بفرستید.\n";
        $message .= "وقتی آماده شد، '✅ تأیید ارسال' رو بزنید.";

        $keyboard = [
            ['✅ تأیید ارسال'],
            ['⬅️ بازگشت']
        ];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started broadcast, array initialized in DB");
    }

    private function saveBroadcastMessage($chat_id, $message) {
        $detected = $this->bot->detectMessageType($message);
        if ($detected['type']) {
            $messages = $this->getBroadcastMessages();
            $messages[] = [$detected['type'], $detected['content'], $detected['caption']];
            $this->saveBroadcastMessages($messages);
            $this->log("Saved broadcast message: {$detected['type']} - {$detected['content']}, Caption: {$detected['caption']}");
        } else {
            $this->bot->send($chat_id, 'text', null, "⚠️ نوع پیام پشتیبانی نمی‌شه!");
            $this->log("Unsupported message type: " . json_encode($message));
        }
    }

    private function confirmBroadcast($chat_id) {
        $messages = $this->getBroadcastMessages();
        $this->log("Confirming broadcast, messages: " . json_encode($messages));

        if (empty($messages)) {
            $this->bot->send($chat_id, 'text', null, "⚠️ هیچ پیامی برای ارسال وجود نداره!");
            $this->showSettingsMenu($chat_id);
            $this->log("No messages to broadcast");
            return;
        }

        $this->broadcastMessage($messages);

        $this->clearBroadcastMessages();
        $this->log("Broadcast sent and DB array cleared");

        $this->setUserState('broadcast_done');
        $message = "✅ <b>پیام‌ها با موفقیت ارسال شدند!</b> ✅\n";
        $message .= "پیام‌های شما به همه کاربران ربات فرستاده شد.\n";
        $message .= "چی کار می‌خواهید بکنید؟";

        $keyboard = [
            ['📢 ارسال پیام دیگر'],
            ['⬅️ بازگشت']
        ];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Showed broadcast done message");
    }

    private function broadcastMessage($messages) {
        $batchSize = 100;
        $offset = 0;

        while (true) {
            $stmt = $this->db->getConnection()->prepare("SELECT user_id FROM " . TABLE_USERS . " LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $batchSize, $offset);
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if (empty($users)) {
                break;
            }

            foreach ($users as $user) {
                $user_chat_id = $user['user_id'];
                foreach ($messages as $msg) {
                    list($type, $content, $caption) = $msg;
                    $this->bot->send($user_chat_id, $type, $type === 'text' ? null : $content, $type === 'text' ? $content : $caption);
                }
            }

            $offset += $batchSize;
        }
    }

    private function startEditIntro($chat_id) {
        $this->setUserState('edit_intro');
        $message = "✏️ <b>ویرایش اینترو</b> ✏️\n";
        $message .= "لطفاً محتوای جدید اینترو رو بفرستید (متن، عکس، ویدئو و ...).\n";
        $message .= "بعد از ارسال، محتوا به‌عنوان اینترو ذخیره می‌شه.";

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started editing intro");
    }

    private function saveIntroContent($chat_id, $message) {
        $detected = $this->bot->detectMessageType($message);
        if ($detected['type']) {
            if ($detected['type'] === 'text') {
                $content = $detected['content'];
            } else {
                $content = "{$detected['type']}|{$detected['content']}|{$detected['caption']}";
            }
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE " . TABLE_BUTTONS . " SET content = ? WHERE is_intro = 1"
            );
            $stmt->bind_param("s", $content);
            $stmt->execute();
            $this->log("Intro content updated: $content");

            $this->setUserState('settings');
            $message = "✅ <b>اینترو با موفقیت ویرایش شد!</b> ✅\n";
            $message .= "محتوای جدید ذخیره شد.";
            $this->bot->send($chat_id, 'text', null, $message);
            $this->showSettingsMenu($chat_id);
        } else {
            $this->bot->send($chat_id, 'text', null, "⚠️ نوع پیام پشتیبانی نمی‌شه!");
            $this->log("Unsupported intro type: " . json_encode($message));
        }
    }

    private function showUserManagementMenu($chat_id) {
        $this->setUserState('user_management');
        $keyboard = [
            ['👤 مدیریت ادمین‌ها'],
            ['🗑️ حذف کاربر'],
            ['⬅️ بازگشت']
        ];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "👥 <b>مدیریت کاربران</b> 👥\nگزینه مورد نظر رو انتخاب کنید:", $reply_markup);
        $this->log("Showed user management menu");
    }

    private function showAdminManagementMenu($chat_id) {
        $this->setUserState('admin_management');
        $keyboard = [
            ['➕ اضافه کردن ادمین'],
            ['🗑️ حذف ادمین'],
            ['⬅️ بازگشت']
        ];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "👤 <b>مدیریت ادمین‌ها</b> 👤\nگزینه مورد نظر رو انتخاب کنید:", $reply_markup);
        $this->log("Showed admin management menu");
    }

    private function startAddAdmin($chat_id) {
        $this->setUserState('add_admin');
        $message = "➕ <b>اضافه کردن ادمین</b> ➕\n";
        $message .= "لطفاً یوزرنیم کاربری که می‌خواهید ادمین بشه رو بفرستید (مثلاً @username):";

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started adding admin");
    }

    private function addAdmin($chat_id, $username) {
        if (substr($username, 0, 1) !== '@') {
            $this->bot->send($chat_id, 'text', null, "⚠️ لطفاً یوزرنیم رو با @ شروع کنید (مثلاً @username)!");
            return;
        }

        $username = substr($username, 1);

        $stmt = $this->db->getConnection()->prepare("SELECT user_id, is_admin FROM " . TABLE_USERS . " WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result) {
            $this->bot->send($chat_id, 'text', null, "⚠️ کاربری با یوزرنیم @$username پیدا نشد!");
            return;
        }

        if ($result['is_admin'] == 1) {
            $this->bot->send($chat_id, 'text', null, "⚠️ کاربر @$username از قبل ادمینه!");
            return;
        }

        $stmt = $this->db->getConnection()->prepare("UPDATE " . TABLE_USERS . " SET is_admin = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $result['user_id']);
        $stmt->execute();

        $this->setUserState('admin_management');
        $message = "✅ <b>ادمین با موفقیت اضافه شد!</b> ✅\n";
        $message .= "کاربر @$username حالا ادمینه.";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showAdminManagementMenu($chat_id);
        $this->log("Added admin: @$username");
    }

    private function showRemoveAdminMenu($chat_id) {
        $this->setUserState('remove_admin');
        $stmt = $this->db->getConnection()->prepare("SELECT user_id, username FROM " . TABLE_USERS . " WHERE is_admin = 1 AND user_id != ?");
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($admins)) {
            $message = "⚠️ هیچ ادمین دیگه‌ای جز شما وجود نداره!";
            $keyboard = [['⬅️ بازگشت']];
            $reply_markup = [
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ];
            $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
            $this->log("No other admins found");
            return;
        }

        foreach ($admins as $admin) {
            if ($admin['username']) {
                $message = "👤 ادمین: @" . $admin['username'];
                $inline_keyboard = [
                    [['text' => "حذف", 'callback_data' => "remove_admin_" . $admin['user_id']]]
                ];
                $reply_markup = [
                    'inline_keyboard' => $inline_keyboard
                ];
                $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
            }
        }

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "لطفاً ادمین مورد نظر رو انتخاب کنید:", $reply_markup);
        $this->log("Showed remove admin menu");
    }

    private function startRemoveUser($chat_id) {
        $this->setUserState('remove_user');
        $message = "🗑️ <b>حذف کاربر</b> 🗑️\n";
        $message .= "لطفاً یوزرنیم کاربری که می‌خواهید حذف بشه رو بفرستید (مثلاً @username):";

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started removing user");
    }

    private function removeUser($chat_id, $username) {
        if (substr($username, 0, 1) !== '@') {
            $this->bot->send($chat_id, 'text', null, "⚠️ لطفاً یوزرنیم رو با @ شروع کنید (مثلاً @username)!");
            return;
        }

        $username = substr($username, 1);

        $stmt = $this->db->getConnection()->prepare("SELECT user_id, is_admin FROM " . TABLE_USERS . " WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result) {
            $this->bot->send($chat_id, 'text', null, "⚠️ کاربری با یوزرنیم @$username پیدا نشد!");
            return;
        }

        if ($result['is_admin'] == 1) {
            $this->bot->send($chat_id, 'text', null, "⚠️ نمی‌تونید ادمین (@$username) رو حذف کنید! اول از ادمینی خارجش کنید.");
            return;
        }

        if ($result['user_id'] == $this->user_id) {
            $this->bot->send($chat_id, 'text', null, "⚠️ نمی‌تونید خودتون رو حذف کنید!");
            return;
        }

        $stmt = $this->db->getConnection()->prepare("DELETE FROM " . TABLE_USERS . " WHERE user_id = ?");
        $stmt->bind_param("i", $result['user_id']);
        $stmt->execute();

        $this->setUserState('user_management');
        $message = "✅ <b>کاربر با موفقیت حذف شد!</b> ✅\n";
        $message .= "کاربر @$username از دیتابیس حذف شد.";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showUserManagementMenu($chat_id);
        $this->log("Removed user: @$username");
    }

    private function showButtonManagementMenu($chat_id) {
        $this->setUserState('button_management');
        $keyboard = [
            ['➕ اضافه کردن دکمه جدید'],
            ['✏️ ویرایش دکمه‌ها'],
            ['🗑️ حذف دکمه'],
            ['⬅️ بازگشت']
        ];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "🛠️ <b>مدیریت دکمه‌ها</b> 🛠️\nگزینه مورد نظر رو انتخاب کنید:", $reply_markup);
        $this->log("Showed button management menu");
    }

    private function startAddButton($chat_id) {
        $this->setUserState('add_button_name');
        $message = "➕ <b>اضافه کردن دکمه جدید</b> ➕\n";
        $message .= "لطفاً نام دکمه جدید رو بفرستید:";

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started adding button - requesting name");
    }

    private function saveButtonName($chat_id, $button_name) {
        if (empty($button_name) || $button_name === 'NO_TEXT') {
            $this->bot->send($chat_id, 'text', null, "⚠️ لطفاً یه نام معتبر برای دکمه وارد کنید!");
            return;
        }

        $data = ['name' => $button_name, 'position' => null, 'contents' => []];
        file_put_contents('addbutton.txt', json_encode($data));

        $this->setUserState('add_button_position');
        $this->showButtonPositionMatrix($chat_id);
        $this->log("Saved button name: $button_name");
    }

    private function showButtonPositionMatrix($chat_id) {
        $stmt = $this->db->getConnection()->prepare("SELECT row_position, col_position FROM " . TABLE_BUTTONS . " WHERE prev_button IS NULL");
        $stmt->execute();
        $occupied = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $occupied_positions = [];
        foreach ($occupied as $pos) {
            $occupied_positions[$pos['row_position'] . '_' . $pos['col_position']] = true;
        }

        $message = "📍 <b>انتخاب جایگاه دکمه</b> 📍\n";
        $message .= "موقعیت دکمه رو از ماتریس زیر انتخاب کنید (سبز = خالی، قرمز = اشغال‌شده):\n\n";
        $inline_keyboard = [];
        for ($row = 1; $row <= 9; $row++) {
            $row_buttons = [];
            for ($col = 1; $col <= 3; $col++) {
                $key = $row . '_' . $col;
                $is_occupied = isset($occupied_positions[$key]);
                $row_buttons[] = [
                    'text' => $is_occupied ? '🔴' : '🟢',
                    'callback_data' => $is_occupied ? 'occupied' : "button_position_$key"
                ];
            }
            $inline_keyboard[] = $row_buttons;
        }

        $reply_markup = [
            'inline_keyboard' => $inline_keyboard
        ];
        $response = $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->saveMatrixMessageId($chat_id, $response['result']['message_id']);

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "جایگاه رو از ماتریس بالا انتخاب کنید:", $reply_markup);
        $this->log("Showed button position matrix");
    }

    private function saveButtonPosition($chat_id, $row, $col) {
        $data = json_decode(file_get_contents('addbutton.txt'), true);
        $data['position'] = ['row' => (int)$row, 'col' => (int)$col];
        file_put_contents('addbutton.txt', json_encode($data));

        $this->requestButtonContent($chat_id);
        $this->log("Saved button position: Row $row, Col $col");
    }

    private function requestButtonContent($chat_id) {
        $this->setUserState('add_button_content');
        $message = "📝 <b>محتوای دکمه</b> 📝\n";
        $message .= "لطفاً محتوای دکمه رو بفرستید (متن، عکس، ویدئو و ...).\n";
        $message .= "هر تعداد محتوا می‌تونید بفرستید، وقتی آماده شد '✅ تأیید' رو بزنید:";

        $keyboard = [
            ['✅ تأیید'],
            ['⬅️ بازگشت']
        ];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started requesting button content");
    }

    private function saveButtonContent($chat_id, $message) {
        $detected = $this->bot->detectMessageType($message);
        if ($detected['type']) {
            $data = json_decode(file_get_contents('addbutton.txt'), true);
            $data['contents'][] = [$detected['type'], $detected['content'], $detected['caption']];
            file_put_contents('addbutton.txt', json_encode($data));
            $this->log("Saved button content: {$detected['type']} - {$detected['content']}, Caption: {$detected['caption']}");
        } else {
            $this->bot->send($chat_id, 'text', null, "⚠️ نوع پیام پشتیبانی نمی‌شه!");
            $this->log("Unsupported button content type: " . json_encode($message));
        }
    }

    private function saveButtonToDatabase($chat_id) {
        $data = json_decode(file_get_contents('addbutton.txt'), true);
        if (empty($data['name']) || !$data['position'] || empty($data['contents'])) {
            $this->bot->send($chat_id, 'text', null, "⚠️ اطلاعات کامل نیست! دوباره شروع کنید.");
            $this->showButtonManagementMenu($chat_id);
            return;
        }

        $name = $data['name'];
        $row = $data['position']['row'];
        $col = $data['position']['col'];
        $content = json_encode($data['contents']);

        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO " . TABLE_BUTTONS . " (button_name, content, row_position, col_position, prev_button) 
             VALUES (?, ?, ?, ?, NULL)"
        );
        $stmt->bind_param("ssii", $name, $content, $row, $col);
        $stmt->execute();

        unlink('addbutton.txt');

        $this->setUserState('button_management');
        $message = "✅ <b>دکمه با موفقیت اضافه شد!</b> ✅\n";
        $message .= "دکمه '$name' در جایگاه ردیف $row، ستون $col ذخیره شد.";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showButtonManagementMenu($chat_id);
        $this->log("Saved button to database: $name at $row, $col");
    }

    private function showRemoveButtonMatrix($chat_id) {
        $this->setUserState('remove_button');
        $stmt = $this->db->getConnection()->prepare("SELECT id, button_name, row_position, col_position FROM " . TABLE_BUTTONS . " WHERE prev_button IS NULL");
        $stmt->execute();
        $buttons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $button_positions = [];
        foreach ($buttons as $button) {
            $key = $button['row_position'] . '_' . $button['col_position'];
            $button_positions[$key] = ['id' => $button['id'], 'name' => $button['button_name']];
        }

        $message = "🗑️ <b>حذف دکمه</b> 🗑️\n";
        $message .= "دکمه مورد نظر برای حذف رو از ماتریس زیر انتخاب کنید:\n\n";
        $inline_keyboard = [];
        for ($row = 1; $row <= 9; $row++) {
            $row_buttons = [];
            for ($col = 1; $col <= 3; $col++) {
                $key = $row . '_' . $col;
                if (isset($button_positions[$key])) {
                    $button = $button_positions[$key];
                    $row_buttons[] = [
                        'text' => $button['name'] . ' 🗑️',
                        'callback_data' => "remove_button_" . $button['id']
                    ];
                } else {
                    $row_buttons[] = [
                        'text' => 'تعریف نشده',
                        'callback_data' => 'undefined'
                    ];
                }
            }
            $inline_keyboard[] = $row_buttons;
        }

        $reply_markup = [
            'inline_keyboard' => $inline_keyboard
        ];
        $response = $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->saveMatrixMessageId($chat_id, $response['result']['message_id']);

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "دکمه رو از ماتریس بالا انتخاب کنید:", $reply_markup);
        $this->log("Showed remove button matrix");
    }

    private function requestRemoveButtonConfirmation($chat_id, $button_id, $matrix_message_id) {
        $stmt = $this->db->getConnection()->prepare("SELECT button_name FROM " . TABLE_BUTTONS . " WHERE id = ?");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) return;

        $button_name = $result['button_name'];
        file_put_contents('removebutton.txt', json_encode(['id' => $button_id, 'matrix_message_id' => $matrix_message_id]));

        $this->setUserState('remove_button_confirm');
        $message = "⚠️ شما قصد حذف دکمه '$button_name' رو دارید.\n";
        $message .= "در صورت تأیید، '✅ تأیید' رو بزنید:";
        $keyboard = [['✅ تأیید'], ['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Requested confirmation for removing button: $button_name");
    }

    private function confirmRemoveButton($chat_id) {
        $data = json_decode(file_get_contents('removebutton.txt'), true);
        $button_id = $data['id'];
        $matrix_message_id = $data['matrix_message_id'];

        $stmt = $this->db->getConnection()->prepare("SELECT button_name FROM " . TABLE_BUTTONS . " WHERE id = ?");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $button_name = $result['button_name'];

        $stmt = $this->db->getConnection()->prepare("DELETE FROM " . TABLE_BUTTONS . " WHERE id = ?");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();

        unlink('removebutton.txt');
        $this->bot->deleteMessage($chat_id, $matrix_message_id);

        $this->setUserState('remove_button');
        $message = "✅ دکمه '$button_name' با موفقیت حذف شد!";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showRemoveButtonMatrix($chat_id);
        $this->log("Removed button: $button_name");
    }

    private function showEditButtonMatrix($chat_id) {
        $this->setUserState('edit_button');
        $stmt = $this->db->getConnection()->prepare("SELECT id, button_name, row_position, col_position FROM " . TABLE_BUTTONS . " WHERE prev_button IS NULL");
        $stmt->execute();
        $buttons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $button_positions = [];
        foreach ($buttons as $button) {
            $key = $button['row_position'] . '_' . $button['col_position'];
            $button_positions[$key] = ['id' => $button['id'], 'name' => $button['button_name']];
        }

        $message = "✏️ <b>ویرایش دکمه‌ها</b> ✏️\n";
        $message .= "دکمه مورد نظر برای ویرایش رو از ماتریس زیر انتخاب کنید:\n\n";
        $inline_keyboard = [];
        for ($row = 1; $row <= 9; $row++) {
            $row_buttons = [];
            for ($col = 1; $col <= 3; $col++) {
                $key = $row . '_' . $col;
                if (isset($button_positions[$key])) {
                    $button = $button_positions[$key];
                    $row_buttons[] = [
                        'text' => $button['name'],
                        'callback_data' => "edit_button_" . $button['id']
                    ];
                } else {
                    $row_buttons[] = [
                        'text' => 'تعریف نشده',
                        'callback_data' => 'undefined'
                    ];
                }
            }
            $inline_keyboard[] = $row_buttons;
        }

        $reply_markup = [
            'inline_keyboard' => $inline_keyboard
        ];
        $response = $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->saveMatrixMessageId($chat_id, $response['result']['message_id']);

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "دکمه رو از ماتریس بالا انتخاب کنید:", $reply_markup);
        $this->log("Showed edit button matrix");
    }

    private function startEditButtonName($chat_id, $button_id, $matrix_message_id) {
        $stmt = $this->db->getConnection()->prepare("SELECT button_name, row_position, col_position, content FROM " . TABLE_BUTTONS . " WHERE id = ?");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) return;

        $data = [
            'id' => $button_id,
            'name' => $result['button_name'],
            'row' => $result['row_position'],
            'col' => $result['col_position'],
            'content' => json_decode($result['content'], true),
            'matrix_message_id' => $matrix_message_id
        ];
        file_put_contents('editbutton.txt', json_encode($data));

        $this->setUserState('edit_button_name');
        $message = "✏️ <b>ویرایش نام دکمه</b> ✏️\n";
        $message .= "نام فعلی دکمه: '{$result['button_name']}'\n";
        $message .= "نام جدید دکمه رو وارد کنید و '✅ تأیید' رو بزنید یا برای بدون تغییر فقط '✅ تأیید' رو بزنید:";
        $keyboard = [['✅ تأیید'], ['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started editing button name for ID: $button_id");
    }

    private function saveEditButtonName($chat_id, $text) {
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        if ($text !== '✅ تأیید') {
            if (empty($text) || $text === 'NO_TEXT') {
                $this->bot->send($chat_id, 'text', null, "⚠️ لطفاً یه نام معتبر وارد کنید یا فقط '✅ تأیید' رو بزنید!");
                return;
            }
            $data['name'] = $text;
        }

        file_put_contents('editbutton.txt', json_encode($data));

        $this->setUserState('edit_button_position');
        $this->showEditButtonPositionMatrix($chat_id);
        $this->log("Saved edited button name: {$data['name']}");
    }

    private function showEditButtonPositionMatrix($chat_id) {
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        $current_row = $data['row'];
        $current_col = $data['col'];

        $stmt = $this->db->getConnection()->prepare("SELECT row_position, col_position FROM " . TABLE_BUTTONS . " WHERE prev_button IS NULL AND id != ?");
        $stmt->bind_param("i", $data['id']);
        $stmt->execute();
        $occupied = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $occupied_positions = [];
        foreach ($occupied as $pos) {
            $occupied_positions[$pos['row_position'] . '_' . $pos['col_position']] = true;
        }

        $message = "📍 <b>انتخاب جایگاه جدید دکمه</b> 📍\n";
        $message .= "جایگاه فعلی دکمه: ردیف $current_row، ستون $current_col\n";
        $message .= "جایگاه جدید رو از دایره‌های سبز انتخاب کنید یا دایره آبی رو برای بدون تغییر انتخاب کنید:\n\n";
        $inline_keyboard = [];
        for ($row = 1; $row <= 9; $row++) {
            $row_buttons = [];
            for ($col = 1; $col <= 3; $col++) {
                $key = $row . '_' . $col;
                $is_current = ($row == $current_row && $col == $current_col);
                $is_occupied = isset($occupied_positions[$key]);
                $row_buttons[] = [
                    'text' => $is_current ? '🔵' : ($is_occupied ? '🔴' : '🟢'),
                    'callback_data' => $is_occupied && !$is_current ? 'occupied' : "edit_position_$key"
                ];
            }
            $inline_keyboard[] = $row_buttons;
        }

        $reply_markup = [
            'inline_keyboard' => $inline_keyboard
        ];
        $response = $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->saveMatrixMessageId($chat_id, $response['result']['message_id']);

        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, "جایگاه رو از ماتریس بالا انتخاب کنید:", $reply_markup);
        $this->log("Showed edit button position matrix");
    }

    private function saveEditButtonPosition($chat_id, $row, $col) {
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        $data['row'] = (int)$row;
        $data['col'] = (int)$col;
        file_put_contents('editbutton.txt', json_encode($data));

        $this->requestEditButtonContent($chat_id);
        $this->log("Saved edited button position: Row $row, Col $col");
    }

    private function requestEditButtonContent($chat_id) {
        $this->setUserState('edit_button_content');
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        $message = "📝 <b>ویرایش محتوای دکمه</b> 📝\n";
        $message .= "محتوای جدید دکمه '{$data['name']}' رو بفرستید (متن، عکس، ویدئو و ...).\n";
        $message .= "هر تعداد محتوا می‌تونید بفرستید، یا برای بدون تغییر '✅ تأیید' رو بزنید:";
        $keyboard = [['✅ تأیید'], ['⬅️ بازگشت']];
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started requesting edited button content");
    }

    private function saveEditButtonContent($chat_id, $message) {
        $detected = $this->bot->detectMessageType($message);
        if ($detected['type']) {
            $data = json_decode(file_get_contents('editbutton.txt'), true);
            $data['content'] = [[$detected['type'], $detected['content'], $detected['caption']]];
            file_put_contents('editbutton.txt', json_encode($data));
            $this->log("Saved edited button content (replaced): {$detected['type']} - {$detected['content']}, Caption: {$detected['caption']}");
        } else {
            $this->bot->send($chat_id, 'text', null, "⚠️ نوع پیام پشتیبانی نمی‌شه!");
            $this->log("Unsupported edit button content type: " . json_encode($message));
        }
    }

    private function saveEditedButtonToDatabase($chat_id) {
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        $id = $data['id'];
        $name = $data['name'];
        $row = $data['row'];
        $col = $data['col'];
        $content = json_encode($data['content']);

        $stmt = $this->db->getConnection()->prepare(
            "UPDATE " . TABLE_BUTTONS . " SET button_name = ?, content = ?, row_position = ?, col_position = ? WHERE id = ?"
        );
        $stmt->bind_param("ssiii", $name, $content, $row, $col, $id);
        $stmt->execute();

        unlink('editbutton.txt');

        $this->setUserState('button_management');
        $message = "✅ دکمه '$name' با موفقیت ویرایش شد!";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showButtonManagementMenu($chat_id);
        $this->log("Saved edited button to database: $name at Row $row, Col $col");
    }

    private function handleBack($chat_id) {
        $matrix_id = $this->getMatrixMessageId($chat_id);
        if ($matrix_id) {
            $this->bot->deleteMessage($chat_id, $matrix_id);
            $this->clearMatrixMessageId($chat_id);
        }
        $last_menu = $this->getUserState();
        $this->log("Handling back, last_menu: $last_menu");

        if ($last_menu === 'main') {
            $this->showMainMenu($chat_id);
        } elseif ($last_menu === 'settings') {
            $this->showMainMenu($chat_id);
        } elseif (in_array($last_menu, ['stats', 'broadcast', 'broadcast_done', 'edit_intro', 'user_management', 'button_management'])) {
            $this->showSettingsMenu($chat_id);
        } elseif (in_array($last_menu, ['admin_management', 'add_admin', 'remove_admin', 'remove_user'])) {
            $this->showUserManagementMenu($chat_id);
        } elseif (in_array($last_menu, ['add_button_name', 'add_button_position', 'add_button_content', 'remove_button', 'remove_button_confirm', 'edit_button', 'edit_button_name', 'edit_button_position', 'edit_button_content'])) {
            $this->showButtonManagementMenu($chat_id);
            if (file_exists('addbutton.txt')) {
                unlink('addbutton.txt');
            }
            if (file_exists('removebutton.txt')) {
                unlink('removebutton.txt');
            }
            if (file_exists('editbutton.txt')) {
                unlink('editbutton.txt');
            }
        } else {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT button_name, prev_button FROM " . TABLE_BUTTONS . " WHERE id = ?"
            );
            $stmt->bind_param("i", $last_menu);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && $result['prev_button'] !== null) {
                $this->showButtonContent($chat_id, $result['button_name']);
            } else {
                $this->showMainMenu($chat_id);
            }
        }
    }
}

$db = new Database();
$bot = new TelegramBot(BOT_TOKEN);

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