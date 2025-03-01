<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/UserManager.php';

class BackHandler {
    private $db;
    private $bot;
    private $userManager;

    public function __construct($db, $bot, $userManager) {
        $this->db = $db;
        $this->bot = $bot;
        $this->userManager = $userManager;
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log = "[$timestamp] User: {$this->userManager->getUserId()} - $message\n";
        file_put_contents('log.txt', $log, FILE_APPEND);
    }

    public function handle($chat_id, $current_state, $show_menu_callback) {
        $matrix_id = $this->getMatrixMessageId($chat_id);
        if ($matrix_id) {
            $this->bot->deleteMessage($chat_id, $matrix_id);
            $this->clearMatrixMessageId($chat_id);
        }

        $this->log("Handling back, current_state: $current_state");

        if ($current_state === 'main' || $current_state === 'settings') {
            call_user_func($show_menu_callback, $chat_id, 'main');
        } elseif (in_array($current_state, ['stats', 'broadcast', 'broadcast_done', 'edit_intro', 'button_management'])) {
            call_user_func($show_menu_callback, $chat_id, 'settings');
        } elseif (in_array($current_state, ['user_management', 'admin_management', 'add_admin', 'remove_admin', 'remove_user'])) {
            if ($current_state === 'user_management') {
                call_user_func($show_menu_callback, $chat_id, 'settings');
            } else {
                call_user_func($show_menu_callback, $chat_id, 'user_management');
            }
        } elseif (in_array($current_state, ['add_button_name', 'add_button_position', 'add_button_content', 
                                            'remove_button', 'remove_button_confirm', 'edit_button', 
                                            'edit_button_name', 'edit_button_position', 'edit_button_content'])) {
            call_user_func($show_menu_callback, $chat_id, 'button_management');
            $this->cleanupTempFiles();
        } elseif (is_numeric($current_state)) {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT prev_button FROM " . BotConfig::get('TABLE_BUTTONS') . " WHERE id = ?"
            );
            $stmt->bind_param("i", $current_state);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && $result['prev_button'] !== null) {
                call_user_func($show_menu_callback, $chat_id, $result['prev_button']);
            } else {
                call_user_func($show_menu_callback, $chat_id, 'main');
            }
        } else {
            call_user_func($show_menu_callback, $chat_id, 'main');
        }
    }

    private function cleanupTempFiles() {
        $files = ['addbutton.txt', 'removebutton.txt', 'editbutton.txt'];
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function getMatrixMessageId($chat_id) {
        $file = "matrix_$chat_id.txt";
        return file_exists($file) ? (int)file_get_contents($file) : null;
    }

    private function clearMatrixMessageId($chat_id) {
        $file = "matrix_$chat_id.txt";
        if (file_exists($file)) {
            unlink($file);
        }
    }
}