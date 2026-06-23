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
    return [
        'first_name', 'last_name', 'country_id', 'language_id',
        'continent_code', 'community_id', 'location_full', 'latitude', 'longitude',
        'location_watershed', 'earthling_emoji', 'time_zone',
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
        'country_id'         => (int) $input['country_id'],
        'language_id'        => trim($input['language_id']),
        'birth_date'         => $birth_date,
        'continent_code'     => trim($input['continent_code']),
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

    return ['ok' => true, 'message' => '', 'clean' => $clean];
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
