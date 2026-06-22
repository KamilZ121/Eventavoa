<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../config/dataHandler.php';
bootstrapApi();

$dataHandler = new DataHandler();
$conn = $dataHandler->getConnection();
$action = requestAction();
$userId = requireLogin();

if ($action === 'getPaymentMethods') {
    $stmt = $conn->prepare('SELECT id, typ, inhaber, nummer FROM zahlungsmoeglichkeiten WHERE user_id = ? ORDER BY id DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $methods = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $value = (string) $row['nummer'];
        $visible = mb_substr($value, -4);
        $methods[] = ['id' => (int) $row['id'], 'typ' => $row['typ'], 'inhaber' => $row['inhaber'], 'nummer_maskiert' => '•••• ' . $visible];
    }
    respond(['success' => true, 'methods' => $methods]);
}

if ($action === 'addPaymentMethod') {
    $typ = trim((string) ($_POST['typ'] ?? ''));
    $inhaber = trim((string) ($_POST['inhaber'] ?? ''));
    $nummer = trim((string) ($_POST['nummer'] ?? ''));
    $pruefziffer = trim((string) ($_POST['pruefziffer'] ?? ''));
    $gueltigBis = trim((string) ($_POST['gueltig_bis'] ?? ''));
    $passwort = (string) ($_POST['passwort'] ?? '');
    $nummerClean = strtoupper(str_replace([' ', '-'], '', $nummer));

    $valid = in_array($typ, ['Kreditkarte', 'Rechnung', 'PayPal'], true) && $inhaber !== '' && $passwort !== '';
    $valid = $valid && match ($typ) {
        'Kreditkarte' => preg_match('/^\d{16}$/', $nummerClean) && preg_match('/^\d{3}$/', $pruefziffer) && preg_match('#^(0[1-9]|1[0-2])/\d{2}$#', $gueltigBis),
        'Rechnung' => filter_var($nummer, FILTER_VALIDATE_EMAIL),
        'PayPal' => filter_var($nummer, FILTER_VALIDATE_EMAIL),
        default => false,
    };
    if (!$valid) {
        respond(['success' => false, 'message' => 'Bitte gültige Zahlungsdaten angeben.'], 422);
    }

    $check = $conn->prepare('SELECT passwort_hash FROM users WHERE id = ? AND aktiv = 1');
    $check->bind_param('i', $userId);
    $check->execute();
    $user = $check->get_result()->fetch_assoc();
    if (!$user || !password_verify($passwort, $user['passwort_hash'])) {
        respond(['success' => false, 'message' => 'Das Passwort ist falsch.'], 403);
    }

    // CVV wird aus Sicherheitsgründen validiert, aber nicht gespeichert.
    $storedNumber = in_array($typ, ['PayPal', 'Rechnung'], true) ? $nummer : $nummerClean;
    $emptyCvv = null;
    $expiry = $typ === 'Kreditkarte' ? $gueltigBis : null;
    $stmt = $conn->prepare('INSERT INTO zahlungsmoeglichkeiten (user_id, typ, inhaber, nummer, pruefziffer, gueltig_bis) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssss', $userId, $typ, $inhaber, $storedNumber, $emptyCvv, $expiry);
    $stmt->execute();
    respond(['success' => true, 'id' => $conn->insert_id]);
}

if ($action === 'deletePaymentMethod') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM zahlungsmoeglichkeiten WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    respond(['success' => true]);
}

respond(['success' => false, 'message' => 'Ungültige Aktion.'], 400);
