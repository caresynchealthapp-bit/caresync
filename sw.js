// sw.js
self.addEventListener('push', event => {
    let payload = { title: 'CareSync Reminder', body: 'You have a scheduled health task.' };
    
    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body,
        icon: 'Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg',
        badge: 'Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg',
        vibrate: [200, 100, 200],
        data: {
            url: payload.url || '/index.php'
        }
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

// Open CareSync when the user taps the notification
self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            for (const client of clientList) {
                if (client.url.includes('caresync') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(event.notification.data.url || '/index.php');
            }
        })
    );
});