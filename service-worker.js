self.addEventListener('install', e => {
  e.waitUntil(
    caches.open('gospod-v6').then(async cache => {
      for (const asset of ['/', '/index.html','/main.js','/styles.css']) {
        try { await cache.add(asset); }
        catch(err){ console.warn('Skip cache', asset, err); }
      }
    }).then(()=>self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.map(k => k !== 'gospod-v6' ? caches.delete(k) : null))
    ).then(()=>clients.claim())
  );
});

self.addEventListener('fetch', e => {
  e.respondWith(
    caches.match(e.request).then(r => r || fetch(e.request))
  );
});
