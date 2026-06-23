<?php
/**
 * Shared profile validation + update logic for Buwana users_tb.
 *
 * Single source of truth for "what is a valid profile update and how is it
 * written", so the same rules apply to BOTH entry points:
 *   - en/profile_update_process.php  (cookie/session-authenticated Buwana page)
 *   - api/profile_update.php          (Bearer-token client-app API)  [future step]
 *
 * These functions deliberately do NOT handle authentication, authorization or
 * CSRF. The CALLER must establish *who* $buwana_id is (session or verified
 * token) before calling. Here we only validate field VALUES and write them,
 * returning a JSON-ready result array identical in shape to the original
 * handler ('succeeded' | 'failed' + message).
 */

/**
 * Fields that must be present and non-empty in a profile update.
 * (birth_date is intentionally optional.)
 *
 * @return string[]
 */
function profile_required_fields(): array
{
    // country_id + continent_code are intentionally NOT listed: they are derived
    // server-side from location_full (see resolve_country_and_continent) and never
    // trusted from the form. location_full is required so the derivation always
    // has something to work from.
    return [
        'first_name', 'last_name', 'language_id',
        'community_id', 'location_full', 'latitude', 'longitude',
        'location_watershed', 'earthling_emoji', 'time_zone',
    ];
}

/**
 * Resolve a user's country_id + continent_code from their location string.
 *
 * Mirrors the signup flow (en/signup-4_process.php): the country is the last
 * comma-separated component of the Nominatim location string, looked up in
 * countries_tb — which carries the authoritative continent_code per country.
 *
 * @param mysqli $buwana_conn
 * @param string $location_full
 * @return array{country_id:int, continent_code:string}|null  null if unresolved.
 */
function resolve_country_and_continent($buwana_conn, string $location_full): ?array
{
    $parts = explode(',', $location_full);
    $country_name = trim(end($parts));
    if ($country_name === '') {
        return null;
    }

    $sql = "SELECT country_id, continent_code FROM countries_tb WHERE country_name = ? LIMIT 1";
    $stmt = $buwana_conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $country_name);
    $stmt->execute();
    $stmt->bind_result($country_id, $continent_code);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || !$country_id) {
        return null;
    }

    return [
        'country_id'     => (int) $country_id,
        'continent_code' => (string) $continent_code,
    ];
}

/**
 * Validate + normalise raw profile input (e.g. $_POST or a decoded JSON body).
 *
 * @param mysqli $buwana_conn
 * @param array  $input  Raw field values keyed by name.
 * @return array{ok: bool, message: string, clean: array}
 *   ok=true  → clean holds normalised values ready for the update.
 *   ok=false → message holds the human-readable reason.
 */
function validate_profile_input($buwana_conn, array $input): array
{
    // 1) Required fields present and non-empty.
    foreach (profile_required_fields() as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            return ['ok' => false, 'message' => 'Missing required field: ' . $field, 'clean' => []];
        }
    }

    // 2) Sanitise / coerce types (mirrors the original handler exactly).
    $birth_date = $input['birth_date'] ?? null;
    if ($birth_date === '') {
        $birth_date = null;
    }

    $clean = [
        'first_name'         => trim($input['first_name']),
        'last_name'          => trim($input['last_name']),
        'language_id'        => trim($input['language_id']),
        'birth_date'         => $birth_date,
        'community_id'       => (int) $input['community_id'],
        'location_full'      => trim($input['location_full']),
        'latitude'           => (float) $input['latitude'],
        'longitude'          => (float) $input['longitude'],
        'location_watershed' => trim($input['location_watershed']),
        'earthling_emoji'    => trim($input['earthling_emoji']),
        'time_zone'          => trim($input['time_zone']),
    ];

    // 3) community_id must be positive...
    if ($clean['community_id'] <= 0) {
        return ['ok' => false, 'message' => 'Invalid community ID.', 'clean' => []];
    }

    // 4) ...and must exist in communities_tb.
    $sql_check_community = "SELECT community_id FROM communities_tb WHERE community_id = ?";
    $stmt_check = $buwana_conn->prepare($sql_check_community);
    $stmt_check->bind_param("i", $clean['community_id']);
    $stmt_check->execute();
    $stmt_check->store_result();
    $exists = $stmt_check->num_rows > 0;
    $stmt_check->close();

    if (!$exists) {
        return ['ok' => false, 'message' => 'Invalid community ID: Not found in communities_tb.', 'clean' => []];
    }

    // Derive country_id + continent_code from the chosen location. The form's
    // country/continent controls are read-only and intentionally NOT trusted —
    // we always set them from location_full so the same rule applies to the
    // profile API.
    $geo = resolve_country_and_continent($buwana_conn, $clean['location_full']);
    if ($geo === null) {
        return [
            'ok' => false,
            'message' => 'Could not determine your country from the location. Please try re-entering your location and saving one more time.',
            'clean' => [],
        ];
    }
    $clean['country_id']     = $geo['country_id'];
    $clean['continent_code'] = $geo['continent_code'];

    return ['ok' => true, 'message' => '', 'clean' => $clean];
}

