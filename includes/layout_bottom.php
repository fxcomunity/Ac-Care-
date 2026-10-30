            </main>

            <!-- Main Footer -->
            <footer class="bg-white border-t border-slate-200 px-6 sm:px-8 py-3.5 text-xs text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-2">
                <span><?= e($website['site_name']) ?> &copy; <?= date('Y') ?>. Hak Cipta Dilindungi.</span>
                <span><?= in_array($currentRole, ['owner', 'admin'], true) ? 'Panel pengelolaan operasional' : ($currentRole === 'teknisi' ? 'Panel teknisi & pekerjaan servis' : 'Portal pelanggan & riwayat perawatan') ?></span>
            </footer>
        </div>
    </div>
<script src="<?= e(baseUrl('assets/js/app.js')) ?>"></script>
<script>
    (function () {
        const toggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('app-sidebar');
        if (!toggle || !sidebar) return;
        toggle.addEventListener('click', function () {
            const isOpen = !sidebar.classList.contains('hidden');
            sidebar.classList.toggle('hidden', isOpen);
            toggle.setAttribute('aria-expanded', String(!isOpen));
        });
    }());
</script>
<?php if (!empty($currentUser)): ?>
<script>window.appBaseUrl = <?= json_encode(baseUrl()) ?>; window.pushPublicKey = <?= json_encode($push['public_key']) ?>; window.csrfToken = <?= json_encode(csrfToken()) ?>;</script>
<script src="<?= e(baseUrl('assets/js/notifications.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
