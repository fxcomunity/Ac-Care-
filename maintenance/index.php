<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin']);
redirect('dashboard/');

$pageTitle = 'Maintenance Operasional';
$activeNav = 'maintenance';
$error = '';
$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['Terencana', 'Dikerjakan', 'Selesai', 'Ditunda'];
$website = websiteSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf_token'] ?? null);
        $action = $_POST['action'] ?? '';
        if ($action === 'site_notice') {
            $maintenanceMessage = validateText($_POST['maintenance_message'] ?? '', 500, 'Pesan pemeliharaan');
            $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $stmtSetting = $pdo->prepare('INSERT INTO website_settings (setting_key, setting_value, updated_at) VALUES (:key, :value, CURRENT_TIMESTAMP) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP');
            foreach (['maintenance_mode' => $maintenanceMode, 'maintenance_message' => $maintenanceMessage] as $key => $value) {
                $stmtSetting->execute(['key' => $key, 'value' => $value]);
            }
            setFlash('success', $maintenanceMode === '1' ? 'Pemberitahuan maintenance website diaktifkan.' : 'Mode maintenance website dinonaktifkan.');
        } elseif ($action === 'create') {
            $judul = validateText($_POST['judul'] ?? '', 150, 'Judul pekerjaan');
            $aset = validateText($_POST['aset'] ?? '', 150, 'Aset atau pekerjaan');
            $kategori = $_POST['kategori'] ?? 'Preventive';
            $prioritas = $_POST['prioritas'] ?? 'Normal';
            $tanggalRencana = trim($_POST['tanggal_rencana'] ?? '');
            $lokasi = trim($_POST['lokasi'] ?? '');
            $pic = trim($_POST['pic'] ?? '');
            $checklist = trim($_POST['checklist'] ?? '');
            $catatan = trim($_POST['catatan'] ?? '');
            if (!in_array($kategori, ['Preventive', 'Corrective', 'Inspection'], true) || !in_array($prioritas, ['Rendah', 'Normal', 'Tinggi', 'Kritis'], true) || !$tanggalRencana || mb_strlen($lokasi) > 150 || mb_strlen($pic) > 100 || mb_strlen($checklist) > 5000 || mb_strlen($catatan) > 5000) {
                throw new InvalidArgumentException('Periksa kategori, prioritas, tanggal, dan batas karakter data maintenance.');
            }
            $kode = 'MNT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $pdo->prepare('INSERT INTO maintenance_tasks (kode, judul, kategori, aset, lokasi, prioritas, tanggal_rencana, pic, checklist, catatan, created_by) VALUES (:kode, :judul, :kategori, :aset, :lokasi, :prioritas, :tanggal_rencana, :pic, :checklist, :catatan, :created_by)');
            $stmt->execute(['kode' => $kode, 'judul' => $judul, 'kategori' => $kategori, 'aset' => $aset, 'lokasi' => $lokasi ?: null, 'prioritas' => $prioritas, 'tanggal_rencana' => $tanggalRencana, 'pic' => $pic ?: null, 'checklist' => $checklist ?: null, 'catatan' => $catatan ?: null, 'created_by' => $_SESSION['user_id']]);
            setFlash('success', 'Rencana maintenance berhasil dibuat.');
        } elseif ($action === 'status') {
            $id = trim($_POST['id'] ?? '');
            $status = $_POST['status'] ?? '';
            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Status maintenance tidak valid.');
            }
            $stmt = $pdo->prepare("UPDATE maintenance_tasks SET status = :status, tanggal_selesai = CASE WHEN :status = 'Selesai' THEN CURRENT_DATE ELSE NULL END, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute(['status' => $status, 'id' => $id]);
            setFlash('success', 'Status maintenance diperbarui.');
        }
        redirect('maintenance/' . ($statusFilter !== '' ? '?status=' . urlencode($statusFilter) : ''));
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        $error = 'Pekerjaan maintenance gagal disimpan.';
    }
}

