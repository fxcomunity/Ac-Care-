<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$activeNav = 'pelanggan';
$pageTitle = 'Data Pelanggan';

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT 
        p.*,
        COUNT(s.id) AS total_servis,
        MAX(s.tanggal_servis) AS servis_terakhir
    FROM pelanggan p
    LEFT JOIN servis s ON p.id = s.pelanggan_id
";

$params = [];
if (!empty($search)) {
    $sql .= " WHERE p.nama ILIKE :search OR p.no_hp ILIKE :search OR p.alamat ILIKE :search ";
    $params['search'] = "%$search%";
}

$sql .= " GROUP BY p.id ORDER BY p.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pelangganList = $stmt->fetchAll();

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">Daftar Pelanggan</h2>
        <p class="text-sm text-slate-500 mt-0.5">Kelola identitas pemilik AC dan nomor kontak pemeliharaan.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= baseUrl('pelanggan/tambah.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah Pelanggan Baru
        </a>
    </div>
</div>

<!-- Search Bar -->
<div class="bg-white border border-slate-200 rounded-xl p-4 mb-6 shadow-sm">
    <form method="GET" action="<?= baseUrl('pelanggan/') ?>" class="flex gap-3">
        <div class="relative flex-1">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama, nomor HP, atau alamat pelanggan..."
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white transition-all">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            Cari
        </button>
        <?php if (!empty($search)): ?>
            <a href="<?= baseUrl('pelanggan/') ?>" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Data Table -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="py-3.5 px-6">Nama Pelanggan</th>
                    <th class="py-3.5 px-6">Nomor Telepon</th>
                    <th class="py-3.5 px-6">Alamat</th>
                    <th class="py-3.5 px-6">Servis Terakhir</th>
                    <th class="py-3.5 px-6 text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($pelangganList)): ?>
                    <tr>
                        <td colspan="5" class="py-8 px-6 text-center text-slate-500">
                            Tidak ditemukan data pelanggan yang sesuai.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pelangganList as $p): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-6 font-semibold text-slate-900">
                                <a href="<?= baseUrl('pelanggan/detail.php?id=' . $p['id']) ?>" class="hover:text-blue-600 transition-colors">
                                    <?= e($p['nama']) ?>
                                </a>
                            </td>
                            <td class="py-4 px-6 font-mono text-slate-600">
                                <?= e($p['no_hp']) ?>
                            </td>
                            <td class="py-4 px-6 text-slate-600 max-w-xs truncate" title="<?= e($p['alamat']) ?>">
                                <?= e($p['alamat']) ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                    <?= $p['servis_terakhir'] ? formatTanggal($p['servis_terakhir']) : 'Belum ada servis' ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <a href="<?= baseUrl('pelanggan/detail.php?id=' . $p['id']) ?>" class="inline-flex items-center px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-md transition-colors">
                                    Detail
                                </a>
                                <a href="<?= baseUrl('pelanggan/edit.php?id=' . $p['id']) ?>" class="inline-flex items-center px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold rounded-md transition-colors">
                                    Edit
                                </a>
                                <form method="POST" action="<?= baseUrl('pelanggan/hapus.php') ?>" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelanggan ini? Semua riwayat servis terkait akan ikut terhapus.')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><button type="submit" class="inline-flex items-center px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold rounded-md transition-colors">Hapus</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
