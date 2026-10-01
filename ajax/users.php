<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\ProfileRight;

header('Content-Type: application/json; charset=UTF-8');

if (!ProfileRight::canViewProfiles()) {
    http_response_code(403);
    echo json_encode(['results' => [], 'count' => 0]);
    return;
}

$result = Dropdown::getDropdownUsers($_POST, false);
if (!is_array($result)) {
    http_response_code(400);
    echo json_encode(['results' => [], 'count' => 0]);
    return;
}

foreach ($result['results'] as &$option) {
    $id = (int) ($option['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $user = new User();
    if ($user->getFromDB($id) && $user->canViewItem()) {
        $option['text'] = sprintf('%s — %s (#%d)', $user->getName(), (string) $user->fields['name'], $id);
        $option['title'] = $option['text'];
    }
}
unset($option);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
