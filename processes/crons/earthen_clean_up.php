<?php
ob_start();

// --------------------------------------------------------
// Cron log setup
// --------------------------------------------------------
$log_file = __DIR__ . '/earthen_clean_up.log';

file_put_contents(
    $log_file,
    '[' . date('Y-m-d H:i:s') . "] Earthen cleanup cron started\n",
    FILE_APPEND
);

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

// --------------------------------------------------------
// Includes (cron-safe Ghost helpers)
// --------------------------------------------------------
require_once __DIR__ . '/earthen_cron_helpers.php';

// --------------------------------------------------------
// Small logger helper
// --------------------------------------------------------
function cron_log($msg) {
    global $log_file;
    file_put_contents(
        $log_file,
        '[' . date('Y-m-d H:i:s') . "] " . $msg . "\n",
        FILE_APPEND
    );
}

// --------------------------------------------------------
// Helper: fix common gmail.com typos
// --------------------------------------------------------
/**
 * Fix common gmail.com domain typos.
 *
 * Returns the original email if no safe correction is found.
 */
function fixGmailTypos(string $email): string {
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return $email; // not a normal email format
    }

    [$local, $domain] = $parts;
    $domainLower = strtolower(trim($domain));

    // Already correct
    if ($domainLower === 'gmail.com') {
        return $email;
    }

    // Explicit set of common typo domains
    $commonTypos = [
        'gmali.com',
        'gmial.com',
        'gmale.com',
        'gnail.com',
        'gmai.com',
        'gamil.com',
        'gmaik.com',
        'gmaol.com',
        'gmail.co',
        'gmail.con',
        'gmail.cim',
        'gmail.cm',
        'gmail.comm',
        'gmaill.com',
        'g-mail.com',
        'gmail.cmo',
    ];

    if (in_array($domainLower, $commonTypos, true)) {
        return $local . '@gmail.com';
    }

    // Heuristic: "close enough" to gmail.com
    // Use levenshtein but gate it so we don't accidentally "fix" other domains.
    if (strpos($domainLower, 'gmai') === 0 || strpos($domainLower, 'gmail') === 0) {
        $distance = levenshtein($domainLower, 'gmail.com');
        if ($distance > 0 && $distance <= 2) {
            return $local . '@gmail.com';
        }
    }

    return $email;
}

// --------------------------------------------------------
// Helper: detect SMS-gateway / carrier-email junk
// --------------------------------------------------------
/**
 * Returns true if an email is clearly an SMS-gateway style address
 * that we never want in the Earthen list.
 */
function isSmsGatewayEmail(string $email): bool {
    $email = strtolower(trim($email));
    if (strpos($email, '@') === false) {
        return false;
    }

    // domain = everything after the last "@"
    $domain = substr(strrchr($email, '@'), 1);

    $blockedDomains = [
        'fido.ca',
        'pcs.rogers.com',
        'mymetropcs.com',
        'tmomail.net',
        'vtext.com',
        'txt.att.net',
        'msg.telus.com',
        'email.uscc.net',
    ];

    return in_array($domain, $blockedDomains, true);
}

// --------------------------------------------------------
// Helper: delete Ghost member by id (Admin API)
// --------------------------------------------------------
function deleteGhostMemberById(string $member_id): void {
    $ghost_api_url = "https://earthen.io/ghost/api/v4/admin/members/{$member_id}/";
    $jwt           = createGhostJWT(); // from earthen_cron_helpers.php

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
        throw new Exception("Earthen delete cURL error: {$curl_err}");
    }

    if ($http_code < 200 || $http_code >= 300) {
        throw new Exception("Earthen delete HTTP {$http_code}: {$response}");
    }
}

