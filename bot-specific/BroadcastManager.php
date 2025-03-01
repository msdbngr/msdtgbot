<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/UserManager.php';
require_once __DIR__ . '/BotConfig.php';
require_once __DIR__ . '/BackHandler.php';
require_once __DIR__ . '/ContentCollector.php';

class BroadcastManager {
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

    public function handleBroadcastMessage($chat_id, $message, $state) {
        $text = $message['text'] ?? 'NO_TEXT';

        if ($text === '📢 ارسال پیام به همه' && $this->userManager->isAdmin()) {
            $this->startBroadcast($chat_id);
            return true;
        } elseif ($text === '✅ تأیید ارسال' && $this->userManager->isAdmin() && $state === 'broadcast') {
            $this->confirmBroadcast($chat_id);
            return true;
        } elseif ($text === '📢 ارسال پیام دیگر' && $this->userManager->isAdmin() && $state === 'broadcast_done') {
            $this->startBroadcast($chat_id);
            return true;
        } elseif ($state === 'broadcast' && $this->userManager->isAdmin()) {
            $this->saveBroadcastMessage($chat_id, $message);
            return true;
        } elseif ($text === '⬅️ بازگشت') {
            $this->backHandler->handle($chat_id, $state, [$this, 'showMenu']);
            return true;
        }
        return false;
    }

    private function startBroadcast($chat_id) {
        $this->userManager->setState('broadcast');
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
        $messages = $this->getBroadcastMessages();
        $updated_messages = $this->contentCollector->collect($message, $messages, $this->userManager->getUserId());
        $this->saveBroadcastMessages($updated_messages);
    }

    private function confirmBroadcast($chat_id) {
        $messages = $this->getBroadcastMessages();
        $this->log("Confirming broadcast, messages: " . json_encode($messages));

        if (empty($messages)) {
            $this->bot->send($chat_id, 'text', null, "⚠️ هیچ پیامی برای ارسال وجود نداره!");
            $this->userManager->setState('settings');
            $this->log("No messages to broadcast");
            return;
        }

        $this->broadcastMessage($messages);

        $this->clearBroadcastMessages();
        $this->log("Broadcast sent and DB array cleared");

        $this->userManager->setState('broadcast_done');
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
            $stmt = $this->db->getConnection()->prepare("SELECT user_id FROM " . $this->configClass::get('TABLE_USERS') . " LIMIT ? OFFSET ?");
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

    private function getBroadcastMessages() {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT broadcast_data FROM " . $this->configClass::get('TABLE_USERS') . " WHERE user_id = ?"
        );
        $stmt->bind_param("i", $this->userManager->getUserId());
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result && $result['broadcast_data'] ? json_decode($result['broadcast_data'], true) : [];
    }

    private function saveBroadcastMessages($messages) {
        $json_data = json_encode($messages);
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE " . $this->configClass::get('TABLE_USERS') . " SET broadcast_data = ? WHERE user_id = ?"
        );
        $stmt->bind_param("si", $json_data, $this->userManager->getUserId());
        $stmt->execute();
    }

    private function clearBroadcastMessages() {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE " . $this->configClass::get('TABLE_USERS') . " SET broadcast_data = NULL WHERE user_id = ?"
        );
        $stmt->bind_param("i", $this->userManager->getUserId());
        $stmt->execute();
    }

    public function showMenu($chat_id, $menu_type) {
        if ($menu_type === 'settings') {
            // این رو به SettingsManager پاس می‌ده، پس اینجا خالی می‌مونه
        }
    }
}