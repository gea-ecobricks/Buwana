<?php
/**
 * Bearer-token authentication for Buwana APIs.
 *
 * Validates the OIDC access token (RS256, signed with the calling app's private
 * key), enforces a required scope, and confirms the user holds a registered
 * connection to that app. Returns the authenticated identity, or emits a JSON
 * error and exits. Modeled on userinfo.php, with the scope + connection checks
 * added — these are what make buwana:profile.read / buwana:profile.write mean
 * something.
 *
 * Requires (included by the caller): vendor/autoload.php, buwanaconn_env.php
 * ($buwana_conn) and api_response.php (api_error / api_json).
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * @param mysqli $buwana_conn
 * @param string $required_scope  e.g. 'buwana:profile.read'
 * @return array{buwana_id:int, client_id:string, scopes:string[]}
 */
function authenticate_api_request($buwana_conn, string $required_scope): array
{
    // 1) Bearer token from the Authorization header.
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) {
        api_error('missing_bearer_token', 401);
    }
    $jwt = $m[1];

    // 2) Read aud (client_id) from the UNVERIFIED payload so we know which app's
    //    public key to verify against (chicken-and-egg, same as userinfo.php).
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        api_error('invalid_token', 401);
    }
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    $client_id = $payload['aud'] ?? null;
    if (!$client_id) {
        api_error('invalid_token', 401);
    }

    // 3) Fetch that app's public key.
    $stmt = $buwana_conn->prepare("SELECT jwt_public_key FROM apps_tb WHERE client_id = ?");
    $stmt->bind_param('s', $client_id);
    $stmt->execute();
    $stmt->bind_result($public_key);
    $stmt->fetch();
    $stmt->close();
    if (!$public_key) {
        api_error('unknown_client', 401);
    }

    // 4) Verify signature + standard claims (exp is enforced by the library).
    try {
        $decoded = JWT::decode($jwt, new Key($public_key, 'RS256'));
    } catch (ExpiredException $e) {
        api_error('token_expired', 401);            // client should refresh + retry
    } catch (Exception $e) {
        api_error('invalid_token', 401, ['details' => $e->getMessage()]);
    }

    // 5) Scope gate.
    $scopes = preg_split('/[\s,]+/', trim($decoded->scope ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    if (!in_array($required_scope, $scopes, true)) {
        api_error('insufficient_scope', 403, ['required_scope' => $required_scope]);
    }

    // 6) Resolve buwana_id from sub (matches userinfo.php: buwana_N | numeric | open_id).
    $sub = $decoded->sub ?? '';
    if ($sub === '') {
        api_error('invalid_token', 401);
    }
    if (strpos($sub, 'buwana_') === 0) {
        $buwana_id = (int) substr($sub, strlen('buwana_'));
    } elseif (is_numeric($sub)) {
        $buwana_id = (int) $sub;
    } else {
        $stmt = $buwana_conn->prepare("SELECT buwana_id FROM users_tb WHERE open_id = ? LIMIT 1");
        $stmt->bind_param('s', $sub);
        $stmt->execute();
        $stmt->bind_result($buwana_id);
        $stmt->fetch();
        $stmt->close();
    }
    if (empty($buwana_id)) {
        api_error('user_not_found', 404);
    }

    // 7) Connection gate — the user must hold a registered connection to this app.
    //    (Mirrors check_user_app_connection(); inlined to avoid that file's CORS /
    //     session side effects in an API context.)
    $stmt = $buwana_conn->prepare(
        "SELECT id FROM user_app_connections_tb
         WHERE buwana_id = ? AND client_id = ? AND status = 'registered' LIMIT 1"
    );
    $stmt->bind_param('is', $buwana_id, $client_id);
    $stmt->execute();
    $stmt->store_result();
    $connected = $stmt->num_rows > 0;
    $stmt->close();
    if (!$connected) {
        api_error('not_connected', 403);
    }

    return [
        'buwana_id' => (int) $buwana_id,
        'client_id' => $client_id,
        'scopes'    => $scopes,
    ];
}
