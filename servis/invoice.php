<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$id = $_GET['id'] ?? '';
$stmt = $pdo->prepare("
    SELECT s.*, p.nama AS nama_pelanggan, p.no_hp, p.alamat
    FROM servis s
    JOIN pelanggan p ON s.pelanggan_id = p.id
    WHERE s.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlash('error', 'Invoice tidak ditemukan.');
    redirect('servis/');
}

$pageTitle = 'Invoice Servis';
require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6 print:hidden">
        <a href="<?= baseUrl('servis/') ?>" class="text-sm font-semibold text-slate-600 hover:text-slate-900">&larr; Kembali ke riwayat</a>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold">Cetak Invoice</button>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 sm:p-10 shadow-sm print:border-0 print:shadow-none">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-5 border-b border-slate-200 pb-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-blue-600"><?= e(websiteSettings()['site_name']) ?></p>
                <h2 class="text-2xl font-bold text-slate-900 mt-1">Invoice Servis AC</h2>
                <p class="text-sm text-slate-500 mt-1">No. <?= e(strtoupper(substr($invoice['id'], 0, 8))) ?></p>
            </div>
            <div class="sm:text-right text-sm text-slate-600">
                <p><span class="font-semibold">Tanggal:</span> <?= formatTanggal($invoice['tanggal_servis']) ?></p>
                <p><span class="font-semibold">Teknisi:</span> <?= e($invoice['teknisi']) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 py-6 border-b border-slate-200">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Pelanggan</p>
                <p class="font-bold text-slate-900"><?= e($invoice['nama_pelanggan']) ?></p>
                <p class="text-sm text-slate-600 mt-1">WhatsApp: <?= e($invoice['no_hp']) ?></p>
                <p class="text-sm text-slate-600 mt-1"><?= nl2br(e($invoice['alamat'])) ?></p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Pengerjaan</p>
                <p class="font-semibold text-slate-900"><?= e($invoice['jenis_servis']) ?></p>
                <p class="text-sm text-slate-600 mt-1"><?= e($invoice['jumlah_unit']) ?> unit AC</p>
                <p class="text-sm text-slate-600 mt-1"><?= nl2br(e($invoice['detail_unit'])) ?></p>
            </div>
        </div>

        <div class="py-6 border-b border-slate-200">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Keterangan Pengerjaan</p>
            <p class="text-sm text-slate-700 whitespace-pre-line"><?= e($invoice['catatan'] ?: 'Tidak ada keterangan tambahan.') ?></p>
        </div>

        <div class="flex items-center justify-between pt-6">
            <span class="font-bold text-slate-900">Total Biaya</span>
            <span class="text-xl font-bold text-slate-900 font-mono"><?= formatRupiah($invoice['biaya']) ?></span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