/**
 * Read a user's profile — editable fields plus resolved display names and a few
 * read-only account fields. Used by the profile API GET and to echo the fresh
 * state back after an update.
 *
 * @param mysqli $buwana_conn
 * @param int    $buwana_id
 * @return array|null  null if the user doesn't exist.
 */
function get_user_profile($buwana_conn, $buwana_id): ?array
{
    $buwana_id = (int) $buwana_id;
    $sql = "SELECT u.full_name, u.first_name, u.last_name, u.email,
                   u.language_id, u.birth_date, u.earthling_emoji,
                   u.community_id, u.country_id, u.continent_code,
                   u.location_full, u.location_lat, u.location_long,
                   u.location_watershed, u.time_zone,
                   u.created_at, u.account_status, u.role,
                   c.country_name,
                   ct.continent_name_en      AS continent_name,
                   cm.com_name               AS community_name,
                   l.languages_native_name   AS language_name
            FROM users_tb u
            LEFT JOIN countries_tb   c  ON u.country_id     = c.country_id
            LEFT JOIN continents_tb  ct ON u.continent_code = ct.continent_code
            LEFT JOIN communities_tb cm ON u.community_id   = cm.community_id
            LEFT JOIN languages_tb   l  ON u.language_id    = l.language_id
            WHERE u.buwana_id = ?";
    $stmt = $buwana_conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $buwana_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'buwana_id'          => $buwana_id,
        'full_name'          => $row['full_name'],
        'first_name'         => $row['first_name'],
        'last_name'          => $row['last_name'],
        'email'              => $row['email'],                 // read-only
        'language_id'        => $row['language_id'],
        'language_name'      => $row['language_name'],
        'birth_date'         => $row['birth_date'],
        'earthling_emoji'    => $row['earthling_emoji'],
        'community_id'       => $row['community_id'] !== null ? (int) $row['community_id'] : null,
        'community_name'     => $row['community_name'],
        'location_full'      => $row['location_full'],
        'location_lat'       => $row['location_lat'] !== null ? (float) $row['location_lat'] : null,
        'location_long'      => $row['location_long'] !== null ? (float) $row['location_long'] : null,
        'location_watershed' => $row['location_watershed'],
        'time_zone'          => $row['time_zone'],
        // read-only / auto-derived from location
        'country_id'         => $row['country_id'] !== null ? (int) $row['country_id'] : null,
        'country_name'       => $row['country_name'],
        'continent_code'     => $row['continent_code'],
        'continent_name'     => $row['continent_name'],
        // read-only account fields
        'created_at'         => $row['created_at'],
        'account_status'     => $row['account_status'],
        'role'               => $row['role'],
    ];
}

/**
 * Validate the input then update users_tb for $buwana_id.
 *
 * @param mysqli    $buwana_conn
 * @param int       $buwana_id   Authoritative user id (established by the caller).
 * @param array     $input       Raw field values.
 * @return array{status: string, message?: string}
 */
function update_user_profile($buwana_conn, $buwana_id, array $input): array
{
    $validation = validate_profile_input($buwana_conn, $input);
    if (!$validation['ok']) {
        return ['status' => 'failed', 'message' => $validation['message']];
    }

    $f = $validation['clean'];
    $buwana_id = (int) $buwana_id;

    // Local scalars for bind_param (passed by reference).
    $first_name         = $f['first_name'];
    $last_name          = $f['last_name'];
    $country_id         = $f['country_id'];
    $language_id        = $f['language_id'];
    $birth_date         = $f['birth_date'];
    $continent_code     = $f['continent_code'];
    $community_id       = $f['community_id'];
    $location_full      = $f['location_full'];
    $latitude           = $f['latitude'];
    $longitude          = $f['longitude'];
    $location_watershed = $f['location_watershed'];
    $earthling_emoji    = $f['earthling_emoji'];
    $time_zone          = $f['time_zone'];

    $sql_update = "UPDATE users_tb
                   SET first_name = ?, last_name = ?, country_id = ?, language_id = ?, birth_date = ?,
                       continent_code = ?, community_id = ?, location_full = ?,
                       location_lat = ?, location_long = ?, location_watershed = ?, earthling_emoji = ?, time_zone = ?
                   WHERE buwana_id = ?";

    $stmt_update = $buwana_conn->prepare($sql_update);

    if (!$stmt_update) {
        error_log("❌ Statement preparation error: " . $buwana_conn->error);
        return ['status' => 'failed', 'message' => 'Failed to prepare update statement: ' . $buwana_conn->error];
    }

    $stmt_update->bind_param('ssisssissdsssi',
        $first_name,
        $last_name,
        $country_id,
        $language_id,
        $birth_date,
        $continent_code,
        $community_id,
        $location_full,
        $latitude,
        $longitude,
        $location_watershed,
        $earthling_emoji,
        $time_zone,
        $buwana_id
    );

    if ($stmt_update->execute()) {
        $stmt_update->close();
        return ['status' => 'succeeded'];
    }

    $err = $stmt_update->error;
    error_log("❌ Query execution error: " . $err);
    $stmt_update->close();
    return ['status' => 'failed', 'message' => 'Failed to execute update query: ' . $err];
}
