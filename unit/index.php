<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);
redirect('servis/');

$activeNav = 'unit';
$pageTitle = 'Data Unit AC';

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT 
        u.*,
        p.nama AS nama_pelanggan,
        p.no_hp,
        s.tanggal_servis AS tanggal_servis_terakhir
    FROM unit_ac u
    JOIN pelanggan p ON u.pelanggan_id = p.id
    LEFT JOIN (
        SELECT DISTINCT ON (unit_ac_id) unit_ac_id, tanggal_servis
        FROM servis
        ORDER BY unit_ac_id, tanggal_servis DESC
    ) s ON u.id = s.unit_ac_id
";

$params = [];
if (!empty($search)) {
    $sql .= " WHERE u.nama_unit ILIKE :search OR u.merk ILIKE :search OR p.nama ILIKE :search OR u.nomor_seri ILIKE :search ";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY p.nama ASC, u.nama_unit ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$unitList = $stmt->fetchAll();

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">Daftar Unit AC</h2>
        <p class="text-sm text-slate-500 mt-0.5">Seluruh unit mesin pendingin yang terdaftar dalam sistem.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= baseUrl('unit/tambah.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah Unit AC
        </a>
    </div>
</div>

<!-- Search -->
<div class="bg-white border border-slate-200 rounded-xl p-4 mb-6 shadow-sm">
    <form method="GET" action="<?= baseUrl('unit/') ?>" class="flex gap-3">
        <div class="relative flex-1">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama unit, merk, nomor seri, atau nama pemilik..."
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white transition-all">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            Cari
        </button>
        <?php if (!empty($search)): ?>
            <a href="<?= baseUrl('unit/') ?>" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors">
                Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="py-3.5 px-6">Nama Unit</th>
                    <th class="py-3.5 px-6">Pemilik (Pelanggan)</th>
                    <th class="py-3.5 px-6">Merk &amp; Kapasitas</th>
                    <th class="py-3.5 px-6">Servis Terakhir</th>
                    <th class="py-3.5 px-6">Status Jadwal</th>
                    <th class="py-3.5 px-6 text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($unitList)): ?>
                    <tr>
                        <td colspan="6" class="py-8 px-6 text-center text-slate-500">
                            Tidak ada unit AC yang terdaftar.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($unitList as $u): ?>
                        <?php
                        $reminder = !empty($u['tanggal_servis_terakhir']) 
                            ? hitungStatusReminder($u['tanggal_servis_terakhir']) 
                            : null;
                        ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-6">
                                <span class="font-bold text-slate-900 block"><?= e($u['nama_unit']) ?></span>
                                <span class="text-xs text-slate-400 font-mono"><?= e($u['nomor_seri'] ?: 'Tanpa S/N') ?></span>
                            </td>
                            <td class="py-4 px-6">
                                <a href="<?= baseUrl('pelanggan/detail.php?id=' . $u['pelanggan_id']) ?>" class="font-semibold text-blue-600 hover:underline">
                                    <?= e($u['nama_pelanggan']) ?>
                                </a>
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-medium text-slate-800"><?= e($u['merk']) ?></span>
                                <span class="text-xs text-slate-500 block"><?= e($u['jenis']) ?> &bull; <?= e($u['kapasitas']) ?></span>
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-600 whitespace-nowrap">
                                <?= formatTanggal($u['tanggal_servis_terakhir']) ?>
                            </td>
                            <td class="py-4 px-6">
                                <?php if ($reminder): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $reminder['badge_class'] ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $reminder['dot_class'] ?>"></span>
                                        <?= e($reminder['label']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Belum Servis</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-right space-x-2 whitespace-nowrap">
                                <a href="<?= baseUrl('servis/tambah.php?unit_id=' . $u['id']) ?>" class="inline-flex items-center px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-md transition-colors shadow-sm">
                                    Catat Servis
                                </a>
                                <a href="<?= baseUrl('unit/edit.php?id=' . $u['id']) ?>" class="inline-flex items-center px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-md transition-colors">
                                    Edit
                                </a>
                                <a href="<?= baseUrl('unit/hapus.php?id=' . $u['id']) ?>" onclick="return confirm('Hapus data unit AC ini?')" class="inline-flex items-center px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold rounded-md transition-colors">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
