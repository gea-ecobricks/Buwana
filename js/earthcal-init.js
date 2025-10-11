(function initEarthcalServiceWorker() {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
    return;
  }

  const betaEnabled = window.EARTHCAL_BETA_TESTING?.enabled === true;
  const serviceWorkerUrl = '/js/service-worker.js';

  const clearWorkersAndCaches = async () => {
    try {
      const registrations = await navigator.serviceWorker.getRegistrations();
      await Promise.all(registrations.map((registration) => registration.unregister()));
    } catch (error) {
      console.error('[EarthCal] Failed to unregister service workers', error);
    }

    if (!('caches' in window)) {
      return;
    }

    try {
      const cacheNames = await caches.keys();
      await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
    } catch (error) {
      console.error('[EarthCal] Failed to clear caches', error);
    }
  };

  if (betaEnabled) {
    clearWorkersAndCaches();
    return;
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register(serviceWorkerUrl)
      .catch((error) => console.error('[EarthCal] Service worker registration failed', error));
  });
})();
