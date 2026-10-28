<?php
// One-off: give every account without an open_id the 'buwana_<id>' subject
// that signup and token.php use. CLI only — this file sits in the web root.
//   php tests/backfill.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

chdir(__DIR__);
require_once '../buwanaconn_env.php';

$sql = "UPDATE users_tb SET open_id = CONCAT('buwana_', buwana_id) WHERE open_id IS NULL OR open_id = ''";
if ($buwana_conn->query($sql)) {
    echo "Backfilled open_id for {$buwana_conn->affected_rows} users.\n";
} else {
    echo "Backfill failed: {$buwana_conn->error}\n";
}

$buwana_conn->close();
