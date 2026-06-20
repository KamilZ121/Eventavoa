<?php

session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/DBAccess.php";

$conn = DBAccess::getInstance()->getConnection();
$action = $_REQUEST['action'] ?? '';

// Zahlungsmöglichkeiten auflisten
if ($action === 'getPaymentMethods') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt.']);
        exit;
    }
    $userId = (int)$_SESSION['user_id'];

    $stmt = mysqli_prepare($conn, "SELECT id, typ, inhaber, nummer FROM zahlungsmoeglichkeiten WHERE user_id = ? ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $methods = [];
    while ($row = mysqli_fetch_assoc($res)) {
        // Nummer maskieren
        $nummer = $row['nummer'];
        $masked = strlen($nummer) > 4 ? '•••• ' . substr($nummer, -4) : $nummer;
        $methods[] = [
            'id' => (int)$row['id'],
            'typ' => $row['typ'],
            'inhaber' => $row['inhaber'],
            'nummer_maskiert' => $masked
        ];
    }

    echo json_encode(['success' => true, 'methods' => $methods]);
    exit;
}

// Neue Zahlungsmöglichkeit hinzufügen
if ($action === 'addPaymentMethod') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt.']);
        exit;
    }
    $userId = (int)$_SESSION['user_id'];
    $typ = trim($_POST['typ'] ?? '');
    $inhaber = trim($_POST['inhaber'] ?? '');
    $nummer = trim($_POST['nummer'] ?? '');
    $pruefziffer = trim($_POST['pruefziffer'] ?? '');
    $gueltigBis = trim($_POST['gueltig_bis'] ?? '');
    $passwort = $_POST['passwort'] ?? '';


    $erlaubt = ['Kreditkarte', 'Bankeinzug', 'PayPal'];
    if (!in_array($typ, $erlaubt, true) || $inhaber === '' || $nummer === '' || $passwort === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte alle Felder korrekt ausfüllen.']);
        exit;
    }

    // Format prüfen
    $nummerClean = str_replace([' ', '-'], '', $nummer);
    if ($typ === 'Kreditkarte' && !preg_match('/^\d{16}$/', $nummerClean)) {
        echo json_encode(['success' => false, 'message' => 'Die Kartennummer muss aus 16 Ziffern bestehen.']);
        exit;
    }
    if ($typ === 'Kreditkarte' && !preg_match('/^\d{3}$/', $pruefziffer)) {
        echo json_encode(['success' => false, 'message' => 'Die Prüfziffer muss aus 3 Ziffern bestehen.']);
        exit;
    }
    if ($typ === 'Kreditkarte' && !preg_match('#^(0[1-9]|1[0-2])/\d{2}$#', $gueltigBis)) {
        echo json_encode(['success' => false, 'message' => 'Bitte ein gültiges Datum im Format MM/JJ angeben.']);
        exit;
    }
    // Nur bei Kreditkarte speichern
    if ($typ !== 'Kreditkarte') {
        $pruefziffer = null;
        $gueltigBis = null;
    }
    if ($typ === 'PayPal' && !filter_var($nummer, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Bitte eine gültige E-Mail Adresse angeben.']);
        exit;
    }
    if ($typ === 'Bankeinzug' && !preg_match('/^AT\d{18}$/', strtoupper($nummerClean))) {
        echo json_encode(['success' => false, 'message' => 'Bitte eine gültige IBAN angeben.']);
        exit;
    }

    // Passwort prüfen
    $pstmt = mysqli_prepare($conn, "SELECT passwort_hash FROM users WHERE id = ?");
    mysqli_stmt_bind_param($pstmt, "i", $userId);
    mysqli_stmt_execute($pstmt);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($pstmt));
    if (!$u || !password_verify($passwort, $u['passwort_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Das Passwort ist falsch.']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO zahlungsmoeglichkeiten (user_id, typ, inhaber, nummer, pruefziffer, gueltig_bis) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isssss", $userId, $typ, $inhaber, $nummer, $pruefziffer, $gueltigBis);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Speichern fehlgeschlagen.']);
    }
    exit;
}

// Zahlungsmöglichkeit löschen
if ($action === 'deletePaymentMethod') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt.']);
        exit;
    }
    $userId = (int)$_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);

    $stmt = mysqli_prepare($conn, "DELETE FROM zahlungsmoeglichkeiten WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $userId);
    mysqli_stmt_execute($stmt);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
