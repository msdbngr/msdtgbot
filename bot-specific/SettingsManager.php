<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/UserManager.php';
require_once __DIR__ . '/BotConfig.php';
require_once __DIR__ . '/ButtonManager.php';
require_once __DIR__ . '/BackHandler.php';
require_once __DIR__ . '/ContentCollector.php';

class SettingsManager {
    private $db;
    private $bot;
    private $userManager;
    private $buttonManager;
    private $configClass;
    private $backHandler;
    private $contentCollector;

    public function __construct($db, $bot, $userManager, $buttonManager, $configClass = 'BotConfig') {
        $this->db = $db;
        $this->bot = $bot;
        $this->userManager = $userManager;
        $this->buttonManager = $buttonManager;
        $this->configClass = $configClass;
        $this->backHandler = new BackHandler($db, $bot, $userManager);
        $this->contentCollector = new ContentCollector($bot);
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log = "[$timestamp] User: {$this->userManager->getUserId()} - $message\n";
        file_put_contents('log.txt', $log, FILE_APPEND);
    }

    public function showSettingsMenu($chat_id) {
        $this->userManager->setState('settings');
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

    public function handleSettingsMessage($chat_id, $text, $state) {
        if ($text === '📊 آمار بات' && $this->userManager->isAdmin()) {
            $this->showBotStats($chat_id);
            return true;
        } elseif ($text === '✏️ ویرایش اینترو' && $this->userManager->isAdmin()) {
            $this->startEditIntro($chat_id);
            return true;
        } elseif ($state === 'edit_intro' && $this->userManager->isAdmin()) {
            $this->saveIntroContent($chat_id, $text);
            return true;
        } elseif ($text === '👥 مدیریت کاربران' && $this->userManager->isAdmin()) {
            $this->showUserManagementMenu($chat_id);
            return true;
        } elseif ($text === '👤 مدیریت ادمین‌ها' && $this->userManager->isAdmin()) {
            $this->showAdminManagementMenu($chat_id);
            return true;
        } elseif ($text === '➕ اضافه کردن ادمین' && $this->userManager->isAdmin()) {
            $this->startAddAdmin($chat_id);
            return true;
        } elseif ($state === 'add_admin' && $this->userManager->isAdmin()) {
            $this->addAdmin($chat_id, $text);
            return true;
        } elseif ($text === '🗑️ حذف ادمین' && $this->userManager->isAdmin()) {
            $this->showRemoveAdminMenu($chat_id);
            return true;
        } elseif ($text === '🗑️ حذف کاربر' && $this->userManager->isAdmin()) {
            $this->startRemoveUser($chat_id);
            return true;
        } elseif ($state === 'remove_user' && $this->userManager->isAdmin()) {
            $this->removeUser($chat_id, $text);
            return true;
        } elseif ($text === '⬅️ بازگشت') {
            $this->backHandler->handle($chat_id, $state, [$this, 'showMenu']);
            return true;
        }
        return false;
    }

    public function showMenu($chat_id, $menu_type) {
        if ($menu_type === 'settings') {
            $this->showSettingsMenu($chat_id);
        } elseif ($menu_type === 'user_management') {
            $this->showUserManagementMenu($chat_id);
        }
    }

    private function showBotStats($chat_id) {
        $this->userManager->setState('stats');
        $stmt = $this->db->getConnection()->prepare("SELECT COUNT(*) as user_count FROM " . $this->configClass::get('TABLE_USERS'));
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

    private function startEditIntro($chat_id) {
        $this->userManager->setState('edit_intro');
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

    private function saveIntroContent($chat_id, $text) {
        $detected = $this->contentCollector->collect(['text' => $text], [], $this->userManager->getUserId());
        if (!empty($detected)) {
            $content = $detected[0][0] === 'text' ? $detected[0][1] : implode('|', $detected[0]);
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE " . $this->configClass::get('TABLE_BUTTONS') . " SET content = ? WHERE is_intro = 1"
            );
            $stmt->bind_param("s", $content);
            $stmt->execute();
            $this->log("Intro content updated: $content");

            $this->userManager->setState('settings');
            $message = "✅ <b>اینترو با موفقیت ویرایش شد!</b> ✅\nمحتوای جدید ذخیره شد.";
            $this->bot->send($chat_id, 'text', null, $message);
            $this->showSettingsMenu($chat_id);
        } else {
            $this->bot->send($chat_id, 'text', null, "⚠️ نوع پیام پشتیبانی نمی‌شه!");
            $this->log("Unsupported intro type");
        }
    }

    private function showUserManagementMenu($chat_id) {
        $this->userManager->setState('user_management');
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
        $this->userManager->setState('admin_management');
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
        $this->userManager->setState('add_admin');
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
        $stmt = $this->db->getConnection()->prepare("SELECT user_id, is_admin FROM " . $this->configClass::get('TABLE_USERS') . " WHERE username = ?");
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

        $stmt = $this->db->getConnection()->prepare("UPDATE " . $this->configClass::get('TABLE_USERS') . " SET is_admin = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $result['user_id']);
        $stmt->execute();

        $this->userManager->setState('admin_management');
        $message = "✅ <b>ادمین با موفقیت اضافه شد!</b> ✅\nکاربر @$username حالا ادمینه.";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showAdminManagementMenu($chat_id);
        $this->log("Added admin: @$username");
    }

    private function showRemoveAdminMenu($chat_id) {
        $this->userManager->setState('remove_admin');
        $stmt = $this->db->getConnection()->prepare("SELECT user_id, username FROM " . $this->configClass::get('TABLE_USERS') . " WHERE is_admin = 1 AND user_id != ?");
        $stmt->bind_param("i", $this->userManager->getUserId());
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
        $this->userManager->setState('remove_user');
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
        $stmt = $this->db->getConnection()->prepare("SELECT user_id, is_admin FROM " . $this->configClass::get('TABLE_USERS') . " WHERE username = ?");
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

        if ($result['user_id'] == $this->userManager->getUserId()) {
            $this->bot->send($chat_id, 'text', null, "⚠️ نمی‌تونید خودتون رو حذف کنید!");
            return;
        }

        $stmt = $this->db->getConnection()->prepare("DELETE FROM " . $this->configClass::get('TABLE_USERS') . " WHERE user_id = ?");
        $stmt->bind_param("i", $result['user_id']);
        $stmt->execute();

        $this->userManager->setState('user_management');
        $message = "✅ <b>کاربر با موفقیت حذف شد!</b> ✅\nکاربر @$username از دیتابیس حذف شد.";
        $this->bot->send($chat_id, 'text', null, $message);
        $this->showUserManagementMenu($chat_id);
        $this->log("Removed user: @$username");
    }
}