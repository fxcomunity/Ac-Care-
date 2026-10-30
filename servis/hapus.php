<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$id = trim($_POST['id'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method tidak diizinkan.');
}
verifyCsrf($_POST['csrf_token'] ?? null);
if (!empty($id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM servis WHERE id = :id");
        $stmt->execute(['id' => $id]);
        auditLog('service_deleted', $id, 'Catatan servis dihapus.');
        setFlash('success', 'Catatan servis berhasil dihapus.');
    } catch (Exception $e) {
        error_log('AC Care service delete failed: ' . $e->getMessage());
        setFlash('error', 'Catatan servis gagal dihapus.');
    }
}

redirect('servis/');
