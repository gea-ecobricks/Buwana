<?php
$metaPath = dirname(__DIR__) . "/meta/{$page}-{$lang}.php";
if (file_exists($metaPath)) {
    require_once $metaPath;
}
?>

<link rel="stylesheet" href="../styles/jquery.dataTables.css">
<link rel="stylesheet" type="text/css" href="../styles/cs_system.css?v=<?php echo ($version); ?>">

<?php
$headerFile = $headerFile ?? dirname(__DIR__) . '/header-2026.php';
require_once $headerFile;
?>
