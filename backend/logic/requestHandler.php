<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/dataHandler.php';

$action = $_POST['action'] ?? '';
$db = new DataHandler();

switch ($action) {

    case 'login':
        $input = $_POST['benutzername'] ?? '';
        $passwort = $_POST['passwort'] ?? '';

        // Suche per Username oder Email
        $user = $db->getUserByUsername($input) ?? $db->getUserByEmail($input);

        if ($user && password_verify($passwort, $user['passwort'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['rolle'] = $user['rolle'];
            $_SESSION['benutzername'] = $user['benutzername'];

            // Cookie setzen wenn gewünscht
            if (!empty($_POST['remember'])) {
                setcookie('remember_user', $user['benutzername'], time() + (86400 * 30), '/');
            }

            echo json_encode(['success' => true, 'rolle' => $user['rolle']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Benutzername oder Passwort falsch']);
        }
        break;

    case 'register':
        $required = ['vorname', 'nachname', 'adresse', 'plz', 'ort', 'email', 'benutzername', 'passwort'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => 'Alle Felder ausfüllen']);
                exit;
            }
        }

        if ($_POST['passwort'] !== $_POST['passwort2']) {
            echo json_encode(['success' => false, 'message' => 'Passwörter stimmen nicht überein']);
            exit;
        }

        if ($db->usernameExists($_POST['benutzername'])) {
            echo json_encode(['success' => false, 'message' => 'Benutzername bereits vergeben']);
            exit;
        }

        if ($db->emailExists($_POST['email'])) {
            echo json_encode(['success' => false, 'message' => 'Email bereits registriert']);
            exit;
        }

        $data = [
            'anrede'       => $_POST['anrede'] ?? '',
            'vorname'      => $_POST['vorname'],
            'nachname'     => $_POST['nachname'],
            'adresse'      => $_POST['adresse'],
            'plz'          => $_POST['plz'],
            'ort'          => $_POST['ort'],
            'email'        => $_POST['email'],
            'benutzername' => $_POST['benutzername'],
            'passwort'     => password_hash($_POST['passwort'], PASSWORD_BCRYPT)
        ];

        if ($db->createUser($data)) {
            echo json_encode(['success' => true, 'message' => 'Registrierung erfolgreich']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler bei der Registrierung']);
        }
        break;

    case 'logout':
        session_destroy();
        setcookie('remember_user', '', time() - 3600, '/');
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
}