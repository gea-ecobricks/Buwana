<?php
require_once __DIR__ . '/helpers.php';

$user = cs_get_current_user($buwana_conn);
$isAdmin = cs_is_admin_user($user);

$input = array_merge($_GET ?? [], $_POST ?? [], cs_get_json_input());
$currentClientId = $input['client_id'] ?? ($_SESSION['client_id'] ?? null);
$currentApp = null;

if ($currentClientId) {
    $stmt = $buwana_conn->prepare('SELECT * FROM apps_tb WHERE client_id = ?');
    if ($stmt) {
        $stmt->bind_param('s', $currentClientId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $currentApp = $result ? $result->fetch_assoc() : null;
        }
        $stmt->close();
    }
}

if ($currentApp && isset($currentApp['app_id'])) {
    $currentApp['app_id'] = intval($currentApp['app_id']);
}

$connectedApps = cs_fetch_connected_apps($buwana_conn, intval($user['buwana_id']));
$chatFilters = [
    'current_user_id' => intval($user['buwana_id']),
    'owner_id' => intval($user['buwana_id']),
];

if (!$isAdmin && $currentApp && isset($currentApp['app_id'])) {
    $chatFilters['app_id'] = intval($currentApp['app_id']);
    $connectedApps = [intval($currentApp['app_id']) => $currentApp];
}

$chats = cs_fetch_chats($buwana_conn, $chatFilters);

$appInboxes = cs_group_chats_by_app($chats, $connectedApps, $currentApp);

$adminPayload = [
    'personal' => [],
    'global' => [],
];

if ($isAdmin) {
    $adminPayload['personal'] = cs_fetch_chats($buwana_conn, [
        'current_user_id' => intval($user['buwana_id']),
        'participant_id' => intval($user['buwana_id']),
    ]);

    $adminPayload['global'] = cs_fetch_chats($buwana_conn, [
        'current_user_id' => intval($user['buwana_id']),
    ]);
}

$meta = [
    'tags' => array_values(cs_get_existing_tags($buwana_conn)),
    'categories' => cs_fetch_categories($buwana_conn),
    'priorities' => ['low', 'medium', 'high', 'urgent'],
    'statuses' => ['open', 'in_progress', 'resolved', 'closed'],
    'support_staff' => cs_fetch_support_staff($buwana_conn),
];

cs_json_response([
    'success' => true,
    'data' => [
        'user' => [
            'id' => intval($user['buwana_id']),
            'first_name' => $user['first_name'],
            'earthling_emoji' => $user['earthling_emoji'],
            'role' => $user['role'],
            'language_id' => intval($user['language_id'] ?? 0),
        ],
        'current_app' => $currentApp,
        'connected_apps' => array_values($connectedApps),
        'app_inboxes' => $appInboxes,
        'admin' => $adminPayload,
        'meta' => $meta,
    ],
]);
