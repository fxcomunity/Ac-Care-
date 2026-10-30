<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['admin', 'teknisi']);

$activeNav = 'pengaturan';
$pageTitle = 'Template WhatsApp';
$error = '';
$databaseReady = true;
try {
    $template = $pdo->query('SELECT * FROM whatsapp_templates WHERE aktif = TRUE ORDER BY updated_at DESC LIMIT 1')->fetch();
} catch (PDOException $exception) {
    $databaseReady = false;
    $template = null;
    $error = 'Tabel template WhatsApp belum tersedia. Jalankan migrasi SQL di database Neon terlebih dahulu.';
}
if (!$template) {
    $template = ['id' => '', 'nama' => 'Reminder servis berkala', 'isi' => 'Halo Bapak/Ibu {nama_pelanggan},\n\nUnit AC {nama_unit} terakhir diservis pada {tanggal_servis}.\n\nJadwal berikutnya: {jadwal_berikutnya}.\n\nSalam,\n{nama_bisnis}'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$databaseReady) {
            throw new RuntimeException('Database belum memiliki tabel whatsapp_templates.');
        }
        verifyCsrf($_POST['csrf_token'] ?? null);
        $nama = validateText($_POST['nama'] ?? '', 100, 'Nama template');
        $isi = validateText($_POST['isi'] ?? '', 5000, 'Isi template');
        if (!preg_match('/\{nama_pelanggan\}|\{nama\}|\{nama_unit\}|\{unit\}|\{tanggal_servis\}|\{tanggal\}/', $isi)) {
            throw new InvalidArgumentException('Template wajib memakai placeholder pelanggan, unit, atau tanggal servis.');
        }
        $pdo->beginTransaction();
        $pdo->exec('UPDATE whatsapp_templates SET aktif = FALSE');
        $stmt = $pdo->prepare('INSERT INTO whatsapp_templates (nama, isi, aktif, updated_by) VALUES (:nama, :isi, TRUE, :updated_by)');
        $stmt->execute(['nama' => $nama, 'isi' => $isi, 'updated_by' => $_SESSION['user_id']]);
        $pdo->commit();
        setFlash('success', 'Template WhatsApp berhasil diperbarui.');
        redirect('pengaturan/whatsapp.php');
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception->getMessage();
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Template belum dapat disimpan. Pastikan schema database sudah dijalankan.';
    }
}

require_once __DIR__ . '/../includes/layout_top.php';
?>
<div class="max-w-3xl mx-auto">
    <div class="mb-6"><h2 class="text-xl font-bold text-slate-900">Template WhatsApp</h2><p class="text-sm text-slate-500 mt-1">Admin dan teknisi dapat mengubah kalimat reminder yang dipakai tombol WhatsApp.</p></div>
    <?php if ($error): ?><div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$databaseReady): ?><div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800"><strong>Jalankan SQL ini di Neon SQL Editor:</strong><pre class="mt-3 whitespace-pre-wrap text-xs">CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    nama VARCHAR(100) NOT NULL,
    isi TEXT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    updated_by UUID REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO whatsapp_templates (nama, isi, aktif) VALUES ('Reminder servis berkala', 'Halo {nama}, unit {unit} terakhir diservis pada {tanggal}. Salam, {perusahaan}', TRUE);</pre></div><?php endif; ?>
    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
        <form method="POST" class="space-y-5"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div><label class="block text-sm font-semibold mb-1.5">Nama template</label><input name="nama" maxlength="100" required value="<?= e($_POST['nama'] ?? $template['nama']) ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label class="block text-sm font-semibold mb-1.5">Kalimat WhatsApp</label><textarea name="isi" rows="12" maxlength="5000" required class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm leading-6"><?= e($_POST['isi'] ?? $template['isi']) ?></textarea><p class="mt-2 text-xs text-slate-500">Placeholder: <code>{nama_pelanggan}</code>, <code>{nama_unit}</code>, <code>{tanggal_servis}</code>, <code>{jadwal_berikutnya}</code>, <code>{nama_bisnis}</code>, <code>{nomor_telepon}</code>. Placeholder lama tetap didukung.</p></div>
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600">Contoh hasil: <strong>Halo Bapak/Ibu Budi</strong>, unit AC Kamar Utama terakhir diservis pada 10 September 2026.</div>
            <div class="pt-4 border-t border-slate-100 flex justify-end"><button class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold">Simpan Template</button></div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>