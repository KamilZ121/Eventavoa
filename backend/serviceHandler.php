<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/config/DBAccess.php";

$conn = DBAccess::getInstance()->getConnection();

$method = $_POST["method"] ?? $_GET["method"] ?? "";

if ($method === "login") {

    $benutzername = trim($_POST["benutzername"] ?? "");
    $passwort = $_POST["passwort"] ?? "";

    if ($benutzername === "" || $passwort === "") {
        echo json_encode([
            "success" => false,
            "message" => "Bitte alle Felder ausfüllen."
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE email = ?
        OR benutzername = ?
        LIMIT 1
    ");

    $stmt->bind_param("ss", $benutzername, $benutzername);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Benutzer nicht gefunden."
        ]);
        exit;
    }

    $user = $result->fetch_assoc();

    if (!password_verify($passwort, $user["passwort_hash"])) {
        echo json_encode([
            "success" => false,
            "message" => "Falsches Passwort."
        ]);
        exit;
    }

    $_SESSION["user_id"] = $user["id"];
    $_SESSION["rolle"] = $user["rolle"];
    $_SESSION["benutzername"] = $user["benutzername"];

    echo json_encode([
        "success" => true,
        "rolle" => $user["rolle"]
    ]);

    exit;
}

if ($method === "register") {
    $anrede = trim($_POST["anrede"] ?? "");
    $vorname = trim($_POST["vorname"] ?? "");
    $nachname = trim($_POST["nachname"] ?? "");
    $adresse = trim($_POST["adresse"] ?? "");
    $plz = trim($_POST["plz"] ?? "");
    $ort = trim($_POST["ort"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $benutzername = trim($_POST["benutzername"] ?? "");
    $passwort = $_POST["passwort"] ?? "";
    $passwort2 = $_POST["passwort2"] ?? "";
    $paymentType = $_POST["payment_type"] ?? "";

    if ($anrede === "" || $vorname === "" || $nachname === "" || $adresse === "" ||
        $plz === "" || $ort === "" || $email === "" || $benutzername === "" ||
        $passwort === "" || $passwort2 === "" || $paymentType === "") {
        echo json_encode(["success" => false, "message" => "Bitte alle Felder ausfüllen."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Ungültige E-Mail-Adresse."]);
        exit;
    }

    if (!preg_match("/^[0-9]{4}$/", $plz)) {
        echo json_encode(["success" => false, "message" => "PLZ muss 4-stellig sein."]);
        exit;
    }

    if ($passwort !== $passwort2) {
        echo json_encode(["success" => false, "message" => "Passwörter stimmen nicht überein."]);
        exit;
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR benutzername = ?");
    $check->bind_param("ss", $email, $benutzername);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "E-Mail oder Benutzername bereits vergeben."]);
        exit;
    }

    $passwortHash = password_hash($passwort, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users
        (anrede, vorname, nachname, email, benutzername, passwort_hash, rolle, aktiv)
        VALUES (?, ?, ?, ?, ?, ?, 'user', 1)
    ");

    $stmt->bind_param(
        "ssssss",
        $anrede,
        $vorname,
        $nachname,
        $email,
        $benutzername,
        $passwortHash
    );

    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "User konnte nicht angelegt werden."]);
        exit;
    }

    $userId = $stmt->insert_id;

    $addressStmt = $conn->prepare("
        INSERT INTO addresses
        (user_id, address_type, strasse, plz, ort, land, is_default)
        VALUES (?, 'billing', ?, ?, ?, 'Österreich', 1)
    ");

    $addressStmt->bind_param("isss", $userId, $adresse, $plz, $ort);
    $addressStmt->execute();

    $paymentIdentifier = null;

    if ($paymentType === "paypal") {
        $paymentIdentifier = $email;
    }

    if ($paymentType === "kreditkarte") {
        $paymentIdentifier = "**** **** **** 1234";
    }

    $paymentStmt = $conn->prepare("
        INSERT INTO payment_methods
        (user_id, payment_type, payment_identifier, is_default)
        VALUES (?, ?, ?, 1)
    ");

    $paymentStmt->bind_param("iss", $userId, $paymentType, $paymentIdentifier);
    $paymentStmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Registrierung erfolgreich."
    ]);
    exit;
}

echo json_encode([
    "success" => false,
    "message" => "Unbekannte Methode."
]);