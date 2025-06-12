const CACHE_VERSION = '1.0.1';
const CACHE_NAME = `gospod-app-${CACHE_VERSION}`;

const CACHE_ASSETS = [
  '/',
  '/index.html',
  '/main.js',
  '/styles.css',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png'
];

// Install event - Pre-cache critical assets
self.addEventListener('install', event => {
  console.log('[Service Worker] Installing Service Worker...', event);
  
  // Skip waiting to ensure the new service worker activates immediately
  self.skipWaiting();
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] Caching app shell and assets');
        return cache.addAll(CACHE_ASSETS);
      })
      .catch(error => {
        console.error('[Service Worker] Cache installation failed:', error);
      })
  );
});

// Activate event - Clean up old caches
self.addEventListener('activate', event => {
  console.log('[Service Worker] Activating Service Worker...', event);
  
  // Take control of all clients immediately
  event.waitUntil(clients.claim());
  
  // Remove old caches
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('[Service Worker] Removing old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
  );
});

// Fetch event - Implement cache strategy
self.addEventListener('fetch', event => {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }
  
  // Skip API requests
  if (event.request.url.includes('/process-image.php') || 
      event.request.url.includes('/log-usage.php') || 
      event.request.url.includes('/get-usage.php')) {
    return;
  }
  
  // Implement stale-while-revalidate strategy for static assets
  if (event.request.url.match(/\.(html|js|css|json|png|jpg|jpeg|svg|gif)$/)) {
    event.respondWith(
      caches.match(event.request)
        .then(cachedResponse => {
          // Return cached response immediately if available
          const fetchPromise = fetch(event.request)
            .then(networkResponse => {
              // Update cache with new response
              if (networkResponse && networkResponse.status === 200) {
                const clonedResponse = networkResponse.clone();
                caches.open(CACHE_NAME).then(cache => {
                  cache.put(event.request, clonedResponse);
                });
              }
              return networkResponse;
            })
            .catch(error => {
              console.error('[Service Worker] Fetch failed:', error);
            });
          
          return cachedResponse || fetchPromise;
        })
    );
    return;
  }
  
  // Network-first strategy for other requests
  event.respondWith(
    fetch(event.request)
      .catch(() => {
        return caches.match(event.request);
      })
  );
});
