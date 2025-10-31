

<?php
require_once "../meta/$page-$lang.php";

$page_key = str_replace('-', '_', $page);
$signup_light_img = $app_info[$page_key . '_top_img_light'] ?? '';
$signup_dark_img = $app_info[$page_key . '_top_img_dark'] ?? '';
?>

<style>
#main {
    height: fit-content;
}

.form-container {
    max-width: 800px;
    margin: 0 auto;
    box-shadow: #0000001f 0px 5px 20px;
}

/*     @media (prefers-color-scheme: light) { */
/*         .app-signup-banner { */
/*             background: url('<?= htmlspecialchars($signup_light_img) ?>?v=2') no-repeat center; */
/*             background-size: contain; */
/*         } */
/*     } */

/*     @media (prefers-color-scheme: dark) { */
/*         .app-signup-banner { */
/*             background: url('<?= htmlspecialchars($signup_dark_img) ?>?v=2') no-repeat center; */
/*             background-size: contain; */
/*         } */
/*     } */

/* .app-signup-banner { */
/*     background: url('<?= htmlspecialchars($signup_light_img) ?>') no-repeat center; */
/*     background-size: contain; */
/* } */

</style>






<?php require_once ("../header-2025.php");?>



