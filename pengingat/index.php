<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$activeNav = 'pengingat';
$pageTitle = 'Pengingat Perawatan Berkala';

$filterStatus = $_GET['status'] ?? 'semua';
$search = trim($_GET['q'] ?? '');

// Ambil servis terakhir setiap pelanggan untuk menghitung reminder 3 bulanan.
$sql = "
    SELECT 
        p.id AS pelanggan_id,
        p.nama AS nama_pelanggan,
        p.no_hp,
        p.alamat,
        s.id AS servis_id,
        s.jumlah_unit,
        s.detail_unit,
        s.jenis_servis,
        s.tanggal_servis AS tanggal_servis_terakhir,
        s.teknisi
    FROM pelanggan p
    LEFT JOIN (
        SELECT DISTINCT ON (pelanggan_id) id, pelanggan_id, jumlah_unit, detail_unit, jenis_servis, tanggal_servis, teknisi
        FROM servis
        ORDER BY pelanggan_id, tanggal_servis DESC, created_at DESC
    ) s ON p.id = s.pelanggan_id
    WHERE 1=1
";

$params = [];
if (!empty($search)) {
    $sql .= " AND (p.nama ILIKE :search OR s.detail_unit ILIKE :search OR p.no_hp ILIKE :search) ";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY p.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$items = [];
$counts = [
    'semua' => 0,
    'belum_pernah' => 0,
    'terlambat' => 0,
    'jatuh_tempo' => 0,
    'segera' => 0,
    'aman' => 0,
];

foreach ($rows as $row) {
    if (!empty($row['tanggal_servis_terakhir'])) {
        $statusInfo = hitungStatusReminder($row['tanggal_servis_terakhir']);
        $row['reminder'] = $statusInfo;
        
        $counts['semua']++;
        if (isset($counts[$statusInfo['status']])) {
            $counts[$statusInfo['status']]++;
        }

        if ($filterStatus === 'semua' || $filterStatus === $statusInfo['status']) {
            $items[] = $row;
        }
    } else {
        // Pelanggan baru tetap tampil sebagai status tersendiri agar tidak disamakan dengan keterlambatan.
        $row['reminder'] = [
            'status' => 'belum_pernah',
            'label'  => 'Belum Pernah Servis',
                'badge_class' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-500/10',
                'dot_class'   => 'bg-slate-400',
            'jadwal_berikutnya' => date('Y-m-d'),
            'sisa_hari' => -1,
        ];
        $counts['semua']++;
        $counts['belum_pernah']++;

        if ($filterStatus === 'semua' || $filterStatus === 'belum_pernah') {
            $items[] = $row;
        }
    }
}

// Urutkan berdasarkan yang paling mendesak (sisa_hari terkecil/negatif di atas)
usort($items, function($a, $b) {
    return ($a['reminder']['sisa_hari'] ?? 0) <=> ($b['reminder']['sisa_hari'] ?? 0);
});

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">Pengingat Servis 3 Bulanan</h2>
        <p class="text-sm text-slate-500 mt-0.5">Otomatisasi pemantauan jadwal servis berkala dan pengiriman notifikasi WhatsApp.</p>
    </div>
</div>

<!-- Status Filter Tabs -->
<div class="flex flex-wrap items-center gap-2 mb-6">
    <a href="<?= baseUrl('pengingat/?status=semua' . (!empty($search) ? '&q=' . urlencode($search) : '')) ?>" 
       class="px-4 py-2 rounded-lg text-xs font-semibold transition-colors <?= $filterStatus === 'semua' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' ?>">
        Semua Pelanggan (<?= $counts['semua'] ?>)
    </a>
    <a href="<?= baseUrl('pengingat/?status=belum_pernah' . (!empty($search) ? '&q=' . urlencode($search) : '')) ?>" 
       class="px-4 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 <?= $filterStatus === 'belum_pernah' ? 'bg-slate-700 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' ?>">
        Belum Pernah (<?= $counts['belum_pernah'] ?>)
    </a>
    <a href="<?= baseUrl('pengingat/?status=terlambat' . (!empty($search) ? '&q=' . urlencode($search) : '')) ?>" 
       class="px-4 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 <?= $filterStatus === 'terlambat' ? 'bg-red-700 text-white shadow-sm' : 'bg-white border border-slate-200 text-red-700 hover:bg-red-50' ?>">
        Terlambat (<?= $counts['terlambat'] ?>)
    </a>
    <a href="<?= baseUrl('pengingat/?status=jatuh_tempo' . (!empty($search) ? '&q=' . urlencode($search) : '')) ?>" 
       class="px-4 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 <?= $filterStatus === 'jatuh_tempo' ? 'bg-red-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-red-700 hover:bg-red-50' ?>">
        <span class="w-2 h-2 rounded-full <?= $filterStatus === 'jatuh_tempo' ? 'bg-white' : 'bg-red-500' ?>"></span>
        Jatuh Tempo (<?= $counts['jatuh_tempo'] ?>)
    </a>
    <a href="<?= baseUrl('pengingat/?status=segera' . (!empty($search) ? '&q=' . urlencode($search) : '')) ?>" 
       class="px-4 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 <?= $filterStatus === 'segera' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-amber-700 hover:bg-amber-50' ?>">
        <span class="w-2 h-2 rounded-full <?= $filterStatus === 'segera' ? 'bg-white' : 'bg-amber-500' ?>"></span>
        Segera (&le; 14 Hari) (<?= $counts['segera'] ?>)
    </a>
    <a href="<?= baseUrl('pengingat/?status=aman' . (!empty($search) ? '&q=' . urlencode($search) : '')) ?>" 
       class="px-4 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 <?= $filterStatus === 'aman' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-emerald-700 hover:bg-emerald-50' ?>">
        <span class="w-2 h-2 rounded-full <?= $filterStatus === 'aman' ? 'bg-white' : 'bg-emerald-500' ?>"></span>
        Aman (<?= $counts['aman'] ?>)
    </a>
</div>

<!-- Table Card -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="py-3.5 px-6">Pelanggan</th>
                            <th class="py-3.5 px-6">Pekerjaan Terakhir</th>
                    <th class="py-3.5 px-6">Servis Terakhir</th>
                    <th class="py-3.5 px-6">Jadwal Berikutnya</th>
                    <th class="py-3.5 px-6">Status</th>
                    <th class="py-3.5 px-6 text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" class="py-8 px-6 text-center text-slate-500">
                            Tidak ditemukan pelanggan yang sesuai dengan filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-6">
                                <a href="<?= baseUrl('pelanggan/detail.php?id=' . $item['pelanggan_id']) ?>" class="font-bold text-slate-900 hover:text-blue-600 transition-colors">
                                    <?= e($item['nama_pelanggan']) ?>
                                </a>
                                <span class="text-xs text-slate-500 font-mono block mt-0.5"><?= e($item['no_hp']) ?></span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-semibold text-slate-800"><?= e($item['jumlah_unit'] ? $item['jumlah_unit'] . ' unit AC' : '-') ?></span>
                                <span class="text-xs text-slate-400 block max-w-xs"><?= e($item['detail_unit'] ?: 'Belum ada riwayat servis') ?></span>
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-600 whitespace-nowrap">
                                <?= $item['tanggal_servis_terakhir'] ? formatTanggal($item['tanggal_servis_terakhir']) : 'Belum pernah' ?>
                            </td>
                            <td class="py-4 px-6 whitespace-nowrap">
                                <span class="text-sm font-semibold text-slate-900 block">
                                    <?= formatTanggal($item['reminder']['jadwal_berikutnya']) ?>
                                </span>
                                <span class="text-xs text-slate-500">
                                    <?php if ($item['reminder']['sisa_hari'] < 0): ?>
                                        <span class="text-red-600 font-medium">Terlewat <?= abs($item['reminder']['sisa_hari']) ?> hari</span>
                                    <?php elseif ($item['reminder']['sisa_hari'] === 0): ?>
                                        <span class="text-amber-600 font-semibold">Hari ini</span>
                                    <?php else: ?>
                                        <span><?= $item['reminder']['sisa_hari'] ?> hari lagi</span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $item['reminder']['badge_class'] ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $item['reminder']['dot_class'] ?>"></span>
                                    <?= e($item['reminder']['label']) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right whitespace-nowrap space-x-2">
                                <a href="<?= buatLinkWhatsApp($item['no_hp'], $item['nama_pelanggan'], $item['detail_unit'] ?: ($item['jumlah_unit'] . ' unit AC'), $item['tanggal_servis_terakhir'] ?? date('Y-m-d')) ?>"
                                   target="_blank" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.767-.775 5.92 5.92 0 011.026-3.13C4.246 15.65 3 13.93 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                    </svg>
                                    Kirim Pengingat
                                </a>
                                <a href="<?= baseUrl('pelanggan/detail.php?id=' . $item['pelanggan_id']) ?>" 
                                   class="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                                    Detail
                                </a>
                                <a href="<?= baseUrl('servis/tambah.php?pelanggan_id=' . $item['pelanggan_id']) ?>" 
                                   class="inline-flex items-center px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                                    Catat Servis
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
