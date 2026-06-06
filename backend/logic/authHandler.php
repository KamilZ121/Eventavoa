<?php

session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/DBAccess.php";

$conn = DBAccess::getInstance()->getConnection();
$action = $_REQUEST['action'] ?? '';

// Neuen Benutzer registrieren
if ($action === 'register') {
    $anrede = trim($_POST['anrede'] ?? '');
    $vorname = trim($_POST['vorname'] ?? '');
    $nachname = trim($_POST['nachname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $benutzername = trim($_POST['benutzername'] ?? '');
    $passwort = $_POST['passwort'] ?? '';
    $passwort2 = $_POST['passwort2'] ?? '';

    // Pflichtfelder check
    if ($vorname === '' || $nachname === '' || $email === '' || $benutzername === '' || $passwort === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte alle Pflichtfelder ausfüllen.']);
        exit;
    }

    // Passwörter müssen übereinstimmen
    if ($passwort !== $passwort2) {
        echo json_encode(['success' => false, 'message' => 'Die Passwörter stimmen nicht überein.']);
        exit;
    }

    // E-Mail/Benutzername check
    $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR benutzername = ?");
    mysqli_stmt_bind_param($check, "ss", $email, $benutzername);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    if (mysqli_stmt_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'E-Mail oder Benutzername ist bereits vergeben.']);
        exit;
    }

    // Passwort hashen
    $hash = password_hash($passwort, PASSWORD_DEFAULT);

    // Benutzer anlegen
    $stmt = mysqli_prepare($conn, "
        INSERT INTO users (anrede, vorname, nachname, email, benutzername, passwort_hash)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "ssssss", $anrede, $vorname, $nachname, $email, $benutzername, $hash);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registrierung fehlgeschlagen. Bitte später erneut versuchen.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
