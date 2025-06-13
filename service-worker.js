const CACHE_VERSION = Date.now(); // Use timestamp for auto-versioning
const CACHE_NAME = `gospod-app-${CACHE_VERSION}`;

const CACHE_ASSETS = [
  '/',
  '/index.html',
  '/main.js',
  '/style.css',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/offline.html' // New offline page
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return Promise.all(
          CACHE_ASSETS.map(asset => {
            return cache.add(asset).catch(error => {
              console.error(`Failed to cache ${asset}:`, error);
            });
          })
        );
      })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  // Handle API requests
  if (event.request.url.includes('/process-image') ||
      event.request.url.includes('/log-usage') ||
      event.request.url.includes('/get-usage')) {
    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, response.clone()));
          return response;
        })
        .catch(() => caches.match(event.request).then(r => r || caches.match('/offline.html')))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      const fetchAndUpdate = fetch(event.request)
        .then(networkResponse => {
          if (networkResponse.ok && event.request.method === 'GET') {
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, networkResponse.clone()));
          }
          return networkResponse;
        })
        .catch(() => cachedResponse);
      return cachedResponse || fetchAndUpdate;
    })
  );
});
