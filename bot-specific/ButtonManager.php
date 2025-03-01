<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/UserManager.php';
require_once __DIR__ . '/BotConfig.php';
require_once __DIR__ . '/BackHandler.php';
require_once __DIR__ . '/ContentCollector.php';

class ButtonManager {
    private $db;
    private $bot;
    private $userManager;
    private $configClass;
    private $backHandler;
    private $contentCollector;

    public function __construct($db, $bot, $userManager, $configClass = 'BotConfig') {
        $this->db = $db;
        $this->bot = $bot;
        $this->userManager = $userManager;
        $this->configClass = $configClass;
        $this->backHandler = new BackHandler($db, $bot, $userManager);
        $this->contentCollector = new ContentCollector($bot);
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log = "[$timestamp] User: {$this->userManager->getUserId()} - $message\n";
        file_put_contents('log.txt', $log, FILE_APPEND);
    }

    public function getButtons($prev_button) {
        $query = "SELECT id, button_name, row_position, col_position 
                  FROM " . $this->configClass::get('TABLE_BUTTONS') . " 
                  WHERE prev_button IS NULL";
        if ($prev_button !== null) {
            $query = "SELECT id, button_name, row_position, col_position 
                      FROM " . $this->configClass::get('TABLE_BUTTONS') . " 
                      WHERE prev_button = ?";
        }
        $stmt = $this->db->getConnection()->prepare($query);
        if ($prev_button !== null) {
            $stmt->bind_param("i", $prev_button);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function buildKeyboard($buttons) {
        $keyboard = array_fill(0, 9, []);
        foreach ($buttons as $button) {
            $row = max(0, min(8, $button['row_position'] - 1));
            $col = max(0, min(2, $button['col_position'] - 1));
            $keyboard[$row][$col] = $button['button_name'];
        }
        return array_filter($keyboard);
    }

    public function getIntroContent() {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT content FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE is_intro = 1 LIMIT 1"
        );
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['content'] : null;
    }

    public function showButtonContent($chat_id, $button_name) {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT id, content, prev_button FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE button_name = ?"
        );
        $stmt->bind_param("s", $button_name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) return;

        $content = json_decode($result['content'], true);
        $prev_button = $result['prev_button'];
        $button_id = $result['id'];

        $this->userManager->setState($prev_button === null ? 'main' : $prev_button);

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
            $this->userManager->setState($button_id);
            $this->bot->send($chat_id, 'text', null, "زیرمنو:", $reply_markup);
        }
    }

    public function handleButtonMessage($chat_id, $text, $state) {
        if ($text === '🛠️ مدیریت دکمه‌ها' && $this->userManager->isAdmin()) {
            $this->showButtonManagementMenu($chat_id);
            return true;
        } elseif ($text === '➕ اضافه کردن دکمه جدید' && $this->userManager->isAdmin()) {
            $this->startAddButton($chat_id);
            return true;
        } elseif ($state === 'add_button_name' && $this->userManager->isAdmin()) {
            $this->saveButtonName($chat_id, $text);
            return true;
        } elseif ($state === 'add_button_content' && $this->userManager->isAdmin()) {
            if ($text === '✅ تأیید') {
                $this->saveButtonToDatabase($chat_id);
            } else {
                $this->saveButtonContent($chat_id, $text);
            }
            return true;
        } elseif ($text === '🗑️ حذف دکمه' && $this->userManager->isAdmin()) {
            $this->showRemoveButtonMatrix($chat_id);
            return true;
        } elseif ($state === 'remove_button_confirm' && $this->userManager->isAdmin() && $text === '✅ تأیید') {
            $this->confirmRemoveButton($chat_id);
            return true;
        } elseif ($text === '✏️ ویرایش دکمه‌ها' && $this->userManager->isAdmin()) {
            $this->showEditButtonMatrix($chat_id);
            return true;
        } elseif ($state === 'edit_button_name' && $this->userManager->isAdmin()) {
            $this->saveEditButtonName($chat_id, $text);
            return true;
        } elseif ($state === 'edit_button_position' && $this->userManager->isAdmin() && $text === '✅ تأیید') {
            $this->requestEditButtonContent($chat_id);
            return true;
        } elseif ($state === 'edit_button_content' && $this->userManager->isAdmin()) {
            if ($text === '✅ تأیید') {
                $this->saveEditedButtonToDatabase($chat_id);
            } else {
                $this->saveEditButtonContent($chat_id, $text);
            }
            return true;
        } elseif ($text === '⬅️ بازگشت') {
            $this->backHandler->handle($chat_id, $state, [$this, 'showMenu']);
            return true;
        }
        return false;
    }

    public function handleCallbackQuery($chat_id, $callback_query) {
        $data = $callback_query['data'];
        $message_id = $callback_query['message']['message_id'];

        if (strpos($data, 'button_position_') === 0) {
            $position = substr($data, strlen('button_position_'));
            list($row, $col) = explode('_', $position);
            $this->saveButtonPosition($chat_id, $row, $col);
            $this->bot->deleteMessage($chat_id, $message_id);
            $this->clearMatrixMessageId($chat_id);
        } elseif (strpos($data, 'remove_button_') === 0) {
            $button_id = substr($data, strlen('remove_button_'));
            $this->requestRemoveButtonConfirmation($chat_id, $button_id, $message_id);
            $this->clearMatrixMessageId($chat_id);
        } elseif (strpos($data, 'edit_button_') === 0) {
            $button_id = substr($data, strlen('edit_button_'));
            $this->startEditButtonName($chat_id, $button_id, $message_id);
            $this->clearMatrixMessageId($chat_id);
        } elseif (strpos($data, 'edit_position_') === 0) {
            $position = substr($data, strlen('edit_position_'));
            list($row, $col) = explode('_', $position);
            $this->saveEditButtonPosition($chat_id, $row, $col);
            $this->bot->deleteMessage($chat_id, $message_id);
            $this->clearMatrixMessageId($chat_id);
        }
    }

    public function showMenu($chat_id, $menu_type) {
        if ($menu_type === 'button_management') {
            $this->showButtonManagementMenu($chat_id);
        } elseif (is_numeric($menu_type)) {
            $this->showButtonContent($chat_id, $this->getButtonName($menu_type));
        }
    }

    public function getButtonName($button_id) {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT button_name FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE id = ?"
        );
        $stmt->bind_param("i", $button_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['button_name'] : '';
    }

    private function showButtonManagementMenu($chat_id) {
        $this->userManager->setState('button_management');
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
        $this->userManager->setState('add_button_name');
        $message = "➕ <b>اضافه کردن دکمه جدید</b> ➕\nلطفاً نام دکمه جدید رو بفرستید:";
        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
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
        $this->userManager->setState('add_button_position');
        $this->showButtonPositionMatrix($chat_id);
        $this->log("Saved button name: $button_name");
    }

    private function showButtonPositionMatrix($chat_id) {
        $stmt = $this->db->getConnection()->prepare("SELECT row_position, col_position FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE prev_button IS NULL");
        $stmt->execute();
        $occupied = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $occupied_positions = [];
        foreach ($occupied as $pos) {
            $occupied_positions[$pos['row_position'] . '_' . $pos['col_position']] = true;
        }

        $message = "📍 <b>انتخاب جایگاه دکمه</b> 📍\nموقعیت دکمه رو از ماتریس زیر انتخاب کنید (سبز = خالی، قرمز = اشغال‌شده):\n\n";
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

        $reply_markup = ['inline_keyboard' => $inline_keyboard];
        $response = $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->saveMatrixMessageId($chat_id, $response['result']['message_id']);
        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
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
        $this->userManager->setState('add_button_content');
        $message = "📝 <b>محتوای دکمه</b> 📝\nلطفاً محتوای دکمه رو بفرستید (متن، عکس، ویدئو و ...).\nهر تعداد محتوا می‌تونید بفرستید، وقتی آماده شد '✅ تأیید' رو بزنید:";
        $keyboard = [['✅ تأیید'], ['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started requesting button content");
    }

    private function saveButtonContent($chat_id, $text) {
        $data = json_decode(file_get_contents('addbutton.txt'), true);
        $content = $this->contentCollector->collect(['text' => $text], $data['contents'], $this->userManager->getUserId());
        $data['contents'] = $content;
        file_put_contents('addbutton.txt', json_encode($data));
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
            "INSERT INTO " . $this->configClass::get('TABLE_BUTTONS') . " (button_name, content, row_position, col_position, prev_button) 
             VALUES (?, ?, ?, ?, NULL)"
        );
        $stmt->bind_param("ssii", $name, $content, $row, $col);
        $stmt->execute();
        unlink('addbutton.txt');
        $this->userManager->setState('button_management');
        $message = "✅ <b>دکمه با موفقیت اضافه شد!</b> ✅\nدکمه '$name' در جایگاه ردیف $row، ستون $col ذخیره شد.";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showButtonManagementMenu($chat_id);
        $this->log("Saved button to database: $name at $row, $col");
    }

    private function showRemoveButtonMatrix($chat_id) {
        $this->userManager->setState('remove_button');
        $stmt = $this->db->getConnection()->prepare("SELECT id, button_name, row_position, col_position FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE prev_button IS NULL");
        $stmt->execute();
        $buttons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $button_positions = [];
        foreach ($buttons as $button) {
            $key = $button['row_position'] . '_' . $button['col_position'];
            $button_positions[$key] = ['id' => $button['id'], 'name' => $button['button_name']];
        }

        $message = "🗑️ <b>حذف دکمه</b> 🗑️\nدکمه مورد نظر برای حذف رو از ماتریس زیر انتخاب کنید:\n\n";
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
                    $row_buttons[] = ['text' => 'تعریف نشده', 'callback_data' => 'undefined'];
                }
            }
            $inline_keyboard[] = $row_buttons;
        }

        $reply_markup = ['inline_keyboard' => $inline_keyboard];
        $response = $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->saveMatrixMessageId($chat_id, $response['result']['message_id']);
        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
        $this->bot->send($chat_id, 'text', null, "دکمه رو از ماتریس بالا انتخاب کنید:", $reply_markup);
        $this->log("Showed remove button matrix");
    }

    private function requestRemoveButtonConfirmation($chat_id, $button_id, $matrix_message_id) {
        $stmt = $this->db->getConnection()->prepare("SELECT button_name FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE id = ?");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if (!$result) return;

        $button_name = $result['button_name'];
        file_put_contents('removebutton.txt', json_encode(['id' => $button_id, 'matrix_message_id' => $matrix_message_id]));
        $this->userManager->setState('remove_button_confirm');
        $message = "⚠️ شما قصد حذف دکمه '$button_name' رو دارید.\nدر صورت تأیید، '✅ تأیید' رو بزنید:";
        $keyboard = [['✅ تأیید'], ['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Requested confirmation for removing button: $button_name");
    }

    private function confirmRemoveButton($chat_id) {
        $data = json_decode(file_get_contents('removebutton.txt'), true);
        $button_id = $data['id'];
        $matrix_message_id = $data['matrix_message_id'];

        $stmt = $this->db->getConnection()->prepare("SELECT button_name FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE id = ?");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $button_name = $result['button_name'];

        $stmt = $this->db->getConnection()->prepare("DELETE FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE id = ?");
        $stmt->bind_param("i", $button_id);
        $stmt->execute();

        unlink('removebutton.txt');
        $this->bot->deleteMessage($chat_id, $matrix_message_id);
        $this->userManager->setState('button_management');
        $message = "✅ دکمه '$button_name' با موفقیت حذف شد!";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showButtonManagementMenu($chat_id);
        $this->log("Removed button: $button_name");
    }

    private function showEditButtonMatrix($chat_id) {
        $this->userManager->setState('edit_button');
        $stmt = $this->db->getConnection()->prepare("SELECT id, button_name, row_position, col_position FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE prev_button IS NULL");
        $stmt->execute();
        $buttons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $button_positions = [];
        foreach ($buttons as $button) {
            $key = $button['row_position'] . '_' . $button['col_position'];
            $button_positions[$key] = ['id' => $button['id'], 'name' => $button['button_name']];
        }

        $message = "✏️ <b>ویرایش دکمه‌ها</b> ✏️\nدکمه مورد نظر برای ویرایش رو از ماتریس زیر انتخاب کنید:\n\n";
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
                    $row_buttons[] = ['text' => 'تعریف نشده', 'callback_data' => 'undefined'];
                }
            }
            $inline_keyboard[] = $row_buttons;
        }

        $reply_markup = ['inline_keyboard' => $inline_keyboard];
        $response = $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->saveMatrixMessageId($chat_id, $response['result']['message_id']);
        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
        $this->bot->send($chat_id, 'text', null, "دکمه رو از ماتریس بالا انتخاب کنید:", $reply_markup);
        $this->log("Showed edit button matrix");
    }

    private function startEditButtonName($chat_id, $button_id, $matrix_message_id) {
        $stmt = $this->db->getConnection()->prepare("SELECT button_name, row_position, col_position, content FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE id = ?");
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
        $this->userManager->setState('edit_button_name');
        $message = "✏️ <b>ویرایش نام دکمه</b> ✏️\nنام فعلی دکمه: '{$result['button_name']}'\nنام جدید دکمه رو وارد کنید یا فقط '✅ تأیید' رو بزنید:";
        $keyboard = [['✅ تأیید'], ['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
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
        $this->userManager->setState('edit_button_position');
        $this->showEditButtonPositionMatrix($chat_id);
        $this->log("Saved edited button name: {$data['name']}");
    }

    private function showEditButtonPositionMatrix($chat_id) {
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        $current_row = $data['row'];
        $current_col = $data['col'];

        $stmt = $this->db->getConnection()->prepare("SELECT row_position, col_position FROM " . $this->configClass::get('TABLE_BUTTONS') . " WHERE prev_button IS NULL AND id != ?");
        $stmt->bind_param("i", $data['id']);
        $stmt->execute();
        $occupied = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $occupied_positions = [];
        foreach ($occupied as $pos) {
            $occupied_positions[$pos['row_position'] . '_' . $pos['col_position']] = true;
        }

        $message = "📍 <b>انتخاب جایگاه جدید دکمه</b> 📍\nجایگاه فعلی دکمه: ردیف $current_row، ستون $current_col\nجایگاه جدید رو انتخاب کنید:\n\n";
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

        $reply_markup = ['inline_keyboard' => $inline_keyboard];
        $response = $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->saveMatrixMessageId($chat_id, $response['result']['message_id']);
        $keyboard = [['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
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
        $this->userManager->setState('edit_button_content');
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        $message = "📝 <b>ویرایش محتوای دکمه</b> 📝\nمحتوای جدید دکمه '{$data['name']}' رو بفرستید یا برای بدون تغییر '✅ تأیید' رو بزنید:";
        $keyboard = [['✅ تأیید'], ['⬅️ بازگشت']];
        $reply_markup = ['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false];
        $this->bot->send($chat_id, 'text', null, $message, $reply_markup);
        $this->log("Started requesting edited button content");
    }

    private function saveEditButtonContent($chat_id, $text) {
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        $content = $this->contentCollector->collect(['text' => $text], $data['content'], $this->userManager->getUserId());
        $data['content'] = $content;
        file_put_contents('editbutton.txt', json_encode($data));
    }

    private function saveEditedButtonToDatabase($chat_id) {
        $data = json_decode(file_get_contents('editbutton.txt'), true);
        $id = $data['id'];
        $name = $data['name'];
        $row = $data['row'];
        $col = $data['col'];
        $content = json_encode($data['content']);

        $stmt = $this->db->getConnection()->prepare(
            "UPDATE " . $this->configClass::get('TABLE_BUTTONS') . " SET button_name = ?, content = ?, row_position = ?, col_position = ? WHERE id = ?"
        );
        $stmt->bind_param("ssiii", $name, $content, $row, $col, $id);
        $stmt->execute();

        unlink('editbutton.txt');
        $this->userManager->setState('button_management');
        $message = "✅ دکمه '$name' با موفقیت ویرایش شد!";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showButtonManagementMenu($chat_id);
        $this->log("Saved edited button to database: $name at Row $row, Col $col");
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
}