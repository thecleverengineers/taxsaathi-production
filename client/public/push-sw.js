self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch {
    payload = { body: event.data ? event.data.text() : '' };
  }

  const title = payload.title || 'TaxSaathi';
  const options = {
    body: payload.body || 'You have a new update.',
    tag: payload.tag || 'taxsaathi-update',
    renotify: false,
    data: { url: payload.url || '/notifications' }
  };

  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
    const visible = windows.some((client) => client.visibilityState === 'visible');
    if (visible) {
      windows.forEach((client) => client.postMessage({ type: 'taxsaathi:push', payload }));
      return undefined;
    }
    return self.registration.showNotification(title, options);
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetPath = String(event.notification.data && event.notification.data.url || '/notifications');
  const target = new URL(targetPath.startsWith('/') && !targetPath.startsWith('//') ? targetPath : '/notifications', self.location.origin).href;
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
    for (const client of windows) {
      if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
        if ('navigate' in client) client.navigate(target);
        return client.focus();
      }
    }
    return self.clients.openWindow(target);
  }));
});
