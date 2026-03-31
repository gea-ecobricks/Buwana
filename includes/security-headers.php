<?php
/**
 * Centralized HTTP security headers (IMP-01 Stage 1).
 *
 * Call send_security_headers() on every page.
 * Call send_security_headers(true) on authentication endpoints
 * (authorize.php, token.php, userinfo.php) to also set Cache-Control: no-store.
 *
 * Stage 2 (Content-Security-Policy with nonce system) is tracked in docs/improvements.md.
 */
function send_security_headers(bool $is_auth_endpoint = false): void {
    // Prevent MIME-type sniffing — browser must honour the declared Content-Type
    header('X-Content-Type-Options: nosniff');

    // Block this page from being loaded in any <iframe> — prevents clickjacking on login pages
    header('X-Frame-Options: DENY');

    // After the first HTTPS visit, the browser refuses plain-HTTP connections for 1 year
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

    // Auth endpoints: never cache tokens, codes, or userinfo in browser or proxy caches
    if ($is_auth_endpoint) {
        header('Cache-Control: no-store');
    }
}
