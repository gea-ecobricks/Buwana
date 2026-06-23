<?php
/**
 * Shared CORS handling for Buwana token-authenticated APIs.
 *
 * Mirrors the trusted-origin list used by /token (token.php). Sends the CORS
 * headers when the request Origin is allow-listed and short-circuits the OPTIONS
 * preflight. Bearer-token APIs aren't cookie-authenticated, but we echo
 * credentials:true for parity with the existing endpoints.
 */

function buwana_allowed_origins(): array
{
    return [
        'https://earthcal.app',
        'https://gobrik.com',
        'https://ecobricks.org',
        'https://learning.ecobricks.org',
        'https://openbooks.ecobricks.org',
        'https://air2.earthen.io',
        'https://air.earthen.io',
        'https://hopeturtles.org',
        'https://files.mandala.team',
        'http://127.0.0.1:3000',
        'http://localhost:3000',
    ];
}

/**
 * Emit CORS headers for an allow-listed Origin and handle the OPTIONS preflight.
 * Call once at the top of an API endpoint, before emitting the body.
 *
 * @param string $methods e.g. 'GET, OPTIONS' or 'POST, OPTIONS'
 */
function send_api_cors_headers(string $methods = 'GET, POST, OPTIONS'): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && in_array($origin, buwana_allowed_origins(), true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    }
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header("Access-Control-Allow-Methods: $methods");

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
