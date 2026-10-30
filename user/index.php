<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
if (($_SESSION['user_role'] ?? '') !== 'user') {
    redirect('dashboard/');
}

$pageTitle = 'Home Pengguna';
$activeNav = 'dashboard';
$customer = null;
$transactions = [];
$totalTransactions = 0;
$totalSpent = 0;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT p.* FROM users u JOIN pelanggan p ON p.id = u.pelanggan_id WHERE u.id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $customer = $stmt->fetch();
    if ($customer) {
        $stmtTransactions = $pdo->prepare("SELECT s.id, s.tanggal_servis, s.jenis_servis, s.teknisi, s.jumlah_unit, s.detail_unit, s.biaya, s.catatan FROM servis s WHERE s.pelanggan_id = :pelanggan_id ORDER BY s.tanggal_servis DESC, s.created_at DESC");
        $stmtTransactions->execute(['pelanggan_id' => $customer['id']]);
        $transactions = $stmtTransactions->fetchAll();
        $totalTransactions = count($transactions);
        $totalSpent = array_sum(array_column($transactions, 'biaya'));
    }
}
require_once __DIR__ . '/../includes/layout_top.php';
?>
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-600"><?= e(websiteSettings()['site_name']) ?></p>
            <h2 class="text-2xl font-bold text-slate-900 mt-2">Halo, <?= e($_SESSION['user_nama'] ?? 'Pengguna') ?></h2>
            <p class="text-sm text-slate-500 mt-1">Pantau transaksi servis AC dan detail pekerjaan teknisi Anda.</p>
        </div>
        <a href="<?= baseUrl('user/pengaturan.php') ?>" class="inline-flex items-center justify-center px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm font-semibold">Pengaturan Akun</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
        <?php if ($customer): ?>
            <p class="text-sm text-slate-500">Akun terhubung sebagai pelanggan <strong class="text-slate-800"><?= e($customer['nama']) ?></strong>.</p>
            <p class="text-sm text-slate-500 mt-1"><?= e($customer['alamat']) ?> &bull; <?= e($customer['no_hp']) ?></p>
        <?php else: ?>
            <p class="text-sm text-slate-500 mt-2">Akun Anda menunggu pengaitan data pelanggan oleh admin.</p>
        <?php endif; ?>
    </div>

    <?php if ($customer): ?>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Transaksi</p><p class="text-3xl font-bold text-slate-900 mt-3"><?= number_format($totalTransactions) ?></p><p class="text-xs text-slate-500 mt-1">Invoice servis tercatat</p></div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Pembayaran</p><p class="text-2xl font-bold text-slate-900 mt-3 font-mono"><?= formatRupiah($totalSpent) ?></p><p class="text-xs text-slate-500 mt-1">Akumulasi biaya servis</p></div>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Status Akun</p><p class="text-lg font-bold text-emerald-700 mt-3">Aktif</p><p class="text-xs text-slate-500 mt-1">Data Anda tersimpan aman</p></div>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200"><h3 class="text-base font-bold text-slate-900">Riwayat Transaksi Servis</h3><p class="text-xs text-slate-500 mt-1">Daftar pekerjaan yang dilakukan teknisi untuk pelanggan ini.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm text-slate-700"><thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider"><tr><th class="py-3 px-6">Tanggal</th><th class="py-3 px-6">Pekerjaan</th><th class="py-3 px-6">Detail AC</th><th class="py-3 px-6">Teknisi</th><th class="py-3 px-6">Biaya</th></tr></thead><tbody class="divide-y divide-slate-100">
                <?php if (!$transactions): ?><tr><td colspan="5" class="py-8 px-6 text-center text-slate-500">Belum ada transaksi servis.</td></tr><?php endif; ?>
                <?php foreach ($transactions as $transaction): ?><tr class="hover:bg-slate-50"><td class="py-4 px-6 whitespace-nowrap font-medium text-slate-900"><?= formatTanggal($transaction['tanggal_servis']) ?></td><td class="py-4 px-6"><span class="font-semibold"><?= e($transaction['jenis_servis']) ?></span><span class="text-xs text-slate-500 block mt-1"><?= e($transaction['jumlah_unit']) ?> unit AC</span></td><td class="py-4 px-6 max-w-xs"><span><?= e($transaction['detail_unit'] ?: '-') ?></span><?php if ($transaction['catatan']): ?><span class="text-xs text-slate-500 block mt-1"><?= e($transaction['catatan']) ?></span><?php endif; ?></td><td class="py-4 px-6"><?= e($transaction['teknisi']) ?></td><td class="py-4 px-6 font-semibold font-mono whitespace-nowrap"><?= formatRupiah($transaction['biaya']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>