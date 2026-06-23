<?php
/**
 * JSON-safe responses for Buwana APIs.
 *
 * display_errors stays ON globally (project preference), but a stray PHP
 * notice/warning/fatal would otherwise leak into a JSON body and break the
 * client's JSON.parse (exactly the bug we hit with profile_service.php). These
 * helpers buffer output and discard any such noise before emitting clean JSON,
 * while still logging detail server-side. Same ob_start/ob_clean idiom as
 * token.php.
 */

/**
 * Start output buffering + install a shutdown guard so even a fatal error
 * (e.g. a missing require) returns JSON rather than an HTML error page.
 * Call once, first thing, in every API endpoint.
 */
function init_json_api(): void
{
    if (ob_get_level() === 0) {
        ob_start();
    }

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            error_log('[API] Fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            echo json_encode(['error' => 'server_error']);
        }
    });
}

/** Emit a clean JSON response and exit, discarding any buffered noise first. */
function api_json($data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}

/** Emit a JSON error envelope and exit. */
function api_error(string $error, int $status = 400, array $extra = []): void
{
    api_json(array_merge(['error' => $error], $extra), $status);
}

/**
 * Read the request body as an associative array, accepting either a JSON body
 * (application/json) or a normal form POST.
 */
function parse_request_body(): array
{
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($content_type, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}
