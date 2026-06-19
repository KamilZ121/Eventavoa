<?php

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../config/DBAccess.php";

$conn = DBAccess::getInstance()->getConnection();
$action = $_REQUEST['action'] ?? '';

// keine Session aber remember me cookie
if (!isset($_SESSION['user_id'])) {
    tryRememberLogin($conn);
}

// Neuen Benutzer registrieren
if ($action === 'register') {
    $anrede = trim($_POST['anrede'] ?? '');
    $vorname = trim($_POST['vorname'] ?? '');
    $nachname = trim($_POST['nachname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $benutzername = trim($_POST['benutzername'] ?? '');
    $passwort = $_POST['passwort'] ?? '';
    $passwort2 = $_POST['passwort2'] ?? '';
    $adresse = trim($_POST['adresse'] ?? '');
    $plz = trim($_POST['plz'] ?? '');
    $ort = trim($_POST['ort'] ?? '');

    // Pflichtfelder check
    if ($vorname === '' || $nachname === '' || $email === '' || $benutzername === '' || $passwort === ''
        || $adresse === '' || $plz === '' || $ort === '') {
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
        // Adresse speichern
        $newUserId = mysqli_insert_id($conn);
        $addr = mysqli_prepare($conn, "
            INSERT INTO addresses (user_id, address_type, strasse, plz, ort, is_default)
            VALUES (?, 'shipping', ?, ?, ?, 1)
        ");
        mysqli_stmt_bind_param($addr, "isss", $newUserId, $adresse, $plz, $ort);
        mysqli_stmt_execute($addr);

        // Direkt einloggen nach der Registrierung
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['benutzername'] = $benutzername;
        $_SESSION['rolle'] = 'user';

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

    // remember me cookie
    if (($_POST['remember'] ?? '') === '1') {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $userId = (int)$user['id'];

        $upd = mysqli_prepare($conn, "UPDATE users SET remember_token = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "si", $tokenHash, $userId);
        mysqli_stmt_execute($upd);

        // 30 tage
        setcookie('remember', $userId . ':' . $token, [
            'expires' => time() + 30 * 24 * 60 * 60,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

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
    // remember me in db
    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        $upd = mysqli_prepare($conn, "UPDATE users SET remember_token = NULL WHERE id = ?");
        mysqli_stmt_bind_param($upd, "i", $userId);
        mysqli_stmt_execute($upd);
    }
    // löschen
    if (isset($_COOKIE['remember'])) {
        setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/']);
    }

    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Eigene Profildaten liefern (für eingeloggte user)
if ($action === 'getProfile') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt.']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT anrede, vorname, nachname, email, benutzername, rolle FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden.']);
        exit;
    }

    echo json_encode(['success' => true, 'user' => $user]);
    exit;
}

// Eigene Stammdaten bearbeiten (passwort nötig)
if ($action === 'updateProfile') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt.']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $anrede = trim($_POST['anrede'] ?? '');
    $vorname = trim($_POST['vorname'] ?? '');
    $nachname = trim($_POST['nachname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $benutzername = trim($_POST['benutzername'] ?? '');
    $passwort = $_POST['passwort'] ?? '';

    // Pflichtfelder
    if ($vorname === '' || $nachname === '' || $email === '' || $benutzername === '' || $passwort === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte alle Pflichtfelder ausfüllen.']);
        exit;
    }

    // E-Mail Format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        exit;
    }

    // Aktuelles Passwort prüfen
    $stmt = mysqli_prepare($conn, "SELECT passwort_hash FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user || !password_verify($passwort, $user['passwort_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Das Passwort ist falsch.']);
        exit;
    }

    // E-Mail Benutzername darf nicht wem anderen gehören
    $check = mysqli_prepare($conn, "SELECT id FROM users WHERE (email = ? OR benutzername = ?) AND id != ?");
    mysqli_stmt_bind_param($check, "ssi", $email, $benutzername, $userId);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    if (mysqli_stmt_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'E-Mail oder Benutzername ist bereits vergeben.']);
        exit;
    }

    // Daten speichern
    $upd = mysqli_prepare($conn, "UPDATE users SET anrede = ?, vorname = ?, nachname = ?, email = ?, benutzername = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "sssssi", $anrede, $vorname, $nachname, $email, $benutzername, $userId);

    if (mysqli_stmt_execute($upd)) {
        // Benutzername in der Session aktuell halten
        $_SESSION['benutzername'] = $benutzername;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Speichern fehlgeschlagen. Bitte später erneut versuchen.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);

// auto login
function tryRememberLogin($conn) {
    if (empty($_COOKIE['remember'])) {
        return;
    }

    // Cookie
    $parts = explode(':', $_COOKIE['remember'], 2);
    if (count($parts) !== 2) {
        return;
    }

    $userId = (int)$parts[0];
    $tokenHash = hash('sha256', $parts[1]);

    $stmt = mysqli_prepare($conn, "SELECT id, benutzername, rolle, remember_token FROM users WHERE id = ? AND aktiv = 1");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    // wenn cookie gesetzt ist und mit hash übereinstimmt einloggen
    if (!$user || empty($user['remember_token']) || !hash_equals($user['remember_token'], $tokenHash)) {
        return;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['benutzername'] = $user['benutzername'];
    $_SESSION['rolle'] = $user['rolle'];
}
