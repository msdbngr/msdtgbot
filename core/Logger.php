<?php
class Logger {
    public static function log($message) {
        $logFile = __DIR__ . '/../logs/bot.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
?>