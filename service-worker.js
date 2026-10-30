self.addEventListener('push', function (event) {
    var data = { title: 'PT AFE Teknik', body: 'Ada pengingat servis AC.', url: '/' };
    if (event.data) {
        try { data = Object.assign(data, event.data.json()); } catch (error) { data.body = event.data.text(); }
    }
    event.waitUntil(self.registration.showNotification(data.title, {
        body: data.body,
        icon: data.icon || '/Developer/assets/img/icon.png',
        badge: data.badge || '/Developer/assets/img/icon.png',
        data: { url: data.url }
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url || '/'));
});