<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../config/dataHandler.php';
bootstrapApi();

$dataHandler = new DataHandler();
$conn = $dataHandler->getConnection();
restoreRememberedLogin($dataHandler);
$action = requestAction();

if ($action === 'register') {
    $fields = [];
    foreach (['anrede', 'vorname', 'nachname', 'adresse', 'plz', 'ort', 'email', 'benutzername'] as $name) {
        $fields[$name] = trim((string) ($_POST[$name] ?? ''));
    }
    $password = (string) ($_POST['passwort'] ?? '');
    $passwordRepeat = (string) ($_POST['passwort2'] ?? '');
    $paymentType = trim((string) ($_POST['payment_type'] ?? ''));
    $paymentOwner = trim((string) ($_POST['payment_owner'] ?? ($fields['vorname'] . ' ' . $fields['nachname'])));
    $paymentIdentifier = trim((string) ($_POST['payment_identifier'] ?? ''));

    if (in_array('', $fields, true) || $password === '' || $paymentType === '' || $paymentIdentifier === '') {
        respond(['success' => false, 'message' => 'Bitte alle Pflichtfelder ausfüllen.'], 422);
    }
    if (!in_array($fields['anrede'], ['Herr', 'Frau', 'Divers'], true)
        || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)
        || !preg_match('/^\d{4}$/', $fields['plz'])
        || mb_strlen($fields['benutzername']) < 3
        || mb_strlen($password) < 8) {
        respond(['success' => false, 'message' => 'Bitte gültige Daten angeben. Das Passwort benötigt mindestens 8 Zeichen.'], 422);
    }
    if ($password !== $passwordRepeat) {
        respond(['success' => false, 'message' => 'Die Passwörter stimmen nicht überein.'], 422);
    }
    $typeMap = ['paypal' => 'PayPal', 'kreditkarte' => 'Kreditkarte', 'rechnung' => 'Rechnung'];
    if (!isset($typeMap[$paymentType])) {
        respond(['success' => false, 'message' => 'Bitte eine gültige Zahlungsart auswählen.'], 422);
    }
    $paymentType = $typeMap[$paymentType];
    $cleanIdentifier = strtoupper(str_replace([' ', '-'], '', $paymentIdentifier));
    $paymentValid = match ($paymentType) {
        'PayPal' => filter_var($paymentIdentifier, FILTER_VALIDATE_EMAIL),
        'Kreditkarte' => preg_match('/^\d{16}$/', $cleanIdentifier),
        'Rechnung' => filter_var($paymentIdentifier, FILTER_VALIDATE_EMAIL),
    };
    if (!$paymentValid) {
        respond(['success' => false, 'message' => 'Die Zahlungsinformation ist ungültig.'], 422);
    }

    if ($dataHandler->userExists($fields['email'], $fields['benutzername'])) {
        respond(['success' => false, 'message' => 'E-Mail oder Benutzername ist bereits vergeben.'], 409);
    }

    $conn->begin_transaction();
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (anrede, vorname, nachname, email, benutzername, passwort_hash) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $fields['anrede'], $fields['vorname'], $fields['nachname'], $fields['email'], $fields['benutzername'], $hash);
        $stmt->execute();
        $userId = $conn->insert_id;
        $address = $conn->prepare("INSERT INTO addresses (user_id, address_type, strasse, plz, ort, is_default) VALUES (?, 'shipping', ?, ?, ?, 1)");
        $address->bind_param('isss', $userId, $fields['adresse'], $fields['plz'], $fields['ort']);
        $address->execute();
        $storedIdentifier = in_array($paymentType, ['PayPal', 'Rechnung'], true) ? $paymentIdentifier : $cleanIdentifier;
        $payment = $conn->prepare('INSERT INTO zahlungsmoeglichkeiten (user_id, typ, inhaber, nummer) VALUES (?, ?, ?, ?)');
        $payment->bind_param('isss', $userId, $paymentType, $paymentOwner, $storedIdentifier);
        $payment->execute();
        $conn->commit();
        respond(['success' => true, 'message' => 'Registrierung erfolgreich.']);
    } catch (Throwable $exception) {
        $conn->rollback();
        respond(['success' => false, 'message' => 'Registrierung konnte nicht gespeichert werden.'], 500);
    }
}
if ($action === 'login') {
    $identifier = trim((string) ($_POST['benutzername'] ?? ''));
    $password = (string) ($_POST['passwort'] ?? '');
    $user = $dataHandler->getUserForLogin($identifier);
    if (!$user || !(bool) $user['aktiv'] || !password_verify($password, $user['passwort_hash'])) {
        respond(['success' => false, 'message' => 'Anmeldedaten ungültig oder Konto deaktiviert.'], 401);
    }
    session_regenerate_id(true);
    setSessionUser(new User($user));
    if (($_POST['remember'] ?? '') === '1') {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $userId = (int) $user['id'];
        $update = $conn->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
        $update->bind_param('si', $hash, $userId);
        $update->execute();
        setcookie('remember', $userId . ':' . $token, ['expires' => time() + 2592000, 'path' => '/eventavoa', 'httponly' => true, 'samesite' => 'Lax']);
    }
    respond(['success' => true, 'rolle' => $user['rolle']]);
}

