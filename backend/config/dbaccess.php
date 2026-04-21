<?php
class DBAccess {
    private static $instance = null;
    private $conn;

    private $host = 'localhost';
    private $dbname = 'eventavoa';
    private $username = 'root';
    private $password = '';

    private function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->dbname);

        if ($this->conn->connect_error) {
            die("DB Fehler: " . $this->conn->connect_error);
        }

        $this->conn->set_charset('utf8mb4');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DBAccess();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}