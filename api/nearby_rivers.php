<?php
// Named rivers and streams within 5 km of a point, from OpenStreetMap's
// Overpass API. Used by en/signup-4.php and en/edit-profile.php.
//
// The browser used to call Overpass directly, but since 2026 the public
// Overpass servers ban clients that don't follow the OSM free-service rules
// (HTTP 406) and are often overloaded (502/504). This proxy follows them:
//   - identifying User-Agent (no stock/faked UA) and a Referer
//   - one request at a time from Buwana (a lock serializes concurrent users)
//   - results cached for 30 days, so repeat lookups never reach Overpass
//   - rate-limited per visitor and globally
//   - if a server refuses us (406/429), we back off for a while instead of
//     moving on to another server to dodge the limit
//   - a second, independently run instance is used only when the primary is
//     down (timeout / 5xx), never to spread load
//
// GET ?lat=..&lon=..  →  {"elements": [{"tags": {"name": ".."}, "geometry": [{"lat":..,"lon":..}, ..]}, ..]}

header('Content-Type: application/json');

function rivers_fail(int $status, string $error): void {
    http_response_code($status);
    echo json_encode(['error' => $error]);
    exit();
}

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
if ($lat === false || $lat === null || $lon === false || $lon === null
    || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    rivers_fail(400, 'invalid_coordinates');
}

// ~100 m precision: plenty for a 5 km search, and lets nearby users share a cache entry
$lat = round($lat, 3);
$lon = round($lon, 3);

$state_dir = sys_get_temp_dir() . '/buwana_rivers';
if (!is_dir($state_dir)) {
    @mkdir($state_dir, 0700, true);
}

$cache_file = "{$state_dir}/cache_{$lat}_{$lon}.json";
$cache_ttl = 30 * 24 * 3600; // rivers rarely change
if (is_readable($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    readfile($cache_file);
    exit();
}

// --- Per-visitor rate limit: 10 uncached lookups per hour per IP ---
$ip_file = "{$state_dir}/ip_" . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.json';
$now = time();
$ip_hits = array_filter(
    json_decode((string) @file_get_contents($ip_file), true) ?: [],
    function ($t) use ($now) { return $t > $now - 3600; }
);
if (count($ip_hits) >= 10) {
    rivers_fail(429, 'rate_limited');
}
$ip_hits[] = $now;
@file_put_contents($ip_file, json_encode(array_values($ip_hits)), LOCK_EX);

// --- Back-off after a server refused us ---
$cooldown_file = "{$state_dir}/cooldown";
if (is_readable($cooldown_file) && (int) @file_get_contents($cooldown_file) > $now) {
    rivers_fail(503, 'overpass_cooldown');
}

// --- One Overpass request at a time from Buwana ---
$lock = fopen("{$state_dir}/lock", 'c');
$waited = 0;
while ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
    if ($waited >= 20) {
        rivers_fail(503, 'overpass_busy');
    }
    sleep(1);
    $waited++;
}

// Another visitor may have fetched this spot while we waited
if (is_readable($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    readfile($cache_file);
    exit();
}

// At most one Overpass call every 2 seconds overall
$last_call_file = "{$state_dir}/last_call";
$since_last = microtime(true) - (float) @file_get_contents($last_call_file);
if ($since_last < 2) {
    usleep((int) ((2 - $since_last) * 1e6));
}

// Relations span entire watersheds (can be hundreds of km of geometry) and are
// the main cause of timeouts. Way elements are sufficient for local rivers.
$query = "[out:json][timeout:20];("
       . "way[\"waterway\"=\"river\"](around:5000,{$lat},{$lon});"
       . "way[\"waterway\"=\"stream\"](around:5000,{$lat},{$lon});"
       . ");out tags geom;";

$endpoints = [
    'https://overpass-api.de/api/interpreter',              // primary (FOSSGIS)
    'https://maps.mail.ru/osm/tools/overpass/api/interpreter', // independent instance, outage fallback only
];

foreach ($endpoints as $endpoint) {
    file_put_contents($last_call_file, (string) microtime(true), LOCK_EX);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['data' => $query]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'Buwana/1.0 (+https://buwana.ecobricks.org; support@gobrik.com)',
        CURLOPT_REFERER        => 'https://buwana.ecobricks.org/',
    ]);
    $body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if (in_array($http_code, [403, 406, 429], true)) {
        // We've been refused or rate-limited: respect it and pause all lookups.
        file_put_contents($cooldown_file, (string) ($now + 15 * 60), LOCK_EX);
        error_log("nearby_rivers: {$endpoint} refused us (HTTP {$http_code}); pausing lookups for 15 min");
        rivers_fail(503, 'overpass_cooldown');
    }

    $data = ($http_code === 200 && $body) ? json_decode($body, true) : null;
    if (!is_array($data) || !isset($data['elements']) || !is_array($data['elements'])) {
        error_log("nearby_rivers: {$endpoint} unavailable (HTTP {$http_code}" . ($curl_err ? ", {$curl_err}" : '') . ')');
        continue; // server down or overloaded: try the outage fallback
    }

    // Only named waterways with geometry; drop everything else to keep the response small
    $elements = [];
    foreach ($data['elements'] as $el) {
        $name = $el['tags']['name'] ?? '';
        if ($name === '' || empty($el['geometry'])) {
            continue;
        }
        $elements[] = ['tags' => ['name' => $name], 'geometry' => $el['geometry']];
    }

    $json = json_encode(['elements' => $elements]);
    @file_put_contents($cache_file, $json, LOCK_EX);
    echo $json;
    exit();
}

rivers_fail(502, 'overpass_unavailable');
