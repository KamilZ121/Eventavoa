<?php
require_once 'dbaccess.php';

class DataHandler {
    private $conn;

    public function __construct() {
        $this->conn = DBAccess::getInstance()->getConnection();
    }

    // ---- USER ----

    public function getUserByUsername(string $benutzername): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE benutzername = ? AND aktiv = 1");
        $stmt->bind_param('s', $benutzername);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public function getUserByEmail(string $email): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ? AND aktiv = 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public function createUser(array $data): bool {
        $stmt = $this->conn->prepare("
            INSERT INTO users (anrede, vorname, nachname, adresse, plz, ort, email, benutzername, passwort, rolle)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user')
        ");
        $stmt->bind_param(
            'sssssssss',
            $data['anrede'],
            $data['vorname'],
            $data['nachname'],
            $data['adresse'],
            $data['plz'],
            $data['ort'],
            $data['email'],
            $data['benutzername'],
            $data['passwort']
        );
        return $stmt->execute();
    }

    public function usernameExists(string $benutzername): bool {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE benutzername = ?");
        $stmt->bind_param('s', $benutzername);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function emailExists(string $email): bool {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}