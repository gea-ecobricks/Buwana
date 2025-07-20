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
    header("Access-Control-Allow-Methods: GET");
}

header('Content-Type: application/json');

$issuer = 'https://buwana.ecobricks.org';

echo json_encode([
    'issuer' => $issuer,
    'authorization_endpoint' => "$issuer/authorize",
    'token_endpoint' => "$issuer/token",
    'userinfo_endpoint' => "$issuer/userinfo",
    'jwks_uri' => "$issuer/.well-known/jwks.php",
    'response_types_supported' => ['code'],
    'subject_types_supported' => ['public'],
    'id_token_signing_alg_values_supported' => ['RS256'],
    'scopes_supported' => [
        'openid', 'email', 'profile',
        'buwana:earthlingEmoji', 'buwana:community', 'buwana:location.continent'
    ],
    'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
    'code_challenge_methods_supported' => ['plain', 'S256'],
    'claims_supported' => [
        'sub', 'email', 'given_name',
        'buwana:earthlingEmoji', 'buwana:community', 'buwana:location.continent'
    ]
]);
?>
