<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('produk/'); }
verifyCsrf($_POST['csrf_token'] ?? null);
$id = trim($_POST['id'] ?? '');
$stmt = $pdo->prepare('DELETE FROM produk WHERE id = :id');
$stmt->execute(['id' => $id]);
setFlash('success', 'Produk berhasil dihapus.');
redirect('produk/');