<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Config.php';

class UserManager {
    private $db;
    private $user_id;
    private $is_admin;
    private $configClass;

    public function __construct($db, $user_id, $configClass = 'Config') {
        $this->db = $db;
        $this->user_id = $user_id;
        $this->configClass = $configClass;
        $this->is_admin = $this->checkAdmin();
    }

    private function checkAdmin() {
        $stmt = $this->db->getConnection()->prepare("SELECT is_admin FROM " . $this->configClass::get('TABLE_USERS') . " WHERE user_id = ?");
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
            "INSERT INTO " . $this->configClass::get('TABLE_USERS') . " (user_id, username, first_name, last_name, last_menu) 
             VALUES (?, ?, ?, ?, 'main') 
             ON DUPLICATE KEY UPDATE username = ?, first_name = ?, last_name = ?"
        );
        $stmt->bind_param("issssss", $chat_id, $username, $first_name, $last_name, $username, $first_name, $last_name);
        $stmt->execute();
    }

    public function setState($state) {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE " . $this->configClass::get('TABLE_USERS') . " SET last_menu = ? WHERE user_id = ?"
        );
        $stmt->bind_param("si", $state, $this->user_id);
        $stmt->execute();
    }

    public function getState() {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT last_menu FROM " . $this->configClass::get('TABLE_USERS') . " WHERE user_id = ?"
        );
        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['last_menu'] : 'main';
    }

    public function isAdmin() {
        return $this->is_admin;
    }

    public function getUserId() {
        return $this->user_id;
    }
}