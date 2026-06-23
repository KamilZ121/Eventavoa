<?php
declare(strict_types=1);

/** Gemeinsame JSON- und Session-Basis für alle API-Endpunkte. */
function bootstrapApi(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
}

function requestMethod(): string
{
    return trim((string) ($_REQUEST['method'] ?? ''));
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireLogin(): int
{
    if (empty($_SESSION['user_id'])) {
        respond(['success' => false, 'needsLogin' => true, 'message' => 'Bitte zuerst einloggen.'], 401);
    }
    return (int) $_SESSION['user_id'];
}

function requireAdmin(): int
{
    $userId = requireLogin();
    if (($_SESSION['rolle'] ?? '') !== 'admin') {
        respond(['success' => false, 'message' => 'Keine Berechtigung.'], 403);
    }
    return $userId;
}