try {
    // ====================================================
    // 1) CLEAN UP @test.com MEMBERS
    // ====================================================
    $page          = 1;
    $page_size     = 50;
    $total_deleted = 0;
    $total_zombies_deleted = 0; // NEW

    while (true) {
        // Ghost filter syntax for “email contains @test.com”
        $filter = urlencode("email:~'@test.com'");
        $url    = "https://earthen.io/ghost/api/v4/admin/members/?filter={$filter}&limit={$page_size}&page={$page}";

        $jwt = createGhostJWT(); // from earthen_cron_helpers.php

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
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
            cron_log("Error fetching @test.com members (curl): {$curl_err}");
            throw new Exception("Error fetching @test.com members (curl)");
        }

        if ($http_code < 200 || $http_code >= 300) {
            cron_log("Error fetching @test.com members (HTTP {$http_code}): {$response}");
            throw new Exception("Ghost API returned HTTP {$http_code} while listing @test.com members.");
        }

        $data    = json_decode($response, true);
        $members = $data['members'] ?? [];

        if (empty($members)) {
            cron_log("No more @test.com members found on page {$page}. @test.com cleanup complete.");
            break;
        }

        cron_log("Page {$page}: found " . count($members) . " @test.com members.");

        foreach ($members as $member) {
            $member_id = $member['id']    ?? null;
            $email     = $member['email'] ?? '(no-email)';

            if (!$member_id) {
                cron_log("Skipping @test.com member with missing id (email={$email}).");
                continue;
            }

            try {
                deleteGhostMemberById($member_id);
                $total_deleted++;
                cron_log("Deleted Earthen member @test.com {$email} (id={$member_id})");
            } catch (Exception $e) {
                cron_log("Exception while deleting @test.com {$email} (id={$member_id}): " . $e->getMessage());
            }
        }

        $page++;
    }

    cron_log("Earthen @test.com cleanup finished. Total deleted: {$total_deleted}");

    // ====================================================
    // 2) REPAIR MISTYPED GMAIL ADDRESSES
    //    + DELETE SMS-GATEWAY / CARRIER EMAILS
    // ====================================================

    // Deep pass: scan members 5,001–20,000 (pages 51–200)
    $start_page       = 310;
    $end_page         = 510;   // 200 * 100 = 20,000
    $page_size        = 100;
    $total_fixed      = 0;
    $total_sms_deleted = 0;

    cron_log("Gmail/SMS pass: scanning pages {$start_page}–{$end_page}, {$page_size} members per page.");

    for ($page = $start_page; $page <= $end_page; $page++) {
        $url = "https://earthen.io/ghost/api/v4/admin/members/?limit={$page_size}&page={$page}";

        $jwt = createGhostJWT();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
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
            cron_log("Error fetching members for gmail/SMS pass (curl) on page {$page}: {$curl_err}");
            throw new Exception("Error fetching members for gmail/SMS pass (curl)");
        }

        if ($http_code < 200 || $http_code >= 300) {
            cron_log("Error fetching members for gmail/SMS pass (HTTP {$http_code}) on page {$page}: {$response}");
            throw new Exception("Ghost API returned HTTP {$http_code} while listing members for gmail/SMS pass.");
        }

        $data    = json_decode($response, true);
        $members = $data['members'] ?? [];

        if (empty($members)) {
            cron_log("No more members on page {$page} for gmail/SMS pass. Deep pass complete.");
            break;
        }

        cron_log("Gmail/SMS pass: scanning page {$page}, " . count($members) . " members.");

        foreach ($members as $member) {
            $member_id = $member['id']    ?? null;
            $old_email = $member['email'] ?? null;

            if (!$member_id || !$old_email) {
                continue;
            }

            // 2a) DELETE members with no newsletters ("zombies")
            $newsletters = $member['newsletters'] ?? [];
            if (empty($newsletters)) {
                try {
                    deleteGhostMemberById($member_id);
                    $total_zombies_deleted++;
                    cron_log("Deleted zombie member with no newsletters: {$old_email} (id={$member_id})");
                } catch (Exception $e) {
                    cron_log("FAILED to delete zombie member {$old_email} (id={$member_id}): " . $e->getMessage());
                }
                // Don't process SMS/gmail for this member anymore
                continue;
            }

            // 2b) If this is an SMS-gateway email, delete it
            if (isSmsGatewayEmail($old_email)) {
                try {
                    deleteGhostMemberById($member_id);
                    $total_sms_deleted++;
                    cron_log("Deleted SMS-gateway member {$old_email} (id={$member_id})");
                } catch (Exception $e) {
                    cron_log("FAILED to delete SMS-gateway member {$old_email} (id={$member_id}): " . $e->getMessage());
                }
                continue; // don't try to "fix gmail" for these
            }

            // 2c) Otherwise, see if there's a gmail typo to repair
            $new_email = fixGmailTypos($old_email);

            // No change needed
            if ($new_email === $old_email) {
                continue;
            }




            // PUT /members/{id}/ with new email
            try {
                $update_url = "https://earthen.io/ghost/api/v4/admin/members/{$member_id}/";
                $jwt_upd    = createGhostJWT();

                $payload = json_encode([
                    'members' => [
                        [
                            'id'    => $member_id,
                            'email' => $new_email,
                        ]
                    ]
                ]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $update_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Ghost ' . $jwt_upd,
                    'Content-Type: application/json',
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

                $upd_response  = curl_exec($ch);
                $upd_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $upd_curl_err  = curl_error($ch);
                curl_close($ch);

                if ($upd_curl_err) {
                    cron_log("FAILED to fix gmail for {$old_email} (id={$member_id}) – curl error: {$upd_curl_err}");
                    continue;
                }

                if ($upd_http_code < 200 || $upd_http_code >= 300) {
                    // Special case: email already exists → keep the correct one, delete the typo account
                    if ($upd_http_code === 422 && strpos($upd_response, 'Member already exists') !== false) {
                        cron_log("Gmail-fix: {$old_email} -> {$new_email} (id={$member_id}) conflicts with existing member. Deleting typo account.");

                        try {
                            deleteGhostMemberById($member_id);
                            $total_fixed++;
                            cron_log("Deleted duplicate typo member: {$old_email} (id={$member_id}), existing good email={$new_email}");
                            continue;

                        } catch (Exception $e) {
                            cron_log("Exception while deleting duplicate typo member {$old_email} (id={$member_id}): " . $e->getMessage());
                            continue;
                        }
                    }

                    // All other errors: just log and move on
                    cron_log("FAILED to fix gmail for {$old_email} (id={$member_id}) – HTTP {$upd_http_code}: {$upd_response}");
                    continue;
                }

                $total_fixed++;
                cron_log("Fixed gmail typo: {$old_email} → {$new_email} (id={$member_id})");

            } catch (Exception $e) {
                cron_log("Exception while fixing gmail for {$old_email} (id={$member_id}): " . $e->getMessage());
            }
        }
    }

    cron_log("Gmail typo repair finished. Total fixed: {$total_fixed}");
    cron_log("SMS-gateway cleanup finished. Total deleted: {$total_sms_deleted}");

} catch (Exception $e) {
    cron_log("Cron error: " . $e->getMessage());
}

ob_end_clean();
echo json_encode(['status' => 'completed']);
exit();
