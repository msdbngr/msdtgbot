<?php
require_once __DIR__ . '/Config.php';

class Database {
    private $conn;
    private $configClass;

    public function __construct($configClass = 'Config') {
        $this->configClass = $configClass;
        $this->conn = new mysqli(
            $configClass::get('DB_HOST'),
            $configClass::get('DB_USER'),
            $configClass::get('DB_PASS'),
            $configClass::get('DB_NAME')
        );
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