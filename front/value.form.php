<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Mapping\DistinctValueCollector;
use GlpiPlugin\Ticketmigration\Mapping\FieldMappingRepository;
use GlpiPlugin\Ticketmigration\Mapping\GlpiValueOptions;
use GlpiPlugin\Ticketmigration\Mapping\TargetRegistry;
use GlpiPlugin\Ticketmigration\Mapping\ValueMappingRepository;
use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;

if (!ProfileRight::canViewProfiles()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}
$profileId = (int) ($_REQUEST['profiles_id'] ?? 0);
$profile = new MigrationProfile();
if (!$profile->getFromDB($profileId) || !$profile->canViewItem()) {
    Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration'));
}
$source = new SourceFile();
if (!$source->getFromDB((int) ($profile->fields['sourcefiles_id'] ?? 0)) || !$source->canViewItem()) {
    Html::displayErrorAndDie(__('Select a CSV source before configuring value mappings.', 'ticketmigration'));
}
$fieldRepository = new FieldMappingRepository();
if ($fieldRepository->missingRequiredTargets($profileId) !== []) {
    Session::addMessageAfterRedirect(__('Complete the required field mappings first.', 'ticketmigration'), false, ERROR);
    Html::redirect(WebUrl::front('mapping.form.php') . '?profiles_id=' . $profileId);
}
$config = json_decode((string) ($source->fields['csv_config'] ?? ''), true) ?: [];
$reader = new CsvReader($source->getProtectedPath(), new CsvConfiguration(
    delimiter: (string) ($config['delimiter'] ?? ';'),
    hasHeader: (bool) ($config['has_header'] ?? true),
    encoding: (string) ($config['encoding'] ?? 'UTF-8'),
));
$definitions = TargetRegistry::valueMappedDefinitions();
$fieldMappings = array_filter(
    $fieldRepository->forProfile($profileId),
    static fn (array $mapping): bool => isset($definitions[(string) $mapping['target_key']]),
);
$multiDelimiters = [];
foreach ($fieldMappings as $index => $mapping) {
    $mappingConfiguration = json_decode((string) ($mapping['configuration'] ?? ''), true) ?: [];
    if (str_starts_with((string) $mapping['target_key'], 'actor.')) {
        $multiDelimiters[$index] = $mappingConfiguration['multi_delimiter'] ?? 'auto';
    }
}
$sets = (new DistinctValueCollector())->collect($reader, array_keys($fieldMappings), 200, $multiDelimiters);
$allowed = [];
foreach ($fieldMappings as $index => $mapping) {
    foreach ($sets[$index]->values as $value) {
        $allowed[(string) $mapping['target_key']][hash('sha256', $value)] = $value;
    }
}
$valueRepository = new ValueMappingRepository();
$profileOptions = json_decode((string) ($profile->fields['options'] ?? ''), true) ?: [];
$savedSkipTargets = (array) ($profileOptions['actor_resolution']['skip_unresolved_targets'] ?? []);
if (isset($_POST['save_values'])) {
    if (!$profile->canUpdateItem()) {
        Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
    }
    $decisions = [];
    $seen = [];
    $skipUnresolvedTargets = [];
    foreach ((array) ($_POST['skip_unresolved_targets'] ?? []) as $targetKey) {
        foreach ($fieldMappings as $index => $fieldMapping) {
            if (($fieldMapping['target_key'] ?? '') === $targetKey
                && str_starts_with((string) $targetKey, 'actor.')
                && $sets[$index]->truncated) {
                $skipUnresolvedTargets[] = (string) $targetKey;
                break;
            }
        }
    }
    $skipUnresolvedTargets = array_values(array_unique($skipUnresolvedTargets));
    $resolutions = (array) ($_POST['resolution'] ?? []);
    foreach ((array) ($_POST['mapping_key'] ?? []) as $position => $mappingKey) {
        $sourceValue = (string) (($_POST['source_value'] ?? [])[$position] ?? '');
        $resolution = (string) ($resolutions[$position] ?? '');
        if (!isset($allowed[$mappingKey][hash('sha256', $sourceValue)])) {
            Session::addMessageAfterRedirect(__('Every discovered source value must be explicitly resolved or ignored.', 'ticketmigration'), false, ERROR);
            Html::redirect(WebUrl::front('value.form.php') . '?profiles_id=' . $profileId);
        }
        if (in_array($mappingKey, $skipUnresolvedTargets, true)) {
            continue;
        }
        if ($resolution === '') {
            Session::addMessageAfterRedirect(__('Every discovered source value must be explicitly resolved or ignored.', 'ticketmigration'), false, ERROR);
            Html::redirect(WebUrl::front('value.form.php') . '?profiles_id=' . $profileId);
        }
        $decisionKey = $mappingKey . ':' . hash('sha256', $sourceValue);
        if (isset($seen[$decisionKey])) {
            Html::displayErrorAndDie(__('Duplicate value mapping submission.', 'ticketmigration'));
        }
        $seen[$decisionKey] = true;
        $decision = ['mapping_key' => $mappingKey, 'source_value' => $sourceValue, 'target_itemtype' => '', 'target_id' => 0, 'target_value' => ''];
        if ($resolution === 'ignore') {
            $decision['target_value'] = '__ignore__';
        } elseif (str_starts_with($resolution, 'value:')) {
            $decision['target_value'] = substr($resolution, 6);
        } elseif ($resolution === 'manual') {
            $definition = $definitions[$mappingKey];
            $manualKey = hash('sha256', $mappingKey . "\0" . $sourceValue);
            $manualId = (int) (($_POST['manual_reference'] ?? [])[$manualKey] ?? 0);
            $itemtype = (string) ($definition['itemtype'] ?? '');
            $referencedItem = $itemtype !== '' ? new $itemtype() : null;
            if ($manualId <= 0 || $referencedItem === null || !$referencedItem->getFromDB($manualId) || !$referencedItem->canViewItem()) {
                Html::displayErrorAndDie(__('Select a valid GLPI reference from the full list.', 'ticketmigration'));
            }
            $decision['target_itemtype'] = $itemtype;
            $decision['target_id'] = $manualId;
        } elseif (preg_match('/^ref:([A-Za-z_\\]+):(\d+)$/', $resolution, $matches)) {
            $definition = $definitions[$mappingKey];
            if (($definition['itemtype'] ?? '') !== $matches[1]) {
                Html::displayErrorAndDie(__('Invalid GLPI reference selection.', 'ticketmigration'));
            }
            $referencedItem = new $matches[1]();
            if (!$referencedItem->getFromDB((int) $matches[2]) || !$referencedItem->canViewItem()) {
                Html::displayErrorAndDie(__('Invalid GLPI reference selection.', 'ticketmigration'));
            }
            $decision['target_itemtype'] = $matches[1];
            $decision['target_id'] = (int) $matches[2];
        } else {
            Html::displayErrorAndDie(__('Invalid value mapping selection.', 'ticketmigration'));
        }
        $decisions[] = $decision;
    }
    $expectedCount = 0;
    foreach ($allowed as $targetKey => $values) {
        if (!in_array($targetKey, $skipUnresolvedTargets, true)) {
            $expectedCount += count($values);
        }
    }
    if (count($decisions) !== $expectedCount) {
        Session::addMessageAfterRedirect(__('Every discovered source value must be explicitly resolved or ignored.', 'ticketmigration'), false, ERROR);
        Html::redirect(WebUrl::front('value.form.php') . '?profiles_id=' . $profileId);
    }
    $valueRepository->replace($profileId, $decisions);
    $truncated = array_filter($sets, static fn ($set, $index): bool => $set->truncated && !in_array((string) $fieldMappings[$index]['target_key'], $skipUnresolvedTargets, true), ARRAY_FILTER_USE_BOTH);
    $profileOptions['actor_resolution']['skip_unresolved_targets'] = $skipUnresolvedTargets;
    $analysis = ['source_id' => (int) $source->getID(), 'filename' => (string) $source->fields['source_filename'], 'total' => 0, 'automatic' => 0, 'remaining' => 0, 'by_target' => []];
    $analysisProvider = new GlpiValueOptions();
    foreach ($fieldMappings as $index => $fieldMapping) {
        $targetKey = (string) $fieldMapping['target_key'];
        $definition = $definitions[$targetKey];
        $total = count($sets[$index]->values);
        $automatic = 0;
        if ($definition['value_kind'] === 'reference') {
            foreach ($sets[$index]->values as $sourceValue) {
                if (count($analysisProvider->exactReferences($definition['itemtype'], $sourceValue)) === 1) {
                    $automatic++;
                }
            }
        }
        $analysis['total'] += $total;
        $analysis['automatic'] += $automatic;
        $analysis['remaining'] += $total - $automatic;
        $analysis['by_target'][$targetKey] = ['label' => $definition['label'], 'total' => $total, 'automatic' => $automatic, 'remaining' => $total - $automatic];
    }
    $profileOptions['last_value_analysis'] = $analysis;
    global $DB;
    $DB->update(MigrationProfile::getTable(), [
        'workflow_step' => $truncated === [] ? MigrationProfile::STEP_VALUES_CONFIGURED : MigrationProfile::STEP_MAPPING_CONFIGURED,
        'is_ready' => 0,
        'options' => json_encode($profileOptions, JSON_THROW_ON_ERROR),
    ], ['id' => $profileId]);
    Session::addMessageAfterRedirect(
        $truncated === [] ? __('Value mappings saved.', 'ticketmigration') : __('Value mappings saved, but the distinct-value limit was reached.', 'ticketmigration'),
        false,
        $truncated === [] ? INFO : WARNING,
    );
    Html::redirect(WebUrl::front('value.form.php') . '?profiles_id=' . $profileId);
}
$saved = $valueRepository->forProfile($profileId);
$optionProvider = new GlpiValueOptions();
$fields = [];
$statistics = ['total' => 0, 'automatic' => 0, 'remaining' => 0];
$position = 0;
foreach ($fieldMappings as $index => $mapping) {
    $targetKey = (string) $mapping['target_key'];
    $definition = $definitions[$targetKey];
    $rows = [];
    $automaticRows = [];
    foreach ($sets[$index]->values as $value) {
        $hash = hash('sha256', $value);
        $options = [];
        if ($definition['value_kind'] === 'reference') {
            foreach ($optionProvider->exactReferences($definition['itemtype'], $value) as $id => $label) {
                $options['ref:' . $definition['itemtype'] . ':' . $id] = $label;
            }
        } else {
            foreach ($optionProvider->enum($definition['value_kind']) as $id => $label) {
                $options['value:' . $id] = $label;
            }
        }
        $selected = '';
        if (isset($saved[$targetKey][$hash])) {
            $entry = $saved[$targetKey][$hash];
            $selected = $entry['target_itemtype']
                ? 'ref:' . $entry['target_itemtype'] . ':' . $entry['target_id']
                : ($entry['target_value'] === '__ignore__' ? 'ignore' : 'value:' . $entry['target_value']);
            if ($entry['target_itemtype'] && !isset($options[$selected])) {
                $selected = 'manual';
            }
        } elseif (count($options) === 1) {
            $selected = (string) array_key_first($options);
        }
        $isAutomatic = $definition['value_kind'] === 'reference'
            && count($options) === 1
            && $selected === (string) array_key_first($options);
        $manualDropdown = '';
        if ($definition['value_kind'] === 'reference') {
            $savedId = isset($saved[$targetKey][$hash]) ? (int) ($saved[$targetKey][$hash]['target_id'] ?? 0) : 0;
            $manualKey = hash('sha256', $targetKey . "\0" . $value);
            $dropdownOptions = [
                'name' => 'manual_reference[' . $manualKey . ']',
                'value' => $savedId,
                'display' => false,
                'width' => '100%',
                'entity' => (int) $profile->fields['entities_id'],
                'entity_sons' => (bool) $profile->fields['is_recursive'],
                'placeholder' => sprintf(__('Search all GLPI %s', 'ticketmigration'), $definition['label']),
            ];
            $manualDropdown = $definition['itemtype'] === 'User'
                ? (string) User::dropdown($dropdownOptions + ['right' => 'all'])
                : (string) Dropdown::show($definition['itemtype'], $dropdownOptions);
        }
        $row = ['source' => $value, 'options' => $options, 'selected' => $selected, 'manual_dropdown' => $manualDropdown, 'position' => $position];
        if ($isAutomatic) {
            $automaticRows[] = $row;
        } else {
            $rows[] = $row;
        }
        $position++;
    }
    $totalCount = count($sets[$index]->values);
    $statistics['total'] += $totalCount;
    $statistics['automatic'] += count($automaticRows);
    $statistics['remaining'] += count($rows);
    $fields[] = [
        'target_key' => $targetKey,
        'label' => $definition['label'],
        'column' => $mapping['source_name'],
        'rows' => $rows,
        'automatic_rows' => $automaticRows,
        'total_count' => $totalCount,
        'automatic_count' => count($automaticRows),
        'remaining_count' => count($rows),
        'truncated' => $sets[$index]->truncated,
        'can_skip_unresolved' => str_starts_with($targetKey, 'actor.'),
        'is_multi_actor' => str_starts_with($targetKey, 'actor.'),
        'skip_unresolved' => in_array($targetKey, $savedSkipTargets, true),
    ];
}
Html::header(__('Value correspondence', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/mapping/values.html.twig', [
    'profile' => $profile->fields, 'source' => $source->fields, 'fields' => $fields, 'statistics' => $statistics,
    'last_analysis' => (array) ($profileOptions['last_value_analysis'] ?? []),
    'can_manage' => $profile->canUpdateItem(), 'form_action' => WebUrl::front('value.form.php'),
    'mapping_url' => WebUrl::front('mapping.form.php') . '?profiles_id=' . $profileId,
    'plan_url' => WebUrl::front('plan.preview.php') . '?profiles_id=' . $profileId,
    'can_preview_plan' => ($profile->fields['workflow_step'] ?? '') === MigrationProfile::STEP_VALUES_CONFIGURED,
]);
Html::footer();
