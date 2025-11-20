<?php
session_start();

require_once '../fetch_app_info.php';

$lang = basename(dirname($_SERVER['SCRIPT_NAME']));
$page = 'team';
$version = '0.1';
$lastModified = date('Y-m-d\TH:i:s\Z', filemtime(__FILE__));

$teamDataPath = __DIR__ . '/../meta/team-profiles.json';
$team_members = [];

if (file_exists($teamDataPath)) {
    $json = file_get_contents($teamDataPath);
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $team_members = $decoded;
    }
}

if (!$team_members) {
    $team_members = [
        [
            'name' => 'Buwana Earthling',
            'full_name' => 'Buwana Earthling',
            'title' => 'Volunteer Steward',
            'earthling_emoji' => '🌍',
            'continent' => 'Global',
            'watershed' => 'Planetary Watershed',
            'profile_text' => 'Our distributed team of Earthlings tends to the EarthenAuth protocol and the regenerative ecosystem it supports.',
            'profile_pic' => '../webps/ecobrick-team-blank.webp',
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <?php require_once("../meta/team-en.php"); ?>
    <style>
        .team-page {
            padding-top: 30px;
        }
        .team-hero {
            text-align: center;
            margin-bottom: 30px;
        }
        .team-hero h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            color: var(--h1);
        }
        .team-hero p {
            margin: 0;
            color: var(--subdued-text);
        }
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
        }
        .team-card {
            background: var(--form-field-background);
            border: 1px solid var(--outline);
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
        }
        .team-card__avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--outline);
            margin: 0 auto 12px auto;
            background: var(--lighter);
        }
        .team-card__avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .team-card__name {
            font-size: 1.3rem;
            margin: 0 0 12px 0;
            color: var(--h1);
        }
        .team-card__details {
            background: var(--lighter);
            border-radius: 14px;
            padding: 12px;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        .team-card__full-name {
            font-weight: 600;
            margin: 0;
        }
        .team-card__title,
        .team-card__location {
            margin: 4px 0;
            color: var(--subdued-text);
        }
        .team-card__bio {
            text-align: left;
            font-size: 0.95rem;
            color: var(--text-color);
            margin: 0;
        }
        @media (max-width: 640px) {
            .team-card {
                padding: 20px;
            }
            .team-card__avatar {
                width: 100px;
                height: 100px;
            }
        }
    </style>
    <?php require_once("../header-2025.php"); ?>
    <div id="form-submission-box" class="landing-page-form">
        <div class="form-container team-page">
            <div class="team-hero">
                <p class="login-status">Meet the Earthlings</p>
                <h1>The Buwana Team</h1>
                <p>Profiles of the humans behind the EarthenAuth protocol.</p>
            </div>
            <section class="team-grid">
                <?php foreach ($team_members as $member):
                    $avatar = !empty($member['profile_pic']) ? $member['profile_pic'] : '../webps/ecobrick-team-blank.webp';
                ?>
                    <article class="team-card">
                        <div class="team-card__avatar">
                            <img src="<?= htmlspecialchars($avatar) ?>" alt="Profile picture of <?= htmlspecialchars($member['full_name'] ?? $member['name']) ?>">
                        </div>
                        <h3 class="team-card__name"><?= htmlspecialchars($member['name'] ?? $member['full_name'] ?? 'Earthling') ?></h3>
                        <div class="team-card__details">
                            <p class="team-card__full-name"><?= htmlspecialchars($member['full_name'] ?? '') ?></p>
                            <p class="team-card__title"><?= htmlspecialchars(($member['earthling_emoji'] ?? '') . ' ' . ($member['title'] ?? '')) ?></p>
                            <p class="team-card__location">
                                <?= htmlspecialchars(($member['continent'] ?? 'Unknown continent') . ' • ' . ($member['watershed'] ?? 'Unknown watershed')) ?>
                            </p>
                        </div>
                        <p class="team-card__bio">
                            <?= nl2br(htmlspecialchars($member['profile_text'] ?? '')) ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    </div>
    <?php require_once("../footer-2025.php"); ?>
    <?php require_once("../scripts/app_modals.php"); ?>
</body>
</html>
