
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$ecobricker_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ecobricker_id > 0) {
    require_once '../gobrikconn_env.php';

    $stmt = $gobrik_conn->prepare('DELETE FROM tb_ecobrickers WHERE ecobricker_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $ecobricker_id);
        $stmt->execute();
        $stmt->close();
    }

    $gobrik_conn->close();
}

header('Location: https://gobrik.com/en/goodbye.php');
exit();
?>
