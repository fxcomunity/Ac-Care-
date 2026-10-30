<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
if (!in_array($_SESSION['user_role'] ?? '', ['developer', 'owner'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$activeNav = 'pengaturan';
$pageTitle = 'Status Sistem';
$auditLogs = [];
try {
    $auditLogs = $pdo->query('SELECT action, target, description, role, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 25')->fetchAll();
} catch (PDOException $exception) {
    $auditLogs = [];
}

require_once __DIR__ . '/../includes/layout_top.php';
?>
<div class="max-w-5xl mx-auto space-y-6">
    <div><h2 class="text-xl font-bold text-slate-900">Status Sistem</h2><p class="text-sm text-slate-500 mt-1">Informasi teknis dan aktivitas keamanan tanpa menampilkan credential sensitif.</p></div>
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm"><p class="text-xs uppercase tracking-wider text-slate-500">PHP</p><p class="mt-2 text-lg font-bold text-slate-900"><?= e(PHP_VERSION) ?></p></div>
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm"><p class="text-xs uppercase tracking-wider text-slate-500">Environment</p><p class="mt-2 text-lg font-bold text-slate-900"><?= e(envValue('APP_ENV', 'development')) ?></p></div>
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm"><p class="text-xs uppercase tracking-wider text-slate-500">Session</p><p class="mt-2 text-lg font-bold text-emerald-700">Aktif</p></div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden"><div class="px-6 py-4 border-b border-slate-200"><h3 class="text-base font-bold text-slate-900">Aktivitas Keamanan Terbaru</h3></div><div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-3">Waktu</th><th class="px-6 py-3">Role</th><th class="px-6 py-3">Aktivitas</th><th class="px-6 py-3">Keterangan</th></tr></thead><tbody class="divide-y divide-slate-100"><?php if (!$auditLogs): ?><tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Belum ada aktivitas audit.</td></tr><?php endif; ?><?php foreach ($auditLogs as $log): ?><tr><td class="px-6 py-3 whitespace-nowrap text-slate-500"><?= formatTanggal($log['created_at']) ?></td><td class="px-6 py-3 capitalize"><?= e($log['role'] ?: '-') ?></td><td class="px-6 py-3 font-semibold text-slate-800"><?= e($log['action']) ?></td><td class="px-6 py-3 text-slate-600"><?= e($log['description'] ?: '-') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
