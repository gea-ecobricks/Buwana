<?php
ob_start();

// --------------------------------------------------------
// Cron log setup
// --------------------------------------------------------

$log_file = __DIR__ . '/earthen_clean_up.log';

// Log that the cron started
file_put_contents(
    $log_file,
    '[' . date('Y-m-d H:i:s') . "] Earthen cleanup cron started\n",
    FILE_APPEND
);

// Send PHP errors into the same log file
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

// --------------------------------------------------------
// Includes
// --------------------------------------------------------
//
// This cron scans the Earthen (Ghost) members list for any
// accounts with emails ending in "@test.com" and deletes them.
//

require_once __DIR__ . '/earthen_cron_helpers.php';

// We'll accumulate detailed log messages for this run
$log_messages = [];

try {
    // Make sure EARTHEN_KEY is available (createGhostJWT will throw if not)
    $jwt = createGhostJWT(); // just to validate early
    unset($jwt);             // we'll recreate per request anyway

    $base_url = 'https://earthen.io/ghost/api/v4/admin/members/';
    $limit    = 200;   // reasonable page size
    $page     = 1;
    $test_members = [];

    // ----------------------------------------------------
    // Page through all members and collect @test.com emails
    // ----------------------------------------------------
    do {
        $jwt = createGhostJWT();
        $url = $base_url . '?limit=' . $limit . '&page=' . $page;

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
            $log_messages[] = '[' . date('Y-m-d H:i:s') . "] cURL error while listing members (page {$page}): {$curl_err}";
            throw new Exception('Error contacting Earthen (curl) while listing members.');
        }

        if ($http_code < 200 || $http_code >= 300) {
            $log_messages[] = '[' . date('Y-m-d H:i:s') . "] HTTP {$http_code} while listing members (page {$page}): {$response}";
            throw new Exception("Earthen API returned HTTP {$http_code} while listing members.");
        }

        $data = json_decode($response, true);

        if (!isset($data['members']) || !is_array($data['members'])) {
            // No members array, break out
            break;
        }

        foreach ($data['members'] as $member) {
            $email = $member['email'] ?? '';
            $id    = $member['id'] ?? null;

            if (!$id || !$email) {
                continue;
            }

            // Match any email ending with "@test.com" (case-insensitive)
            if (preg_match('/@test\.com$/i', $email)) {
                $test_members[] = [
                    'id'    => $id,
                    'email' => $email,
                ];
            }
        }

        // Pagination
        $pagination = $data['meta']['pagination'] ?? null;
        if ($pagination && !empty($pagination['next'])) {
            $page = (int)$pagination['next'];
            $has_next = true;
        } else {
            $has_next = false;
        }

    } while ($has_next);

    if (empty($test_members)) {
        $log_messages[] = '[' . date('Y-m-d H:i:s') . "] No @test.com members found in Earthen.";
    } else {
        $log_messages[] = '[' . date('Y-m-d H:i:s') . "] Found " . count($test_members) . " @test.com member(s) to delete.";

        // ------------------------------------------------
        // Delete each @test.com member by id
        // ------------------------------------------------
        foreach ($test_members as $member) {
            $email = $member['email'];
            $id    = $member['id'];

            try {
                $jwt = createGhostJWT();
                $delete_url = $base_url . $id . '/';

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $delete_url);
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
                    $log_messages[] =
                        '[' . date('Y-m-d H:i:s') . "] ERROR deleting member {$email} ({$id}) - cURL error: {$curl_err}";
                    continue;
                }

                if ($http_code < 200 || $http_code >= 300) {
                    $log_messages[] =
                        '[' . date('Y-m-d H:i:s') . "] ERROR deleting member {$email} ({$id}) - HTTP {$http_code}: {$response}";
                    continue;
                }

                $log_messages[] =
                    '[' . date('Y-m-d H:i:s') . "] Deleted Earthen member {$email} ({$id}) successfully (HTTP {$http_code}).";

            } catch (Exception $e) {
                $log_messages[] =
                    '[' . date('Y-m-d H:i:s') . "] EXCEPTION deleting member {$email} ({$id}): " . $e->getMessage();
            }
        }
    }

} catch (Exception $e) {
    $log_messages[] = '[' . date('Y-m-d H:i:s') . "] Cron error: " . $e->getMessage();
}

// Persist log entries for the current run.
if (!empty($log_messages)) {
    file_put_contents($log_file, implode(PHP_EOL, $log_messages) . PHP_EOL, FILE_APPEND);
}

ob_end_clean();
echo json_encode(['status' => 'completed']);
exit();
