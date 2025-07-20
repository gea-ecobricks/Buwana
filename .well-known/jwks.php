<?php
// 🌐 CORS: Allow trusted origins
$allowedOrigins = [
    "https://earthcal.app",
    "https://gobrik.com",
    "https://ecobricks.org",
    "https://learning.ecobricks.org",
    "https://openbooks.ecobricks.org"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: POST");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "method_not_allowed"]);
    exit;
}

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
        'kid' => $client_id, // Must match the 'kid' set in JWT token header
        'n' => $modulus,
        'e' => $exponent
    ];
}

$stmt->close();
echo json_encode($jwks);
exit;
?>
