<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
sendSecurityHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}
verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
$data = json_decode(file_get_contents('php://input'), true);
$endpoint = trim((string)($data['endpoint'] ?? ''));
if ($endpoint === '' || strlen($endpoint) > 2048 || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Subscription tidak valid.']);
    exit;
}
$stmt = $pdo->prepare('INSERT INTO push_subscriptions (user_id, endpoint, subscription_json) VALUES (:user_id, :endpoint, CAST(:payload AS jsonb)) ON CONFLICT (endpoint) DO UPDATE SET user_id = EXCLUDED.user_id, subscription_json = EXCLUDED.subscription_json, updated_at = CURRENT_TIMESTAMP');
$stmt->execute(['user_id' => $_SESSION['user_id'], 'endpoint' => $endpoint, 'payload' => json_encode($data)]);
echo json_encode(['success' => true]);