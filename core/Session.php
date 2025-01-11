<?php
class Session {
    public static function start($chat_id) {
        if (session_status() === PHP_SESSION_NONE) {
            session_id($chat_id); // تنظیم session_id قبل از شروع سشن
            session_start();
            Logger::log("سشن برای chat_id: $chat_id شروع شد.");
        }
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
        Logger::log("مقدار برای کلید $key تنظیم شد: " . json_encode($value));
    }

    public static function get($key) {
        $value = $_SESSION[$key] ?? null;
        Logger::log("مقدار برای کلید $key دریافت شد: " . json_encode($value));
        return $value;
    }

    public static function destroy() {
        session_destroy();
        Logger::log("سشن برای کاربر فعلی از بین رفت.");
    }
}
?>