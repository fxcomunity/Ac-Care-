<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$activeNav = 'produk';
$pageTitle = 'Produk & Layanan';
$search = trim($_GET['q'] ?? '');
$sql = 'SELECT p.*, u.nama AS pembuat FROM produk p LEFT JOIN users u ON u.id = p.created_by';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE p.nama ILIKE :search OR p.kategori ILIKE :search OR p.maintenance ILIKE :search';
    $params['search'] = '%' . $search . '%';
}
$sql .= ' ORDER BY p.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
require_once __DIR__ . '/../includes/layout_top.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6"><div><h2 class="text-xl font-bold text-slate-900">Produk &amp; Maintenance</h2><p class="text-sm text-slate-500 mt-1">Kelola katalog produk dan informasi perawatan untuk ditampilkan di Home.</p></div><a href="<?= baseUrl('produk/tambah.php') ?>" class="px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">+ Tambah Produk</a></div>
<div class="bg-white border border-slate-200 rounded-xl p-4 mb-6 shadow-sm"><form method="GET" class="flex gap-3"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari produk, kategori, maintenance..." class="flex-1 px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"><button class="px-4 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-semibold">Cari</button></form></div>
<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
<?php foreach ($products as $product): ?>
    <article class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <?php if ($product['gambar_url']): ?><img src="<?= e($product['gambar_url']) ?>" alt="<?= e($product['nama']) ?>" class="w-full h-40 object-cover"><?php else: ?><div class="h-40 bg-slate-100 flex items-center justify-center text-4xl font-black text-blue-600">AC</div><?php endif; ?>
        <div class="p-5"><div class="flex items-start justify-between gap-3"><div><span class="text-xs font-semibold uppercase tracking-wider text-blue-600"><?= e($product['kategori']) ?></span><h3 class="mt-1 text-lg font-bold text-slate-900"><?= e($product['nama']) ?></h3></div><span class="text-xs font-semibold px-2 py-1 rounded-full <?= $product['aktif'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= $product['aktif'] ? 'Tampil' : 'Draft' ?></span></div><p class="mt-3 text-sm text-slate-600 line-clamp-3"><?= e($product['deskripsi'] ?: '-') ?></p><div class="mt-4 pt-4 border-t border-slate-100"><p class="text-xs text-slate-500">Maintenance</p><p class="mt-1 text-sm text-slate-700"><?= e($product['maintenance'] ?: '-') ?></p><p class="mt-3 font-bold text-slate-900"><?= (float)$product['harga'] > 0 ? formatRupiah($product['harga']) : 'Hubungi kami' ?></p></div><div class="mt-5 flex gap-2"><a href="<?= baseUrl('produk/edit.php?id=' . $product['id']) ?>" class="flex-1 text-center px-3 py-2 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">Edit</a><form method="POST" action="<?= baseUrl('produk/hapus.php') ?>" onsubmit="return confirm('Hapus produk ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= e($product['id']) ?>"><button class="px-3 py-2 rounded-lg bg-red-50 text-red-700 text-xs font-semibold">Hapus</button></form></div></div>
    </article>
<?php endforeach; ?>
</div>
<?php if (!$products): ?><div class="bg-white border border-slate-200 rounded-xl p-10 text-center text-sm text-slate-500">Belum ada produk. Tambahkan produk pertama dari tombol di atas.</div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>