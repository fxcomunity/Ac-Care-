<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$id = $_GET['id'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM produk WHERE id = :id');
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();
if (!$product) { setFlash('error', 'Produk tidak ditemukan.'); redirect('produk/'); }
$activeNav = 'produk';
$pageTitle = 'Edit Produk';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf_token'] ?? null);
        $nama = validateText($_POST['nama'] ?? '', 150, 'Nama produk');
        $kategori = validateText($_POST['kategori'] ?? '', 80, 'Kategori');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $maintenance = trim($_POST['maintenance'] ?? '');
        $gambarUrl = trim($_POST['gambar_url'] ?? '');
        $harga = filter_var($_POST['harga'] ?? 0, FILTER_VALIDATE_FLOAT);
        if ($harga === false || $harga < 0 || mb_strlen($deskripsi) > 2000 || mb_strlen($maintenance) > 2000 || ($gambarUrl !== '' && !filter_var($gambarUrl, FILTER_VALIDATE_URL))) { throw new InvalidArgumentException('Periksa harga, deskripsi, maintenance, dan URL gambar.'); }
        $stmt = $pdo->prepare('UPDATE produk SET nama = :nama, kategori = :kategori, deskripsi = :deskripsi, harga = :harga, maintenance = :maintenance, gambar_url = :gambar_url, aktif = :aktif, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute(['nama' => $nama, 'kategori' => $kategori, 'deskripsi' => $deskripsi ?: null, 'harga' => $harga, 'maintenance' => $maintenance ?: null, 'gambar_url' => $gambarUrl ?: null, 'aktif' => isset($_POST['aktif']), 'id' => $id]);
        setFlash('success', 'Produk berhasil diperbarui.'); redirect('produk/');
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); } catch (PDOException $e) { $error = 'Produk gagal diperbarui.'; }
}
$data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $product;
require_once __DIR__ . '/../includes/layout_top.php';
?>
<div class="max-w-3xl mx-auto"><div class="mb-6"><h2 class="text-xl font-bold text-slate-900">Edit Produk &amp; Maintenance</h2><p class="text-sm text-slate-500 mt-1">Perbarui informasi yang tampil di katalog.</p></div><?php if ($error): ?><div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?><div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm"><form method="POST" class="space-y-5"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><div class="grid sm:grid-cols-2 gap-5"><div><label class="block text-sm font-semibold mb-1.5">Nama produk / layanan</label><input name="nama" maxlength="150" required value="<?= e($data['nama'] ?? '') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div><div><label class="block text-sm font-semibold mb-1.5">Kategori</label><input name="kategori" maxlength="80" required value="<?= e($data['kategori'] ?? '') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div></div><div><label class="block text-sm font-semibold mb-1.5">Deskripsi</label><textarea name="deskripsi" maxlength="2000" rows="4" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"><?= e($data['deskripsi'] ?? '') ?></textarea></div><div><label class="block text-sm font-semibold mb-1.5">Informasi maintenance</label><textarea name="maintenance" maxlength="2000" rows="4" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"><?= e($data['maintenance'] ?? '') ?></textarea></div><div class="grid sm:grid-cols-2 gap-5"><div><label class="block text-sm font-semibold mb-1.5">Harga</label><input name="harga" type="number" min="0" step="1000" value="<?= e($data['harga'] ?? '0') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div><div><label class="block text-sm font-semibold mb-1.5">URL gambar</label><input name="gambar_url" type="url" value="<?= e($data['gambar_url'] ?? '') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div></div><label class="inline-flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="aktif" value="1" <?= isset($data['aktif']) && filter_var($data['aktif'], FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?>> Tampilkan di Home publik</label><div class="pt-4 border-t border-slate-100 flex justify-end gap-3"><a href="<?= baseUrl('produk/') ?>" class="px-4 py-2.5 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold">Batal</a><button class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold">Simpan Perubahan</button></div></form></div></div>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>