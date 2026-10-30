<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin']);

$id = trim($_POST['id'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method tidak diizinkan.');
}
verifyCsrf($_POST['csrf_token'] ?? null);
if (!empty($id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM pelanggan WHERE id = :id");
        $stmt->execute(['id' => $id]);
        auditLog('customer_deleted', $id, 'Data pelanggan dihapus.');
        setFlash('success', 'Data pelanggan dan semua data terkait berhasil dihapus.');
    } catch (Exception $e) {
        error_log('AC Care customer delete failed: ' . $e->getMessage());
        setFlash('error', 'Data pelanggan gagal dihapus.');
    }
}

redirect('pelanggan/');
