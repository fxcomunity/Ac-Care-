<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireLogin();

$activeNav = 'pelanggan';
$id = $_GET['id'] ?? '';
if (empty($id)) {
    redirect('pelanggan/');
}

$isStaff = isStaffRole();
if (!$isStaff) {
    $stmtOwner = $pdo->prepare('SELECT pelanggan_id FROM users WHERE id = :user_id');
    $stmtOwner->execute(['user_id' => $_SESSION['user_id']]);
    if ($stmtOwner->fetchColumn() !== $id) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

// Data Pelanggan
$stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$pelanggan = $stmt->fetch();

if (!$pelanggan) {
    setFlash('error', 'Data pelanggan tidak ditemukan.');
    redirect('pelanggan/');
}

$pageTitle = 'Detail Pelanggan: ' . $pelanggan['nama'];

// Riwayat Servis milik pelanggan
$stmtServis = $pdo->prepare("
    SELECT s.*
    FROM servis s
    WHERE s.pelanggan_id = :pelanggan_id
    ORDER BY s.tanggal_servis DESC, s.created_at DESC
");
$stmtServis->execute(['pelanggan_id' => $id]);
$riwayatServis = $stmtServis->fetchAll();

require_once __DIR__ . '/../includes/layout_top.php';
?>

<!-- Header Info Pelanggan -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <div class="flex items-center gap-3">
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight"><?= e($pelanggan['nama']) ?></h2>
            <?php if ($isStaff): ?><a href="<?= baseUrl('pelanggan/edit.php?id=' . $pelanggan['id']) ?>" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-md transition-colors">Edit Profil</a><?php endif; ?>
        </div>
        <p class="text-xs text-slate-500 mt-1">Terdaftar sejak <?= formatTanggal($pelanggan['created_at']) ?></p>
    </div>
    <div class="flex items-center gap-3">
        <?php if ($isStaff): ?><a href="<?= baseUrl('servis/tambah.php?pelanggan_id=' . $pelanggan['id']) ?>" class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Buat Invoice Servis
        </a><?php endif; ?>
    </div>
</div>

<!-- Info Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-1">Nomor Telepon / WhatsApp</span>
        <div class="flex items-center justify-between mt-2">
            <span class="text-base font-semibold text-slate-900 font-mono"><?= e($pelanggan['no_hp']) ?></span>
            <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $pelanggan['no_hp'])) ?>" 
               target="_blank" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-md transition-colors">
                Chat WA
            </a>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm md:col-span-2">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-1">Alamat Domisili</span>
        <p class="text-sm font-medium text-slate-800 mt-2"><?= nl2br(e($pelanggan['alamat'])) ?></p>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-1">Status Pelanggan</span>
        <span class="inline-flex mt-2 px-2.5 py-1 rounded-full text-xs font-semibold <?= ($pelanggan['status'] ?? 'aktif') === 'aktif' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= e(ucfirst($pelanggan['status'] ?? 'aktif')) ?></span>
    </div>
    <?php if (!empty($pelanggan['email'])): ?><div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm"><span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-1">Email</span><p class="text-sm font-medium text-slate-800 mt-2 break-all"><?= e($pelanggan['email']) ?></p></div><?php endif; ?>
    <?php if (!empty($pelanggan['catatan'])): ?><div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm md:col-span-<?= !empty($pelanggan['email']) ? '1' : '2' ?>"><span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-1">Catatan</span><p class="text-sm text-slate-700 mt-2"><?= nl2br(e($pelanggan['catatan'])) ?></p></div><?php endif; ?>
</div>

<!-- Section: Riwayat Servis -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h3 class="text-base font-bold text-slate-900">Riwayat Servis Pelanggan</h3>
            <p class="text-xs text-slate-500 mt-0.5">Catatan seluruh pekerjaan perawatan yang pernah dilakukan.</p>
        </div>
        <a href="<?= baseUrl('servis/tambah.php?pelanggan_id=' . $pelanggan['id']) ?>" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
            + Catat Pekerjaan
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="py-3 px-6">Tanggal</th>
                    <th class="py-3 px-6">Invoice AC</th>
                    <th class="py-3 px-6">Pekerjaan</th>
                    <th class="py-3 px-6">Teknisi</th>
                    <th class="py-3 px-6">Biaya</th>
                    <th class="py-3 px-6">Catatan</th>
                    <th class="py-3 px-6 text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($riwayatServis)): ?>
                    <tr>
                        <td colspan="7" class="py-8 px-6 text-center text-slate-500">
                            Belum ada riwayat servis tercatat untuk pelanggan ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayatServis as $s): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-6 font-medium text-slate-900 whitespace-nowrap">
                                <?= formatTanggal($s['tanggal_servis']) ?>
                            </td>
                            <td class="py-3.5 px-6 font-semibold text-slate-800">
                                <?= e($s['jumlah_unit']) ?> unit AC
                                <span class="text-xs text-slate-400 font-normal block"><?= e($s['detail_unit'] ?: '-') ?></span>
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                                    <?= e($s['jenis_servis']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-600">
                                <?= e($s['teknisi']) ?>
                            </td>
                            <td class="py-3.5 px-6 font-semibold text-slate-900 font-mono">
                                <?= formatRupiah($s['biaya']) ?>
                            </td>
                            <td class="py-3.5 px-6 text-xs text-slate-500 max-w-xs truncate" title="<?= e($s['catatan'] ?? '') ?>">
                                <?= e($s['catatan'] ?: '-') ?>
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <a href="<?= baseUrl('servis/invoice.php?id=' . $s['id']) ?>" class="text-xs text-blue-600 hover:text-blue-800 font-semibold mr-3">
                                    Invoice
                                </a>
                                <form method="POST" action="<?= baseUrl('servis/hapus.php') ?>" class="inline" onsubmit="return confirm('Hapus riwayat servis ini?')"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= e($s['id']) ?>"><button type="submit" class="text-xs text-red-600 hover:text-red-800 font-semibold">Hapus</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
