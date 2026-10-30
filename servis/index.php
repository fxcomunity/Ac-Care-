<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$activeNav = 'servis';
$pageTitle = 'Riwayat Servis AC';

$search = trim($_GET['q'] ?? '');
$filterJenis = trim($_GET['jenis'] ?? '');

$sql = "
    SELECT 
        s.*,
        p.nama AS nama_pelanggan,
        p.no_hp
    FROM servis s
    JOIN pelanggan p ON s.pelanggan_id = p.id
    WHERE 1=1
";

$params = [];
if (!empty($search)) {
    $sql .= " AND (p.nama ILIKE :search OR s.detail_unit ILIKE :search OR s.teknisi ILIKE :search OR s.catatan ILIKE :search) ";
    $params['search'] = "%$search%";
}

if (!empty($filterJenis)) {
    $sql .= " AND s.jenis_servis = :jenis ";
    $params['jenis'] = $filterJenis;
}

$sql .= " ORDER BY s.tanggal_servis DESC, s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$servisList = $stmt->fetchAll();

// Total Pendapatan Tercatat
$totalBiaya = array_sum(array_column($servisList, 'biaya'));

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">Catatan Riwayat Servis</h2>
        <p class="text-sm text-slate-500 mt-0.5">Seluruh pekerjaan perawatan, cuci AC, dan perbaikan mesin.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= baseUrl('servis/tambah.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Catat Pekerjaan Baru
        </a>
    </div>
</div>

<!-- Filters & Summary -->
<div class="bg-white border border-slate-200 rounded-xl p-4 mb-6 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
    <form method="GET" action="<?= baseUrl('servis/') ?>" class="flex flex-wrap items-center gap-3 w-full md:w-auto flex-1">
        <div class="relative flex-1 min-w-[200px]">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari pelanggan, detail AC, teknisi..."
                   class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white transition-all">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
        </div>

        <select name="jenis" class="px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all">
            <option value="">Semua Jenis Servis</option>
            <?php if ($filterJenis !== ''): ?><option value="<?= e($filterJenis) ?>" selected><?= e($filterJenis) ?></option><?php endif; ?>
        </select>

        <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            Filter
        </button>
        <?php if (!empty($search) || !empty($filterJenis)): ?>
            <a href="<?= baseUrl('servis/') ?>" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                Reset
            </a>
        <?php endif; ?>
    </form>

    <div class="text-right flex-shrink-0 w-full md:w-auto border-t md:border-t-0 pt-2 md:pt-0">
        <span class="text-xs text-slate-500 block">Total Nilai Pekerjaan:</span>
        <span class="text-lg font-bold text-slate-900 font-mono"><?= formatRupiah($totalBiaya) ?></span>
    </div>
</div>

<!-- Table -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="py-3.5 px-6">Tanggal Servis</th>
                    <th class="py-3.5 px-6">Pelanggan</th>
                    <th class="py-3.5 px-6">Invoice AC</th>
                    <th class="py-3.5 px-6">Jenis Pekerjaan</th>
                    <th class="py-3.5 px-6">Teknisi</th>
                    <th class="py-3.5 px-6">Biaya</th>
                    <th class="py-3.5 px-6">Catatan Teknis</th>
                    <th class="py-3.5 px-6 text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($servisList)): ?>
                    <tr>
                        <td colspan="8" class="py-8 px-6 text-center text-slate-500">
                            Tidak ditemukan data pekerjaan servis yang sesuai kriteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($servisList as $s): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-6 font-semibold text-slate-900 whitespace-nowrap">
                                <?= formatTanggal($s['tanggal_servis']) ?>
                            </td>
                            <td class="py-4 px-6">
                                <a href="<?= baseUrl('pelanggan/detail.php?id=' . $s['pelanggan_id']) ?>" class="font-semibold text-blue-600 hover:underline">
                                    <?= e($s['nama_pelanggan']) ?>
                                </a>
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-medium text-slate-800"><?= e($s['jumlah_unit']) ?> unit AC</span>
                                <span class="text-xs text-slate-400 block"><?= e($s['detail_unit'] ?: '-') ?></span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
                                    <?= e($s['jenis_servis']) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-slate-600">
                                <?= e($s['teknisi']) ?>
                            </td>
                            <td class="py-4 px-6 font-semibold text-slate-900 font-mono whitespace-nowrap">
                                <?= formatRupiah($s['biaya']) ?>
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-500 max-w-xs truncate" title="<?= e($s['catatan'] ?? '') ?>">
                                <?= e($s['catatan'] ?: '-') ?>
                            </td>
                            <td class="py-4 px-6 text-right whitespace-nowrap">
                                <a href="<?= baseUrl('servis/invoice.php?id=' . $s['id']) ?>" class="text-xs text-blue-600 hover:text-blue-800 font-semibold mr-3">
                                    Invoice
                                </a>
                                <form method="POST" action="<?= baseUrl('servis/hapus.php') ?>" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus catatan servis ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= e($s['id']) ?>"><button type="submit" class="text-xs text-red-600 hover:text-red-800 font-semibold">Hapus</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
