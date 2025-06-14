const CACHE='gospod-v6';
const ASSETS=['/','/index.html','/main.js','/style.css'];

self.addEventListener('install',e=>{
  e.waitUntil(caches.open(CACHE).then(c=>Promise.all(
     ASSETS.map(a=>c.add(a).catch(()=>{}))
  )));
  self.skipWaiting();
});
self.addEventListener('fetch',e=>{
  e.respondWith(caches.match(e.request).then(r=>r||fetch(e.request)));
});
