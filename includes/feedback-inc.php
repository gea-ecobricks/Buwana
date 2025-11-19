<?php
$metaPath = dirname(__DIR__) . "/meta/{$page}-{$lang}.php";
if (file_exists($metaPath)) {
    require_once $metaPath;
}
?>

<link rel="stylesheet" href="../styles/jquery.dataTables.css">
<link rel="stylesheet" type="text/css" href="../styles/cs_system.css?v=<?php echo ($version); ?>">

<?php require_once dirname(__DIR__) . "/header-2025.php"; ?>
