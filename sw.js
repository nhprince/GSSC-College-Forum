const CACHE_NAME = 'gssc-cache-v1';
const OFFLINE_URL = '/pages/maintenance.php'; // Or a dedicated offline page

const ASSETS_TO_CACHE = [
  '/',
  '/assets/css/main.css',
  '/assets/js/app.js',
  '/assets/js/chat.js',
  '/assets/js/notices.js',
  '/assets/js/storage.js',
  '/assets/js/members.js',
  '/manifest.json'
];

// Install: cache core assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate: cleanup old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(keys.map(key => {
        if (key !== CACHE_NAME) return caches.delete(key);
      }));
    })
  );
  self.clients.claim();
});

// Fetch: Network first, then cache (to allow offline viewing of recently loaded data)
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Cache successful responses for later offline use
        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, clone);
        });
        return response;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});

// Push: handle incoming notifications
self.addEventListener('push', event => {
  if (!(self.registration && self.registration.showNotification)) return;

  const data = event.data ? event.data.json() : { title: 'New Notification', body: 'You have a new update.' };

  const options = {
    body: data.body,
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/icon-192.png',
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification Click: open the app
self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then(clientList => {
      for (const client of clientList) {
        if (client.url === event.notification.data.url && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(event.notification.data.url);
      }
    })
  );
});
