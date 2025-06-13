// service-worker.js - Fixed version
const CACHE_NAME = 'gospod-app-v5';
const CACHE_ASSETS = [
  '/',
  '/index.html',
  '/main.js',
  '/styles.css'
  // Remove non-existent assets that cause cache failures
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        // Add assets individually to avoid failures
        return Promise.all(
          CACHE_ASSETS.map(asset => 
            cache.add(asset).catch(err => 
              console.warn(`Failed to cache ${asset}:`, err)
            )
          )
        );
      })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys => 
      Promise.all(
        keys.map(key => key !== CACHE_NAME ? caches.delete(key) : null)
      )
    ).then(() => clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then(cached => cached || fetch(event.request))
      .catch(() => caches.match('/'))
  );
});
