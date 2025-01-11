<?php
class Database {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }

        // تنظیم کالیشن به utf8mb4_unicode_ci
        $this->conn->set_charset("utf8mb4");
        $this->conn->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    }

    public function getConnection() {
        return $this->conn;
    }

    public function closeConnection() {
        $this->conn->close();
    }
}
?>