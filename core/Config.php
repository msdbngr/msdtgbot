<?php
class Config {
    protected static $BOT_TOKEN = '';
    protected static $DB_HOST = 'localhost';
    protected static $DB_USER = '';
    protected static $DB_PASS = '';
    protected static $DB_NAME = '';
    protected static $BASE_URL = '';

    public static function get($key) {
        return static::$$key;
    }
}