if ($action === 'status') {
    respond(empty($_SESSION['user_id'])
        ? ['success' => true, 'loggedIn' => false]
        : ['success' => true, 'loggedIn' => true, 'benutzername' => $_SESSION['benutzername'], 'rolle' => $_SESSION['rolle']]);
}

if ($action === 'logout') {
    if (!empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
        $stmt = $conn->prepare('UPDATE users SET remember_token = NULL WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }
    setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/eventavoa']);
    session_unset();
    session_destroy();
    respond(['success' => true]);
}

if ($action === 'getProfile') {
    $userId = requireLogin();
    respond(['success' => true, 'user' => $dataHandler->getProfile($userId)]);
}

if ($action === 'updateProfile') {
    $userId = requireLogin();
    $values = [];
    foreach (['anrede', 'vorname', 'nachname', 'email', 'benutzername', 'adresse', 'plz', 'ort'] as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $password = (string) ($_POST['passwort'] ?? '');
    if (in_array('', $values, true) || !filter_var($values['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{4}$/', $values['plz'])) {
        respond(['success' => false, 'message' => 'Bitte gültige und vollständige Daten angeben.'], 422);
    }
    $check = $conn->prepare('SELECT passwort_hash FROM users WHERE id = ?');
    $check->bind_param('i', $userId);
    $check->execute();
    $user = $check->get_result()->fetch_assoc();
    if (!$user || !password_verify($password, $user['passwort_hash'])) {
        respond(['success' => false, 'message' => 'Das Passwort ist falsch.'], 403);
    }
    if ($dataHandler->userExists($values['email'], $values['benutzername'], $userId)) {
        respond(['success' => false, 'message' => 'E-Mail oder Benutzername ist bereits vergeben.'], 409);
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('UPDATE users SET anrede = ?, vorname = ?, nachname = ?, email = ?, benutzername = ? WHERE id = ?');
        $stmt->bind_param('sssssi', $values['anrede'], $values['vorname'], $values['nachname'], $values['email'], $values['benutzername'], $userId);
        $stmt->execute();
        $address = $conn->prepare("INSERT INTO addresses (user_id, address_type, strasse, plz, ort, is_default)
                                   VALUES (?, 'shipping', ?, ?, ?, 1)
                                   ON DUPLICATE KEY UPDATE strasse = VALUES(strasse), plz = VALUES(plz), ort = VALUES(ort)");
        // Der Dump garantiert den Unique-Key noch nicht: bestehende Adresse daher separat aktualisieren.
        $find = $conn->prepare("SELECT id FROM addresses WHERE user_id = ? AND address_type = 'shipping' ORDER BY is_default DESC, id LIMIT 1");
        $find->bind_param('i', $userId);
        $find->execute();
        $existing = $find->get_result()->fetch_assoc();
        if ($existing) {
            $address = $conn->prepare('UPDATE addresses SET strasse = ?, plz = ?, ort = ?, is_default = 1 WHERE id = ?');
            $addressId = (int) $existing['id'];
            $address->bind_param('sssi', $values['adresse'], $values['plz'], $values['ort'], $addressId);
        } else {
            $address = $conn->prepare("INSERT INTO addresses (user_id, address_type, strasse, plz, ort, is_default) VALUES (?, 'shipping', ?, ?, ?, 1)");
            $address->bind_param('isss', $userId, $values['adresse'], $values['plz'], $values['ort']);
        }
        $address->execute();
        $conn->commit();
        $_SESSION['benutzername'] = $values['benutzername'];
        respond(['success' => true]);
    } catch (Throwable $exception) {
        $conn->rollback();
        respond(['success' => false, 'message' => 'Daten konnten nicht gespeichert werden.'], 500);
    }
}

respond(['success' => false, 'message' => 'Ungültige Aktion.'], 400);

function setSessionUser(User $user): void
{
    $_SESSION['user_id'] = $user->id;
    $_SESSION['benutzername'] = $user->benutzername;
    $_SESSION['rolle'] = $user->rolle;
}

function restoreRememberedLogin($dataHandler)
{
    if (!empty($_SESSION['user_id']) || empty($_COOKIE['remember'])) return;
    $parts = explode(':', (string) $_COOKIE['remember'], 2);
    if (count($parts) !== 2) return;
    $userId = (int) $parts[0];
    $hash = hash('sha256', $parts[1]);
    $user = $dataHandler->getRememberedUser($userId);
    if ($user && $user['remember_token'] && hash_equals($user['remember_token'], $hash)) setSessionUser(new User($user));
}
