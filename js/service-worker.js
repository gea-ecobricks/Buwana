importScripts('earthcal-config.js');

const CACHE_NAME = 'earthcal-runtime-v1';
const PRECACHE_ASSETS = [];
const cachingEnabled = self.EARTHCAL_BETA_TESTING?.enabled !== true;

self.addEventListener('install', (event) => {
  if (!cachingEnabled) {
    self.skipWaiting();
    return;
  }

  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => (PRECACHE_ASSETS.length ? cache.addAll(PRECACHE_ASSETS) : undefined))
  );

  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  if (!cachingEnabled) {
    event.waitUntil(self.clients.claim());
    return;
  }

  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) =>
        Promise.all(
          cacheNames
            .filter((cacheName) => cacheName !== CACHE_NAME)
            .map((cacheName) => caches.delete(cacheName))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  if (!cachingEnabled) {
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(event.request)
        .then((networkResponse) => {
          const responseClone = networkResponse.clone();

          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone).catch((error) => {
              console.error('[EarthCal] Failed to cache response', error);
            });
          });

          return networkResponse;
        })
        .catch((error) => {
          console.error('[EarthCal] Network request failed', error);
          throw error;
        });
    })
  );
});
