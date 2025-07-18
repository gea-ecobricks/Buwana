<?php
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
