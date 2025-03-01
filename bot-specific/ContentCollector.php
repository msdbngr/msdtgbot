<?php
require_once __DIR__ . '/TelegramBot.php';

class ContentCollector {
    private $bot;

    public function __construct($bot) {
        $this->bot = $bot;
    }

    private function log($message, $user_id) {
        $timestamp = date('Y-m-d H:i:s');
        $log = "[$timestamp] User: $user_id - $message\n";
        file_put_contents('log.txt', $log, FILE_APPEND);
    }

    public function collect($message, $existing_content = [], $user_id) {
        $detected = $this->bot->detectMessageType($message);
        if ($detected['type']) {
            $content = array_merge($existing_content, [[$detected['type'], $detected['content'], $detected['caption']]]);
            $this->log("Collected content: {$detected['type']} - {$detected['content']}", $user_id);
            return $content;
        } else {
            $this->log("Unsupported content type: " . json_encode($message), $user_id);
            return $existing_content;
        }
    }
}