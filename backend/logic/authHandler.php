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

    // E-Mail serverseitig prüfen (nicht nur HTML5)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
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

// Benutzer einloggen
if ($action === 'login') {
    $benutzername = trim($_POST['benutzername'] ?? '');
    $passwort = $_POST['passwort'] ?? '';

    if ($benutzername === '' || $passwort === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte Benutzername und Passwort angeben.']);
        exit;
    }

    // User per Benutzername oder E-Mail holen
    $stmt = mysqli_prepare($conn, "SELECT id, benutzername, passwort_hash, rolle FROM users WHERE benutzername = ? OR email = ?");
    mysqli_stmt_bind_param($stmt, "ss", $benutzername, $benutzername);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);


    if (!$user || !password_verify($passwort, $user['passwort_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Benutzername oder Passwort ist falsch.']);
        exit;
    }

    // Login Session
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['benutzername'] = $user['benutzername'];
    $_SESSION['rolle'] = $user['rolle'];

    echo json_encode(['success' => true]);
    exit;
}

// Login Status
if ($action === 'status') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'loggedIn' => true,
            'benutzername' => $_SESSION['benutzername'],
            'rolle' => $_SESSION['rolle']
        ]);
    } else {
        echo json_encode(['loggedIn' => false]);
    }
    exit;
}

// Benutzer ausloggen
if ($action === 'logout') {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
