<?php
/**
 * URL-safe Base64 encode helper.
 *
 * @param string $data
 * @return string
 */
function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

/**
 * Create a Ghost Admin JWT for Earthen (v4 API).
 *
 * Requires EARTHEN_KEY in the format: "{id}:{secret}"
 *
 * @return string
 * @throws Exception if the API key is missing or malformed.
 */
function createGhostJWT() {
    $apiKey = getenv('EARTHEN_KEY');

    if (!$apiKey) {
        // For cron, we throw instead of echoing HTML or exiting.
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
        'exp' => $now + 300,   // 5 minutes
        'aud' => '/v4/admin/', // v4 admin audience
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
 * Look up a Ghost member ID by email via the Earthen Ghost Admin API.
 *
 * @param string $email
 * @return string|null Member ID if found, null if not.
 * @throws Exception on HTTP/cURL/API errors.
 */
function getMemberIdByEmail($email) {
    $email_encoded = urlencode($email);
    $ghost_api_url = "https://earthen.io/ghost/api/v4/admin/members/?filter=email:$email_encoded";
    $jwt = createGhostJWT();

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
        error_log("Earthen getMemberIdByEmail cURL error for {$email}: {$curl_err}");
        throw new Exception('cURL error while fetching member from Earthen.');
    }

    if ($http_code < 200 || $http_code >= 300) {
        error_log("Earthen getMemberIdByEmail HTTP {$http_code} for {$email}: {$response}");
        throw new Exception("Earthen API returned HTTP status {$http_code}.");
    }

    $response_data = json_decode($response, true);

    if (isset($response_data['members'][0]['id'])) {
        $member_id = $response_data['members'][0]['id'];
        error_log("Earthen getMemberIdByEmail: found member {$member_id} for {$email}");
        return $member_id;
    }

    // No member found is not a transport error, just a logical “not found”.
    error_log("Earthen getMemberIdByEmail: no member found for {$email}");
    return null;
}

/**
 * Unsubscribe (actually delete) a member from Earthen/Ghost by email.
 *
 * @param string $email
 * @throws Exception if the user is not found or unsubscribe fails.
 */
function earthenUnsubscribe($email) {
    error_log("Earthen unsubscribe: process initiated for email: {$email}");

    $member_id = getMemberIdByEmail($email);
    error_log("Earthen unsubscribe: member ID retrieved: " . ($member_id ?: 'NULL'));

    if (!$member_id) {
        // For the cron, we treat "not found" as an exception; your caller catches it
        throw new Exception("Earthen unsubscribe: user not found for email {$email}");
    }

    $ghost_api_url = "https://earthen.io/ghost/api/v4/admin/members/{$member_id}/";
    $jwt = createGhostJWT();

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
        error_log("Earthen unsubscribe cURL error for {$email} (member {$member_id}): {$curl_err}");
        throw new Exception("Earthen unsubscribe cURL error for {$email}");
    }

    if ($http_code >= 200 && $http_code < 300) {
        error_log("Earthen unsubscribe: success for {$email}, HTTP {$http_code}");
        return;
    }

    error_log("Earthen unsubscribe HTTP {$http_code} for {$email} (member {$member_id}): {$response}");
    throw new Exception("Earthen unsubscribe failed with HTTP code {$http_code} for {$email}");
}