$where = '';
$params = [];
if (in_array($statusFilter, $allowedStatuses, true)) {
    $where = ' WHERE status = :status';
    $params['status'] = $statusFilter;
}
$stmtTasks = $pdo->prepare("SELECT * FROM maintenance_tasks{$where} ORDER BY CASE status WHEN 'Dikerjakan' THEN 1 WHEN 'Terencana' THEN 2 WHEN 'Ditunda' THEN 3 ELSE 4 END, tanggal_rencana ASC, created_at DESC");
$stmtTasks->execute($params);
$tasks = $stmtTasks->fetchAll();
$summary = $pdo->query("SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE status = 'Terencana') AS planned, COUNT(*) FILTER (WHERE status = 'Dikerjakan') AS running, COUNT(*) FILTER (WHERE status = 'Selesai') AS done, COUNT(*) FILTER (WHERE status = 'Ditunda') AS postponed FROM maintenance_tasks")->fetch();
require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-blue-600">Operational control</p><h2 class="text-xl font-bold text-slate-900 mt-1">Maintenance Operasional</h2><p class="text-sm text-slate-500 mt-1">Rencanakan, jalankan, dan lacak pekerjaan pemeliharaan aset.</p></div><a href="#buat-rencana" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold">+ Buat Rencana</a></div>
    <?php if ($error): ?><div class="p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 shadow-sm"><div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5"><div><h3 class="text-base font-bold text-amber-950">Pemberitahuan Pemeliharaan Website</h3><p class="text-sm text-amber-800 mt-1">Tampilkan banner informasi kepada semua pengguna saat website sedang dalam maintenance operasional.</p></div><span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= $website['maintenance_mode'] === '1' ? 'bg-amber-200 text-amber-900' : 'bg-white text-slate-500' ?>"><?= $website['maintenance_mode'] === '1' ? 'Sedang aktif' : 'Tidak aktif' ?></span></div><form method="POST" class="mt-5 space-y-4"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="site_notice"><label class="flex items-center gap-3 text-sm font-semibold text-amber-950"><input type="checkbox" name="maintenance_mode" value="1" <?= $website['maintenance_mode'] === '1' ? 'checked' : '' ?>> Aktifkan banner pemeliharaan website</label><textarea name="maintenance_message" rows="3" maxlength="500" required class="w-full px-3.5 py-2.5 bg-white border border-amber-200 rounded-lg text-sm text-slate-800"><?= e($website['maintenance_message']) ?></textarea><div class="flex justify-end"><button class="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-semibold">Simpan Pemberitahuan</button></div></form></div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4"><div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm"><p class="text-xs text-slate-500">Total</p><p class="text-2xl font-bold mt-2"><?= (int)$summary['total'] ?></p></div><div class="bg-blue-50 border border-blue-100 rounded-xl p-4"><p class="text-xs text-blue-700">Terencana</p><p class="text-2xl font-bold text-blue-900 mt-2"><?= (int)$summary['planned'] ?></p></div><div class="bg-amber-50 border border-amber-100 rounded-xl p-4"><p class="text-xs text-amber-700">Dikerjakan</p><p class="text-2xl font-bold text-amber-900 mt-2"><?= (int)$summary['running'] ?></p></div><div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4"><p class="text-xs text-emerald-700">Selesai</p><p class="text-2xl font-bold text-emerald-900 mt-2"><?= (int)$summary['done'] ?></p></div><div class="bg-slate-100 border border-slate-200 rounded-xl p-4"><p class="text-xs text-slate-600">Ditunda</p><p class="text-2xl font-bold text-slate-800 mt-2"><?= (int)$summary['postponed'] ?></p></div></div>

    <div id="buat-rencana" class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm"><h3 class="text-base font-bold text-slate-900">Buat Rencana Maintenance</h3><form method="POST" class="mt-5 grid md:grid-cols-2 gap-4"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create"><div><label class="block text-sm font-semibold mb-1.5">Judul pekerjaan</label><input name="judul" maxlength="150" required placeholder="Contoh: Preventive maintenance AC lantai 2" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div><div><label class="block text-sm font-semibold mb-1.5">Aset / pekerjaan</label><input name="aset" maxlength="150" required placeholder="Contoh: 12 unit AC kantor" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div><div><label class="block text-sm font-semibold mb-1.5">Kategori</label><select name="kategori" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"><option>Preventive</option><option>Corrective</option><option>Inspection</option></select></div><div><label class="block text-sm font-semibold mb-1.5">Prioritas</label><select name="prioritas" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"><option>Normal</option><option>Rendah</option><option>Tinggi</option><option>Kritis</option></select></div><div><label class="block text-sm font-semibold mb-1.5">Tanggal rencana</label><input name="tanggal_rencana" type="date" required value="<?= date('Y-m-d') ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div><div><label class="block text-sm font-semibold mb-1.5">PIC / penanggung jawab</label><input name="pic" maxlength="100" placeholder="Nama teknisi atau tim" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div><div><label class="block text-sm font-semibold mb-1.5">Lokasi</label><input name="lokasi" maxlength="150" placeholder="Gedung, lantai, atau area" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div><div><label class="block text-sm font-semibold mb-1.5">Checklist pekerjaan</label><textarea name="checklist" maxlength="5000" rows="3" placeholder="Cek filter&#10;Cek tekanan freon&#10;Cek arus listrik" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></textarea></div><div class="md:col-span-2"><label class="block text-sm font-semibold mb-1.5">Catatan / standar kerja</label><textarea name="catatan" maxlength="5000" rows="3" placeholder="Target, risiko, kebutuhan sparepart, atau standar keselamatan kerja." class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></textarea></div><div class="md:col-span-2 flex justify-end"><button class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-sm font-semibold">Simpan Rencana</button></div></form></div>

    <div class="flex flex-wrap gap-2"><?php foreach (array_merge(['' => 'Semua'], array_combine($allowedStatuses, $allowedStatuses)) as $value => $label): ?><a href="<?= baseUrl('maintenance/' . ($value !== '' ? '?status=' . urlencode($value) : '')) ?>" class="px-3 py-2 rounded-lg text-xs font-semibold <?= $statusFilter === $value ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-600' ?>"><?= e($label) ?></a><?php endforeach; ?></div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-left text-sm text-slate-700"><thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider"><tr><th class="py-3 px-5">Pekerjaan / Aset</th><th class="py-3 px-5">Jadwal</th><th class="py-3 px-5">PIC &amp; Lokasi</th><th class="py-3 px-5">Prioritas</th><th class="py-3 px-5">Status</th><th class="py-3 px-5 text-right">Update</th></tr></thead><tbody class="divide-y divide-slate-100"><?php if (!$tasks): ?><tr><td colspan="6" class="py-10 px-5 text-center text-slate-500">Belum ada rencana maintenance.</td></tr><?php endif; ?><?php foreach ($tasks as $task): ?><tr class="align-top hover:bg-slate-50"><td class="py-4 px-5"><span class="text-xs font-mono text-slate-400"><?= e($task['kode']) ?></span><strong class="block text-slate-900 mt-1"><?= e($task['judul']) ?></strong><span class="text-xs text-slate-500 block mt-1"><?= e($task['aset']) ?><?php if ($task['lokasi']): ?> &bull; <?= e($task['lokasi']) ?><?php endif; ?></span><?php if ($task['checklist']): ?><details class="mt-2 text-xs text-slate-500"><summary class="cursor-pointer font-semibold">Lihat checklist</summary><p class="whitespace-pre-line mt-1"><?= e($task['checklist']) ?></p></details><?php endif; ?></td><td class="py-4 px-5 whitespace-nowrap"><?= formatTanggal($task['tanggal_rencana']) ?><?php if ($task['tanggal_selesai']): ?><span class="text-xs text-emerald-600 block">Selesai <?= formatTanggal($task['tanggal_selesai']) ?></span><?php endif; ?></td><td class="py-4 px-5"><?= e($task['pic'] ?: '-') ?></td><td class="py-4 px-5"><span class="text-xs font-semibold <?= $task['prioritas'] === 'Kritis' ? 'text-red-700' : ($task['prioritas'] === 'Tinggi' ? 'text-amber-700' : 'text-slate-600') ?>"><?= e($task['prioritas']) ?></span></td><td class="py-4 px-5"><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700"><?= e($task['status']) ?></span></td><td class="py-4 px-5 text-right"><form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= e($task['id']) ?>"><select name="status" onchange="this.form.submit()" class="px-2 py-1.5 border border-slate-300 rounded-lg text-xs"><option value="">Pilih status</option><?php foreach ($allowedStatuses as $status): ?><option value="<?= e($status) ?>" <?= $task['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></form></td></tr><?php endforeach; ?></tbody></table></div></div>
+</div>
+<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
