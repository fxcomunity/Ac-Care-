<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$activeNav = 'jadwal';
$pageTitle = 'Jadwal Servis';
$error = '';
$allowedStatuses = ['menunggu_konfirmasi', 'dijadwalkan', 'sedang_dikerjakan', 'selesai', 'dibatalkan'];
$statusLabels = [
    'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
    'dijadwalkan' => 'Dijadwalkan',
    'sedang_dikerjakan' => 'Sedang Dikerjakan',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf_token'] ?? null);
        $id = trim($_POST['id'] ?? '');
        $status = $_POST['status'] ?? '';
        if ($id === '' || !in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Status jadwal tidak valid.');
        }
        $stmtUpdate = $pdo->prepare('UPDATE jadwal_servis SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmtUpdate->execute(['status' => $status, 'id' => $id]);
        setFlash('success', 'Status jadwal servis diperbarui.');
        redirect('jadwal/');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        $error = 'Jadwal servis belum tersedia. Jalankan schema database terbaru.';
    }
}

$jadwal = [];
try {
    $jadwal = $pdo->query("SELECT j.*, p.nama AS nama_pelanggan, p.no_hp, usr.nama AS nama_teknisi
        FROM jadwal_servis j
        JOIN pelanggan p ON p.id = j.pelanggan_id
        LEFT JOIN users usr ON usr.id = j.teknisi_id
        ORDER BY j.tanggal ASC, j.jam ASC NULLS LAST, j.created_at DESC")->fetchAll();
} catch (PDOException $exception) {
    $error = 'Tabel jadwal servis belum tersedia. Jalankan schema database terbaru.';
}

require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 tracking-tight">Jadwal Servis</h2>
        <p class="text-sm text-slate-500 mt-0.5">Pantau pekerjaan yang sudah dikonfirmasi dan dijadwalkan untuk pelanggan.</p>
    </div>

    <?php if ($error): ?><div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800"><?= e($error) ?></div><?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm text-slate-700">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    <tr><th class="py-3.5 px-6">Tanggal</th><th class="py-3.5 px-6">Pelanggan</th><th class="py-3.5 px-6">Unit</th><th class="py-3.5 px-6">Teknisi</th><th class="py-3.5 px-6">Status</th><th class="py-3.5 px-6 text-right">Tindakan</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!$jadwal): ?><tr><td colspan="6" class="py-10 px-6 text-center text-slate-500">Belum ada servis yang dijadwalkan.</td></tr><?php endif; ?>
                    <?php foreach ($jadwal as $item): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="py-4 px-6 whitespace-nowrap"><strong class="text-slate-900"><?= formatTanggal($item['tanggal']) ?></strong><?php if ($item['jam']): ?><span class="block text-xs text-slate-500 mt-0.5"><?= e(substr($item['jam'], 0, 5)) ?></span><?php endif; ?></td>
                            <td class="py-4 px-6"><a href="<?= baseUrl('pelanggan/detail.php?id=' . $item['pelanggan_id']) ?>" class="font-semibold text-slate-900 hover:text-blue-600"><?= e($item['nama_pelanggan']) ?></a><span class="block text-xs text-slate-500 mt-0.5"><?= e($item['no_hp']) ?></span></td>
                            <td class="py-4 px-6"><span class="font-semibold text-slate-800"><?= e($item['jumlah_unit']) ?> unit AC</span><span class="block text-xs text-slate-500 max-w-xs"><?= e($item['detail_unit'] ?: 'Detail AC belum dicatat') ?></span></td>
                            <td class="py-4 px-6 text-sm"><?= e($item['nama_teknisi'] ?: 'Belum ditugaskan') ?></td>
                            <td class="py-4 px-6"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $item['status'] === 'selesai' ? 'bg-emerald-50 text-emerald-700' : ($item['status'] === 'dibatalkan' ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700') ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span></td>
                            <td class="py-4 px-6 text-right"><form method="POST" class="inline-flex items-center gap-2"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><select name="status" aria-label="Ubah status jadwal" class="px-2 py-1.5 border border-slate-300 rounded-lg text-xs"><option value="">Ubah status</option><?php foreach ($allowedStatuses as $status): ?><option value="<?= e($status) ?>"><?= e($statusLabels[$status]) ?></option><?php endforeach; ?></select><button class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">Simpan</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
