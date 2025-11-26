<?php
/**
 * Cron-safe Earthen / Ghost helpers.
 * - No HTML/JS output
 * - No reliance on browser DOM
 * - Throws Exceptions on error so caller can log nicely
 */

// URL-safe base64 encoder
function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

/**
 * Fetch and validate the Ghost Admin API key from the environment.
 *
 * EARTHEN_KEY must be in the format: "{id}:{secret}"
 * - id: any non-empty string (Ghost's key id)
 * - secret: 64 hex chars (256-bit), e.g. /^[0-9a-fA-F]{64}$/
 *
 * @return array [id, secret]
 * @throws Exception on any format/validation problem
 */
function getEarthenAdminKey() {
    $raw = getenv('EARTHEN_KEY');

    if (!$raw) {
        throw new Exception('EARTHEN_KEY (Ghost Admin API key) not set in environment.');
    }

    $raw = trim($raw);

    // How many colons?
    $colonCount = substr_count($raw, ':');
    if ($colonCount !== 1) {
        throw new Exception(
            "EARTHEN_KEY must be in the format \"id:secret\" with exactly 1 colon; found {$colonCount}."
        );
    }

    // Split into id and secret
    list($id, $secret) = explode(':', $raw, 2);
    $id     = trim($id);
    $secret = trim($secret);

    if ($id === '' || $secret === '') {
        throw new Exception('EARTHEN_KEY id or secret is empty after trimming.');
    }

    // Secret must be hex only
    if (!preg_match('/^[0-9a-fA-F]+$/', $secret)) {
        $len = strlen($secret);
        throw new Exception(
            "EARTHEN_KEY secret contains non-hex characters (length {$len}). " .
            "Make sure you copied the **Admin API key** exactly from Ghost."
        );
    }

    // Length must be even (Ghost secrets are typically 64 hex chars)
    $len = strlen($secret);
    if ($len % 2 !== 0) {
        throw new Exception(
            "EARTHEN_KEY secret length ({$len}) is not even. " .
            "It should typically be 64 hex characters. Check for a missing or extra character."
        );
    }

    // For debugging without leaking the actual key

    return [$id, $secret];
}

/**
 * Build a Ghost Admin JWT using EARTHEN_KEY.
 *
 * @return string JWT
 * @throws Exception if key is missing/invalid
 */
function createGhostJWT() {
    list($id, $secret) = getEarthenAdminKey();

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
