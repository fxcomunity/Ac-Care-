<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);
redirect('servis/');

$id = $_GET['id'] ?? '';
if (!empty($id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM unit_ac WHERE id = :id");
        $stmt->execute(['id' => $id]);
        setFlash('success', 'Unit AC dan riwayat servis terkait berhasil dihapus.');
    } catch (Exception $e) {
        setFlash('error', 'Gagal menghapus unit: ' . $e->getMessage());
    }
}

redirect('unit/');
