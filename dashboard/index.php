<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$activeNav = 'dashboard';
$currentRole = $_SESSION['user_role'] ?? 'teknisi';
$pageTitle = $currentRole === 'owner' ? 'Dashboard Owner' : ($currentRole === 'admin' ? 'Dashboard Admin' : 'Dashboard Teknisi');

$totalPelanggan = (int)$pdo->query("SELECT COUNT(*) FROM pelanggan")->fetchColumn();

// Servis bulan berjalan
$servisBulanIni = (int)$pdo->query("
    SELECT COUNT(*) FROM servis 
    WHERE DATE_TRUNC('month', tanggal_servis) = DATE_TRUNC('month', CURRENT_DATE)
")->fetchColumn();

// Ambil pekerjaan servis terakhir per pelanggan untuk menghitung reminder 3 bulanan.
$queryReminder = "
    SELECT 
        s.id AS servis_id,
        s.jumlah_unit,
        s.detail_unit,
        s.jenis_servis,
        p.id AS pelanggan_id,
        p.nama AS nama_pelanggan,
        p.no_hp,
        s.tanggal_servis AS tanggal_servis_terakhir,
        s.teknisi
    FROM pelanggan p
    LEFT JOIN (
        SELECT DISTINCT ON (pelanggan_id) *
        FROM servis
        ORDER BY pelanggan_id, tanggal_servis DESC, created_at DESC
    ) s ON p.id = s.pelanggan_id
";

$stmtReminder = $pdo->query($queryReminder);
$allCustomers = $stmtReminder->fetchAll();

$perluServisList = [];
$totalJatuhTempo = 0;
$reminderHariIni = 0;

foreach ($allCustomers as $row) {
    if (!empty($row['tanggal_servis_terakhir'])) {
        $statusInfo = hitungStatusReminder($row['tanggal_servis_terakhir']);
        if (in_array($statusInfo['status'], ['terlambat', 'jatuh_tempo', 'segera'], true)) {
            $row['reminder'] = $statusInfo;
            $perluServisList[] = $row;
            if (in_array($statusInfo['status'], ['terlambat', 'jatuh_tempo'], true)) {
                $totalJatuhTempo++;
            }
            if ($statusInfo['status'] === 'jatuh_tempo') {
                $reminderHariIni++;
            }
        }
    }
}

// 5 Servis Terbaru
$stmtServisTerbaru = $pdo->query("
    SELECT 
        s.id,
        s.tanggal_servis,
        s.jenis_servis,
        s.teknisi,
        s.biaya,
        p.nama AS nama_pelanggan,
        s.jumlah_unit
    FROM servis s
    JOIN pelanggan p ON s.pelanggan_id = p.id
    ORDER BY s.tanggal_servis DESC, s.created_at DESC
    LIMIT 5
");
$servisTerbaru = $stmtServisTerbaru->fetchAll();

require_once __DIR__ . '/../includes/layout_top.php';
?>

<!-- Action Bar -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">Ringkasan Operasional</h2>
        <p class="text-sm text-slate-500 mt-0.5"><?= in_array($currentRole, ['owner', 'admin'], true) ? 'Kelola pelanggan, akun teknisi, unit, dan seluruh aktivitas operasional.' : 'Pantau unit yang perlu ditangani, jadwal pemeliharaan, dan riwayat pekerjaan.' ?></p>
    </div>
    <div class="flex items-center gap-3">
        <?php if ($currentRole === 'owner'): ?><a href="<?= baseUrl('pelanggan/tambah.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah Pelanggan
        </a><?php elseif ($currentRole === 'teknisi'): ?><a href="<?= baseUrl('servis/tambah.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg shadow-sm transition-colors">Buat Invoice</a><?php endif; ?>
        <?php if ($currentRole !== 'admin'): ?><a href="<?= baseUrl('servis/tambah.php') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Catat Servis
        </a><?php endif; ?>
    </div>
</div>

<!-- Metrics Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <!-- Card 1 -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Pelanggan</span>
            <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
            </span>
        </div>
        <a href="<?= baseUrl('pelanggan/') ?>" class="block text-3xl font-bold text-slate-900 mt-3 hover:text-blue-600"><?= number_format($totalPelanggan) ?></a>
        <p class="text-xs text-slate-500 mt-1">Pelanggan terdaftar</p>
    </div>

    <!-- Card 2 -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reminder Hari Ini</span>
            <span class="p-2 bg-slate-100 text-slate-700 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 3h13.5m-9.75 3h6m3.75-15H4.5A2.25 2.25 0 002.25 7.5v6.75A2.25 2.25 0 004.5 16.5h15a2.25 2.25 0 002.25-2.25V7.5A2.25 2.25 0 0019.5 5.25z" />
                </svg>
            </span>
        </div>
        <p class="text-3xl font-bold text-slate-900 mt-3"><?= number_format($reminderHariIni) ?></p>
        <p class="text-xs text-slate-500 mt-1">Pelanggan perlu ditindaklanjuti</p>
    </div>

    <!-- Card 3 -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Servis Bulan Ini</span>
            <span class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
            </span>
        </div>
        <p class="text-3xl font-bold text-slate-900 mt-3"><?= number_format($servisBulanIni) ?></p>
        <p class="text-xs text-slate-500 mt-1">Pekerjaan diselesaikan</p>
    </div>

    <!-- Card 4 -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Jatuh Tempo</span>
            <span class="p-2 bg-red-50 text-red-600 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </span>
        </div>
        <p class="text-3xl font-bold <?= $totalJatuhTempo > 0 ? 'text-red-600' : 'text-slate-900' ?> mt-3">
            <?= number_format($totalJatuhTempo) ?>
        </p>
        <p class="text-xs text-slate-500 mt-1">Perlu pengingat WhatsApp</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Kolom Kiri / 2 span: Pengingat Servis Mendesak -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Perhatian Pengingat Servis</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Pelanggan yang melewati atau mendekati jadwal servis 3 bulan.</p>
                </div>
                <a href="<?= baseUrl('pengingat/') ?>" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Lihat Semua</a>
            </div>

            <?php if (empty($perluServisList)): ?>
                <div class="p-8 text-center text-slate-500 text-sm">
                    Tidak ada unit yang jatuh tempo saat ini. Semua jadwal dalam kondisi aman.
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach (array_slice($perluServisList, 0, 5) as $item): ?>
                        <div class="p-4 sm:px-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50 transition-colors">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $item['reminder']['badge_class'] ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $item['reminder']['dot_class'] ?>"></span>
                                        <?= e($item['reminder']['label']) ?>
                                    </span>
                                    <h4 class="text-sm font-semibold text-slate-900 truncate"><?= e($item['nama_pelanggan']) ?></h4>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">
                                    Servis terakhir: <span class="font-medium text-slate-700"><?= formatTanggal($item['tanggal_servis_terakhir']) ?></span> &bull;
                                    <?= e($item['jumlah_unit']) ?> unit AC
                                </p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <a href="<?= buatLinkWhatsApp($item['no_hp'], $item['nama_pelanggan'], $item['detail_unit'] ?: ($item['jumlah_unit'] . ' unit AC'), $item['tanggal_servis_terakhir']) ?>"
                                   target="_blank" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.767-.775 5.92 5.92 0 011.026-3.13C4.246 15.65 3 13.93 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                    </svg>
                                    Kirim WhatsApp
                                </a>
                                <a href="<?= baseUrl('servis/tambah.php?pelanggan_id=' . $item['pelanggan_id']) ?>" 
                                   class="inline-flex items-center px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                                    Catat Servis
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kolom Kanan / 1 span: Riwayat Pekerjaan Terakhir -->
    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Servis Terkini</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Catatan riwayat pekerjaan terbaru.</p>
                </div>
                <a href="<?= baseUrl('servis/') ?>" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Semua</a>
            </div>

            <?php if (empty($servisTerbaru)): ?>
                <div class="p-8 text-center text-slate-500 text-sm">
                    Belum ada riwayat servis yang tercatat.
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($servisTerbaru as $s): ?>
                        <div class="p-4 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                                <span><?= formatTanggal($s['tanggal_servis']) ?></span>
                                <span class="font-semibold text-slate-700"><?= formatRupiah($s['biaya']) ?></span>
                            </div>
                            <p class="text-sm font-semibold text-slate-900 leading-snug">
                                <?= e($s['jenis_servis']) ?> &bull; <?= e($s['jumlah_unit']) ?> unit AC
                            </p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <?= e($s['nama_pelanggan']) ?> (Teknisi: <?= e($s['teknisi']) ?>)
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
