<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireRole(['owner']);

$pageTitle = 'Perubahan Website';
$activeNav = 'website';
$error = '';
$settings = websiteSettings();
$selectedLogoSource = $_POST['logo_source'] ?? ($settings['site_logo'] === '' ? 'upload' : 'keep');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf($_POST['csrf_token'] ?? null);
        $siteName = validateText($_POST['site_name'] ?? '', 100, 'Nama website');
        $siteSlogan = validateText($_POST['site_slogan'] ?? '', 200, 'Slogan');
        $logoValue = $settings['site_logo'];
        $logoSource = $_POST['logo_source'] ?? 'keep';

        if ($logoSource === 'url') {
            $logoUrl = trim($_POST['logo_url'] ?? '');
            if ($logoUrl === '' || !filter_var($logoUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($logoUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                throw new InvalidArgumentException('URL logo harus berupa URL http atau https yang valid.');
            }
            if (mb_strlen($logoUrl) > 500) {
                throw new InvalidArgumentException('URL logo maksimal 500 karakter.');
            }
            $logoValue = $logoUrl;
        } elseif ($logoSource === 'upload') {
            $file = $_FILES['logo_file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Pilih file gambar yang akan diunggah.');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException('Ukuran logo maksimal 2 MB.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/x-icon' => 'ico'];
            if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
                throw new InvalidArgumentException('Logo harus berupa PNG, JPG, WEBP, GIF, atau ICO yang valid.');
            }
            $uploadDirectory = __DIR__ . '/../assets/uploads/branding';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException('Folder upload logo tidak dapat dibuat.');
            }
            $fileName = 'site-logo-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
            if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $fileName)) {
                throw new RuntimeException('File logo gagal disimpan.');
            }
            $logoValue = 'assets/uploads/branding/' . $fileName;
        } elseif ($logoSource === 'remove') {
            $logoValue = '';
        }

        $stmt = $pdo->prepare('INSERT INTO website_settings (setting_key, setting_value, updated_at) VALUES (:key, :value, CURRENT_TIMESTAMP) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP');
        foreach (['site_name' => $siteName, 'site_slogan' => $siteSlogan, 'site_logo' => $logoValue] as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
        setFlash('success', 'Perubahan website berhasil disimpan.');
        redirect('pengaturan/website.php');
    } catch (InvalidArgumentException | RuntimeException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Perubahan website gagal disimpan. Pastikan schema database sudah dijalankan.';
    }
}

$settings = websiteSettings();
$logoPreview = $settings['site_logo'] !== '' ? websiteAssetUrl($settings['site_logo']) : '';
require_once __DIR__ . '/../includes/layout_top.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="mb-6"><h2 class="text-xl font-bold text-slate-900">Perubahan Website</h2><p class="text-sm text-slate-500 mt-1">Atur nama, slogan, dan favicon/logo website.</p></div>
    <?php if ($error): ?><div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?= e($error) ?></div><?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div><label for="site_name" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama website</label><input id="site_name" name="site_name" maxlength="100" required value="<?= e($_POST['site_name'] ?? $settings['site_name']) ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label for="site_slogan" class="block text-sm font-semibold text-slate-700 mb-1.5">Slogan</label><input id="site_slogan" name="site_slogan" maxlength="200" required value="<?= e($_POST['site_slogan'] ?? $settings['site_slogan']) ?>" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>

            <div class="border-t border-slate-100 pt-5">
                <div class="flex items-center justify-between gap-3"><div><label class="block text-sm font-semibold text-slate-700">Favicon / logo website</label><p class="text-xs text-slate-500 mt-1">Gunakan upload gambar atau URL gambar. Maksimal upload 2 MB.</p></div><?php if ($logoPreview): ?><img src="<?= e($logoPreview) ?>" alt="Logo saat ini" class="w-12 h-12 object-contain rounded-lg border border-slate-200 p-1"><?php endif; ?></div>
                <div class="grid sm:grid-cols-4 gap-3 mt-4">
                    <label class="flex items-center gap-2 border border-slate-200 rounded-lg p-3 text-sm"><input type="radio" name="logo_source" value="keep" <?= $selectedLogoSource === 'keep' ? 'checked' : '' ?>> Pertahankan</label>
                    <label class="flex items-center gap-2 border border-slate-200 rounded-lg p-3 text-sm"><input type="radio" name="logo_source" value="upload" <?= $selectedLogoSource === 'upload' ? 'checked' : '' ?>> Upload gambar</label>
                    <label class="flex items-center gap-2 border border-slate-200 rounded-lg p-3 text-sm"><input type="radio" name="logo_source" value="url" <?= $selectedLogoSource === 'url' ? 'checked' : '' ?>> Pakai URL</label>
                    <label class="flex items-center gap-2 border border-slate-200 rounded-lg p-3 text-sm"><input type="radio" name="logo_source" value="remove" <?= $selectedLogoSource === 'remove' ? 'checked' : '' ?>> Hapus logo</label>
                </div>
                <div id="drop-zone" class="mt-4 border-2 border-dashed border-slate-300 rounded-xl p-6 text-center cursor-pointer hover:border-blue-500 transition-colors <?= $selectedLogoSource === 'upload' ? '' : 'hidden' ?>"><input id="logo_file" name="logo_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/x-icon" class="hidden"><p class="text-sm font-semibold text-slate-700">Tarik gambar ke sini atau klik untuk memilih</p><p id="file-name" class="text-xs text-slate-500 mt-1">PNG, JPG, WEBP, GIF, atau ICO</p></div>
                <div id="url-field" class="mt-4 <?= $selectedLogoSource === 'url' ? '' : 'hidden' ?>"><label for="logo_url" class="block text-sm font-semibold text-slate-700 mb-1.5">URL logo</label><input id="logo_url" name="logo_url" type="url" maxlength="500" value="<?= e($_POST['logo_url'] ?? (filter_var($settings['site_logo'], FILTER_VALIDATE_URL) ? $settings['site_logo'] : '')) ?>" placeholder="https://contoh.com/logo.png" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end"><button type="submit" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold">Simpan Perubahan</button></div>
        </form>
    </div>
</div>
<script>
(function () {
    var dropZone = document.getElementById('drop-zone');
    var fileInput = document.getElementById('logo_file');
    var fileName = document.getElementById('file-name');
    var urlField = document.getElementById('url-field');
    var radios = document.querySelectorAll('input[name="logo_source"]');
    dropZone.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () { fileName.textContent = fileInput.files[0] ? fileInput.files[0].name : 'PNG, JPG, WEBP, GIF, atau ICO'; });
    ['dragenter', 'dragover'].forEach(function (eventName) { dropZone.addEventListener(eventName, function (event) { event.preventDefault(); dropZone.classList.add('border-blue-500', 'bg-blue-50'); }); });
    ['dragleave', 'drop'].forEach(function (eventName) { dropZone.addEventListener(eventName, function (event) { event.preventDefault(); dropZone.classList.remove('border-blue-500', 'bg-blue-50'); }); });
    dropZone.addEventListener('drop', function (event) { if (event.dataTransfer.files.length) { fileInput.files = event.dataTransfer.files; fileName.textContent = event.dataTransfer.files[0].name; } });
    radios.forEach(function (radio) { radio.addEventListener('change', function () { urlField.classList.toggle('hidden', this.value !== 'url'); dropZone.classList.toggle('hidden', this.value !== 'upload'); }); });
}());
</script>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
