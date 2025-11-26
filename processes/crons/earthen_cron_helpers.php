<?php
/**
 * Cron-safe Earthen / Ghost helpers.
 * - No HTML/JS output
 * - No reliance on browser DOM
 * - Throws Exceptions on error so caller can log nicely
 */

// URL-safe base64 encoder (same as you had)
function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

/**
 * Build a Ghost Admin JWT using EARTHEN_KEY.
 *
 * EARTHEN_KEY must be in the format "{id}:{secret}" and is defined
 * in config/earthen_env.php.
 */
function createGhostJWT() {
    // Prefer the constant, fall back to env if it ever exists there
    $apiKey = defined('EARTHEN_KEY') ? EARTHEN_KEY : getenv('EARTHEN_KEY');

    if (!$apiKey) {
        throw new Exception('EARTHEN_KEY (Ghost Admin API key) not set in environment.');
    }

    $parts = explode(':', $apiKey, 2);
    if (count($parts) !== 2) {
        throw new Exception('EARTHEN_KEY is not in the expected "{id}:{secret}" format.');
    }

    list($id, $secret) = $parts;

    $header = json_encode([
        'typ' => 'JWT',
        'alg' => 'HS256',
        'kid' => $id,
    ]);

    $now = time();
    $payload = json_encode([
        'iat' => $now,
        'exp' => $now + 300, // 5 minutes
        'aud' => '/v4/admin/',
    ]);

    $base64UrlHeader  = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode($payload);

    $signature = hash_hmac(
        'sha256',
        $base64UrlHeader . '.' . $base64UrlPayload,
        hex2bin($secret),
        true
    );

    $base64UrlSignature = base64UrlEncode($signature);

    return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
}

/**
 * Get Ghost member ID for an email, or null if not found.
 */
function getMemberIdByEmail($email) {
    $email_encoded = urlencode($email);
    $ghost_api_url = "https://earthen.io/ghost/api/v4/admin/members/?filter=email:$email_encoded";
    $jwt           = createGhostJWT();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ghost_api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Ghost ' . $jwt,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log("Earthen getMemberIdByEmail cURL error: $curl_err");
        throw new Exception('Error contacting Earthen (curl).');
    }

    if ($http_code < 200 || $http_code >= 300) {
        error_log("Earthen getMemberIdByEmail HTTP $http_code: $response");
        throw new Exception("Earthen API returned HTTP $http_code while looking up member.");
    }

    $data = json_decode($response, true);
    if (
        !isset($data['members']) ||
        !is_array($data['members']) ||
        count($data['members']) === 0
    ) {
        // Not an error: just "user not found"
        return null;
    }

    return $data['members'][0]['id'] ?? null;
}

/**
 * Unsubscribe a user from Earthen by deleting the Ghost member.
 *
 * Throws Exception on error. Returns true on success, false if not found.
 */
function earthenUnsubscribe($email) {
    error_log("Earthen unsubscribe: process initiated for email: $email");

    $member_id = getMemberIdByEmail($email);
    error_log("Earthen unsubscribe: member id for $email is " . ($member_id ?: 'null'));

    if (!$member_id) {
        // Treat "not found" as non-fatal: there's nothing to delete.
        return false;
    }

    $ghost_api_url = "https://earthen.io/ghost/api/v4/admin/members/$member_id/";
    $jwt           = createGhostJWT();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ghost_api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Ghost ' . $jwt,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log("Earthen unsubscribe cURL error: $curl_err");
        throw new Exception('Error contacting Earthen (curl) during unsubscribe.');
    }

    if ($http_code < 200 || $http_code >= 300) {
        error_log("Earthen unsubscribe HTTP $http_code: $response");
        throw new Exception("Earthen API returned HTTP $http_code during unsubscribe.");
    }

    error_log("Earthen unsubscribe: completed successfully for $email (HTTP $http_code)");
    return true;
}
