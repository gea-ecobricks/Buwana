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

// Small helper to append to this cron's log
function cron_log($msg) {
    global $log_file;
    file_put_contents(
        $log_file,
        '[' . date('Y-m-d H:i:s') . "] " . $msg . "\n",
        FILE_APPEND
    );
}

try {
    $page      = 1;
    $page_size = 50;
    $total_deleted = 0;

    while (true) {
        // Ghost filter syntax for “email contains @test.com”
        // We URL-encode the filter so the quotes don't break the URL.
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
            cron_log("Error fetching test members (curl): {$curl_err}");
            throw new Exception("Error fetching test members (curl)");
        }

        if ($http_code < 200 || $http_code >= 300) {
            cron_log("Error fetching test members (HTTP {$http_code}): {$response}");
            throw new Exception("Ghost API returned HTTP {$http_code} while listing members.");
        }

        $data = json_decode($response, true);
        $members = $data['members'] ?? [];

        // No more members with @test.com – we are done.
        if (empty($members)) {
            cron_log("No more @test.com members found on page {$page}. Cleanup complete.");
            break;
        }

        cron_log("Page {$page}: found " . count($members) . " @test.com members.");

        // Loop through this batch and delete each member
        foreach ($members as $member) {
            $member_id = $member['id']    ?? null;
            $email     = $member['email'] ?? '(no-email)';

            if (!$member_id) {
                cron_log("Skipping member with missing id (email={$email}).");
                continue;
            }

            try {
                // DELETE /members/{id}/
                $delete_url = "https://earthen.io/ghost/api/v4/admin/members/{$member_id}/";
                $jwt_del    = createGhostJWT();

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $delete_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Ghost ' . $jwt_del,
                    'Content-Type: application/json',
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

                $del_response  = curl_exec($ch);
                $del_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $del_curl_err  = curl_error($ch);
                curl_close($ch);

                if ($del_curl_err) {
                    cron_log("FAILED to delete {$email} (id={$member_id}) – curl error: {$del_curl_err}");
                    continue;
                }

                if ($del_http_code < 200 || $del_http_code >= 300) {
                    cron_log("FAILED to delete {$email} (id={$member_id}) – HTTP {$del_http_code}: {$del_response}");
                    continue;
                }

                $total_deleted++;
                cron_log("Deleted Earthen member {$email} (id={$member_id})");

            } catch (Exception $e) {
                cron_log("Exception while deleting {$email} (id={$member_id}): " . $e->getMessage());
            }
        }

        // Move to the next page. Depending on Ghost’s behaviour, newly deleted
        // members may shift pages, but for test cleanup this is fine.
        $page++;
    }

    cron_log("Earthen cleanup cron finished. Total deleted: {$total_deleted}");

} catch (Exception $e) {
    cron_log("Cron error: " . $e->getMessage());
}

ob_end_clean();
echo json_encode(['status' => 'completed']);
exit();
