(function () {
    var button = document.querySelector('[data-enable-notifications]');
    if (!button) return;

    button.hidden = false;
    if (!('Notification' in window)) {
        button.textContent = 'Notifikasi tidak didukung';
        button.disabled = true;
        return;
    }

    function decodeKey(value) {
        var padding = '='.repeat((4 - value.length % 4) % 4);
        var base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        return Uint8Array.from(Array.prototype.map.call(raw, function (char) { return char.charCodeAt(0); }));
    }

    async function showSystemNotification(title, body) {
        if (Notification.permission !== 'granted') return;
        if ('serviceWorker' in navigator) {
            var registration = await navigator.serviceWorker.ready;
            await registration.showNotification(title, { body: body, icon: window.appBaseUrl + '/assets/img/icon.png', badge: window.appBaseUrl + '/assets/img/icon.png' });
        } else {
            new Notification(title, { body: body });
        }
    }

    async function activateNotifications(requestPermission) {
        button.disabled = true;
        button.textContent = 'Mengaktifkan...';
        try {
            var permission = Notification.permission;
            if (permission === 'default' && requestPermission) {
                permission = await Notification.requestPermission();
            }
            if (permission !== 'granted') {
                throw new Error(permission === 'denied'
                    ? 'Izin notifikasi diblokir. Aktifkan izin untuk situs ini melalui pengaturan browser.'
                    : 'Izinkan notifikasi melalui dialog browser untuk melanjutkan.');
            }
            if (!window.isSecureContext) {
                throw new Error('Notifikasi memerlukan HTTPS atau localhost. Buka website melalui alamat aman.');
            }
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                throw new Error('Browser ini belum mendukung Push Notification.');
            }
            if (!window.pushPublicKey) {
                throw new Error('Push belum dikonfigurasi. Atur VAPID_PUBLIC_KEY dan VAPID_PRIVATE_KEY di file .env.');
            }

            var registration = await navigator.serviceWorker.register(window.appBaseUrl + '/service-worker.js');
            var subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: decodeKey(window.pushPublicKey)
                });
            }
            var response = await fetch(window.appBaseUrl + '/api/subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken },
                body: JSON.stringify(subscription)
            });
            var result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Subscription notifikasi gagal disimpan.');
            }

            await showSystemNotification('PT AFE Teknik', 'Notifikasi berhasil diaktifkan di perangkat ini.');
            button.textContent = 'Notifikasi aktif';
            button.title = 'Notifikasi aktif di perangkat ini';
        } catch (error) {
            button.disabled = false;
            button.textContent = Notification.permission === 'denied' ? 'Izin notifikasi diblokir' : 'Coba aktifkan lagi';
            button.title = error.message;
            window.alert(error.message);
        }
    }

    button.addEventListener('click', function () {
        activateNotifications(true);
    });
    if (Notification.permission === 'granted') {
        activateNotifications(false);
    } else if (Notification.permission === 'denied') {
        button.textContent = 'Izin notifikasi diblokir';
        button.title = 'Klik ikon pengaturan situs di kiri alamat localhost, ubah Notifikasi menjadi Izinkan, lalu muat ulang halaman.';
    }

    var flash = document.querySelector('[data-app-flash="success"]');
    if (flash && Notification.permission === 'granted') {
        if ('serviceWorker' in navigator) navigator.serviceWorker.register(window.appBaseUrl + '/service-worker.js').then(function () { return showSystemNotification('PT AFE Teknik', flash.textContent.trim()); });
        else new Notification('PT AFE Teknik', { body: flash.textContent.trim() });
    }
}());