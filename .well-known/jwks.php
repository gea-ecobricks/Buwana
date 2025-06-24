<?php
header('Content-Type: application/json');
require_once '../buwanaconn_env.php';

// Prepare JWKS array
$jwks = ['keys' => []];

// Fetch all apps with a public key
$stmt = $buwana_conn->prepare("SELECT client_id, jwt_public_key FROM apps_tb WHERE jwt_public_key IS NOT NULL");
$stmt->execute();
$stmt->bind_result($client_id, $publicKey);

while ($stmt->fetch()) {
    $details = openssl_pkey_get_details(openssl_pkey_get_public($publicKey));
    if (!$details || !isset($details['rsa'])) {
        continue; // Skip any invalid keys
    }
    $keyData = $details['rsa'];

    // Base64 URL-safe encoding
    $modulus = rtrim(strtr(base64_encode($keyData['n']), '+/', '-_'), '=');
    $exponent = rtrim(strtr(base64_encode($keyData['e']), '+/', '-_'), '=');

    // Build key entry
    $jwks['keys'][] = [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $client_id,
        'n' => $modulus,
        'e' => $exponent
    ];
}

$stmt->close();
echo json_encode($jwks);
exit;
?>
