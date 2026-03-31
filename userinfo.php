<?php
require_once 'vendor/autoload.php';
require_once 'buwanaconn_env.php';
require_once __DIR__ . '/includes/security-headers.php';
send_security_headers(true);
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Allow CORS for Earthcal
$allowedOrigins = [
    "https://earthcal.app"
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


header('Content-Type: application/json');

// --- 1️⃣ Get Authorization Bearer token ---
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing or invalid Authorization header']);
    exit;
}

$jwt = $matches[1];

// --- 2️⃣ Parse JWT payload ---
$parts = explode('.', $jwt);
if (count($parts) !== 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JWT format']);
    exit;
}

$payload = json_decode(base64_decode($parts[1]), true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JWT payload']);
    exit;
}

$client_id = $payload['aud'] ?? null;
if (!$client_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing audience (client_id) in JWT']);
    exit;
}

// --- 3️⃣ Lookup public key from DB ---
$stmt = $buwana_conn->prepare("SELECT jwt_public_key FROM apps_tb WHERE client_id = ?");
$stmt->bind_param("s", $client_id);
$stmt->execute();
$stmt->bind_result($public_key);
$stmt->fetch();
$stmt->close();

if (!$public_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Unknown client_id']);
    exit;
}

// --- 4️⃣ Verify JWT signature ---
try {
    $decoded = JWT::decode($jwt, new Key($public_key, 'RS256'));
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token', 'details' => $e->getMessage()]);
    exit;
}

// --- 5️⃣ Check expiration ---
if ($decoded->exp < time()) {
    http_response_code(401);
    echo json_encode(['error' => 'Token expired']);
    exit;
}

// --- 6️⃣ Parse buwana_id from sub ---
$sub = $decoded->sub ?? null;
if (!$sub) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing sub claim']);
    exit;
}

if (strpos($sub, 'buwana_') === 0) {
    $buwana_id_from_sub = intval(str_replace('buwana_', '', $sub));
} elseif (is_numeric($sub)) {
    $buwana_id_from_sub = intval($sub);
} else {
    $buwana_id_from_sub = null;  // sub is open_id UUID style
}

// --- 7️⃣ Always lookup actual buwana_id from users_tb ---
if ($buwana_id_from_sub) {
    $buwana_id = $buwana_id_from_sub; // fallback if numeric sub
} else {
    // Try open_id lookup
    $stmt = $buwana_conn->prepare("SELECT buwana_id FROM users_tb WHERE open_id = ?");
    $stmt->bind_param("s", $sub);
    $stmt->execute();
    $stmt->bind_result($buwana_id);
    $stmt->fetch();
    $stmt->close();

    if (!$buwana_id) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found in database']);
        exit;
    }
}

// --- 8️⃣ Fetch full user info ---
$stmt_user = $buwana_conn->prepare("SELECT email, first_name, earthling_emoji, community_id, continent_code FROM users_tb WHERE buwana_id = ?");
$stmt_user->bind_param("i", $buwana_id);
$stmt_user->execute();
$stmt_user->bind_result($email, $first_name, $earthling_emoji, $community_id, $continent_code);
$stmt_user->fetch();
$stmt_user->close();

// --- 9️⃣ Return full userinfo ---
$response = [
    'sub' => $sub,                     // OpenID spec: never change
    'buwana_id' => $buwana_id,         // ✅ Actual buwana_id (int)
    'email' => $email,
    'given_name' => $first_name,
    'buwana:earthlingEmoji' => $earthling_emoji,
    'buwana:community' => $community_id,
    'buwana:location.continent' => $continent_code
];

echo json_encode($response);
exit;
?>
