<?php
require_once __DIR__ . '/dbaccess.php';
require_once __DIR__ . '/../models/product.class.php';
require_once __DIR__ . '/../models/user.class.php';

/** Alle häufigen Lesezugriffe auf die Datenbank an einer verständlichen Stelle. */
class DataHandler
{
    private $conn;

    function __construct()
    {
        $this->conn = DBAccess::getInstance()->getConnection();
    }

    function getConnection()
    {
        return $this->conn;
    }

    function getCategories()
    {
        $result = $this->conn->query('SELECT id, name FROM categories ORDER BY name');
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    function getProducts($categoryId = 0, $search = '')
    {
        $like = '%' . $search . '%';
        $sql = "SELECT p.id, p.category_id, p.name, p.description, p.price, p.currency,
                       p.rating, p.is_active, pi.image_path
                FROM products p
                LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                WHERE p.is_active = 1 AND (? = 0 OR p.category_id = ?)
                  AND (? = '' OR p.name LIKE ? OR p.description LIKE ?)
                ORDER BY p.name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iisss', $categoryId, $categoryId, $search, $like, $like);
        $stmt->execute();
        $products = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $products[] = new Product($row);
        }
        return $products;
    }

    function getProductById($id)
    {
        $sql = "SELECT p.id, p.category_id, p.name, p.description, p.price, p.currency,
                       p.rating, p.is_active, pi.image_path
                FROM products p
                LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                WHERE p.id = ? AND p.is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? new Product($row) : null;
    }

    function getUserForLogin($identifier)
    {
        $sql = 'SELECT id, anrede, vorname, nachname, email, benutzername, passwort_hash, rolle, aktiv, remember_token
                FROM users WHERE benutzername = ? OR email = ? LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    function userExists($email, $username, $exceptId = 0)
    {
        $sql = 'SELECT id FROM users WHERE (email = ? OR benutzername = ?) AND (? = 0 OR id != ?)';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssii', $email, $username, $exceptId, $exceptId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    function getProfile($userId)
    {
        $sql = "SELECT u.anrede, u.vorname, u.nachname, u.email, u.benutzername, u.rolle,
                       COALESCE(a.strasse, '') adresse, COALESCE(a.plz, '') plz, COALESCE(a.ort, '') ort
                FROM users u
                LEFT JOIN addresses a ON a.user_id = u.id AND a.address_type = 'shipping' AND a.is_default = 1
                WHERE u.id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    function getRememberedUser($userId)
    {
        $stmt = $this->conn->prepare('SELECT id, anrede, vorname, nachname, email, benutzername, rolle, aktiv, remember_token FROM users WHERE id = ? AND aktiv = 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
