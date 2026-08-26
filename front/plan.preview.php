<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Mapping\FieldMappingRepository;
use GlpiPlugin\Ticketmigration\Mapping\ValueMappingRepository;
use GlpiPlugin\Ticketmigration\Mapping\LocationEntityMappingRepository;
use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\Plan\MigrationPlanBuilder;
use GlpiPlugin\Ticketmigration\Plan\EntityContextProvider;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;

if (!ProfileRight::canViewProfiles()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}
$profileId = (int) ($_GET['profiles_id'] ?? 0);
$profile = new MigrationProfile();
if (!$profile->getFromDB($profileId) || !$profile->canViewItem()) {
    Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration'));
}
if (($profile->fields['workflow_step'] ?? '') !== MigrationProfile::STEP_VALUES_CONFIGURED) {
    Session::addMessageAfterRedirect(__('Complete value correspondence before previewing a migration plan.', 'ticketmigration'), false, ERROR);
    Html::redirect(WebUrl::front('value.form.php') . '?profiles_id=' . $profileId);
}
$source = new SourceFile();
if (!$source->getFromDB((int) $profile->fields['sourcefiles_id']) || !$source->canViewItem()) {
    Html::displayErrorAndDie(__('The active CSV source is unavailable.', 'ticketmigration'));
}
$config = json_decode((string) $source->fields['csv_config'], true) ?: [];
$reader = new CsvReader($source->getProtectedPath(), new CsvConfiguration(
    delimiter: (string) ($config['delimiter'] ?? ';'), hasHeader: (bool) ($config['has_header'] ?? true), encoding: (string) ($config['encoding'] ?? 'UTF-8'),
));
$requestedOffset = min(1000000, max(0, (int) ($_GET['row_offset'] ?? 0)));
$window = $reader->rowWindow($requestedOffset);
$row = $window['row'];
$profileOptions = json_decode((string) ($profile->fields['options'] ?? ''), true) ?: [];
$valueMappings = (new ValueMappingRepository())->forProfile($profileId);
$fieldMappings = (new FieldMappingRepository())->forProfile($profileId);
$locationEntityMappings = (new LocationEntityMappingRepository())->forProfile($profileId);
$entityContext = (new EntityContextProvider())->build((int) $profile->fields['entities_id'], $valueMappings, $locationEntityMappings);
$plan = $row ? (new MigrationPlanBuilder())->build(
    $row,
    $fieldMappings,
    $valueMappings,
    $reader->columns(),
    (array) ($profileOptions['description_consolidation'] ?? []),
    (array) ($profileOptions['actor_resolution'] ?? []),
    (array) ($profileOptions['title_fallback'] ?? []),
    $entityContext,
) : null;
$sourceValues = [];
foreach ($fieldMappings as $sourceIndex => $mapping) {
    $targetKey = (string) ($mapping['target_key'] ?? '');
    if ($targetKey !== '' && $row !== null) {
        $sourceValues[$targetKey] = (string) ($row->value((int) $sourceIndex) ?? '');
    }
}
$referenceLabel = static function (string $itemtype, int $id): string {
    if (!is_a($itemtype, CommonDBTM::class, true)) {
        return sprintf('#%d', $id);
    }
    $item = new $itemtype();
    if (!$item->getFromDB($id) || !$item->canViewItem()) {
        return sprintf('#%d', $id);
    }
    if ($itemtype === 'User') {
        return sprintf('%s — %s (#%d)', $item->getName(), (string) $item->fields['name'], $id);
    }
    return sprintf('%s (#%d)', $item->getName(), $id);
};
$summary = ['requesters' => [], 'assignees' => [], 'location' => null, 'entity' => null, 'entity_origin' => ''];
if ($plan !== null) {
    foreach ((array) ($plan->actors['requester'] ?? []) as $actor) {
        $summary['requesters'][] = $referenceLabel((string) $actor['itemtype'], (int) $actor['id']);
    }
    foreach ((array) ($plan->actors['assignee'] ?? []) as $actor) {
        $summary['assignees'][] = $referenceLabel((string) $actor['itemtype'], (int) $actor['id']);
    }
    if (isset($plan->ticket['location']['id'])) {
        $summary['location'] = $referenceLabel('Location', (int) $plan->ticket['location']['id']);
    }
    if (isset($plan->ticket['entity']['id'])) {
        $entityId = (int) $plan->ticket['entity']['id'];
        $summary['entity'] = $referenceLabel('Entity', $entityId);
        $locationId = (int) ($plan->ticket['location']['id'] ?? 0);
        if (($sourceValues['ticket.entity'] ?? '') !== '') {
            $summary['entity_origin'] = __('Explicit CSV entity mapping', 'ticketmigration');
        } elseif (count(array_filter((array) ($plan->actors['requester'] ?? []), static function (array $requester) use ($entityContext, $entityId): bool {
            $requesterId = (int) ($requester['id'] ?? 0);
            $preferred = ($entityContext['user_preferred_entities'] ?? [])[$requesterId] ?? null;
            $authorizations = (array) (($entityContext['user_entities'] ?? [])[$requesterId] ?? []);
            return (int) $preferred === $entityId || (count($authorizations) === 1 && (int) $authorizations[0] === $entityId);
        })) > 0) {
            $summary['entity_origin'] = __('Requester GLPI authorization', 'ticketmigration');
        } elseif ($locationId > 0 && (($entityContext['location_entities'][$locationId] ?? null) === $entityId)) {
            $summary['entity_origin'] = match ($entityContext['location_entity_sources'][$locationId] ?? '') {
                'profile_mapping' => __('Migration profile location/entity mapping', 'ticketmigration'),
                'hierarchy_name' => __('Exact location hierarchy match', 'ticketmigration'),
                default => __('Entity owning the resolved location', 'ticketmigration'),
            };
        } else {
            $summary['entity_origin'] = __('Requester entity or migration profile default', 'ticketmigration');
        }
    }
}
$planUrl = WebUrl::front('plan.preview.php') . '?profiles_id=' . $profileId . '&row_offset=';
Html::header(__('Migration plan preview', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/plan/preview.html.twig', [
    'profile' => $profile->fields, 'source' => $source->fields, 'row' => $row, 'plan' => $plan,
    'summary' => $summary, 'source_values' => $sourceValues, 'row_position' => $window['offset'] + 1,
    'previous_url' => $window['previous_offset'] !== null ? $planUrl . $window['previous_offset'] : null,
    'next_url' => $window['next_offset'] !== null ? $planUrl . $window['next_offset'] : null,
    'plan_json' => $plan ? json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
    'values_url' => WebUrl::front('value.form.php') . '?profiles_id=' . $profileId,
]);
Html::footer();
