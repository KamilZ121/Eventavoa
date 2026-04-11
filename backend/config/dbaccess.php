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
            die(json_encode(['error' => 'Datenbankverbindung fehlgeschlagen: ' . $this->conn->connect_error]));
        }
        $this->conn->set_charset('utf8mb4');
    }

    public static function getInstance(): DBAccess {
        if (self::$instance === null) {
            self::$instance = new DBAccess();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli {
        return $this->conn;
    }
}

