/**
 * Shared cache configuration for the EarthCal app.
 *
 * Toggle `EARTHCAL_BETA_TESTING.enabled` to switch caching behaviour
 * across both the main thread and the service worker. When set to
 * `true`, caching is disabled so beta builds always load fresh assets.
 */
(function configureEarthcalScope(globalScope) {
  const EARTHCAL_BETA_TESTING = { enabled: true };

  if (typeof self !== 'undefined') {
    self.EARTHCAL_BETA_TESTING = EARTHCAL_BETA_TESTING;
  }

  if (typeof window !== 'undefined') {
    window.EARTHCAL_BETA_TESTING = EARTHCAL_BETA_TESTING;
  }

  if (globalScope && !globalScope.EARTHCAL_BETA_TESTING) {
    globalScope.EARTHCAL_BETA_TESTING = EARTHCAL_BETA_TESTING;
  }
})(typeof globalThis !== 'undefined' ? globalThis : undefined);
