<?php

namespace GlpiPlugin\Ticketmigration;

use CommonGLPI;
use Session;

final class ProfileRight extends \Profile
{
    public const RIGHT_VIEW_PROFILES = 'plugin_ticketmigration_profiles';
    public const RIGHT_MANAGE_PROFILES = 'plugin_ticketmigration_manage_profiles';
    public const RIGHT_DRY_RUN = 'plugin_ticketmigration_dry_run';
    public const RIGHT_RUN = 'plugin_ticketmigration_run';
    public const RIGHT_HISTORY = 'plugin_ticketmigration_history';
    public const RIGHT_CONFIG = 'plugin_ticketmigration_config';

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return $item instanceof \Profile
            && $item->getID() > 0
            && ($item->fields['interface'] ?? '') === 'central'
            ? self::createTabEntry(__('Ticket Migration', 'ticketmigration'), 0, $item::getType(), 'ti ti-transfer')
            : '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof \Profile) {
            $canEdit = Session::haveRight('profile', UPDATE);
            if ($canEdit) {
                echo "<form method='post' action='" . $item->getFormURL() . "'>";
            }
            $item->displayRightsChoiceMatrix(self::rights(), [
                'canedit' => $canEdit,
                'title' => __('Ticket Migration', 'ticketmigration'),
            ]);
            if ($canEdit) {
                echo "<div class='center'>";
                echo \Html::hidden('id', ['value' => $item->getID()]);
                echo \Html::submit(_sx('button', 'Save'), ['name' => 'update']);
                echo '</div>';
                \Html::closeForm();
            }
        }
        return true;
    }

    public static function rights(): array
    {
        return [
            self::right(__('View migration profiles', 'ticketmigration'), self::RIGHT_VIEW_PROFILES, [READ => __('Read')]),
            self::right(__('Manage migration profiles', 'ticketmigration'), self::RIGHT_MANAGE_PROFILES, [
                READ => __('Read'),
                CREATE => __('Create'),
                UPDATE => __('Update'),
                DELETE => __('Delete'),
                PURGE => ['short' => __('Purge'), 'long' => _x('button', 'Delete permanently')],
            ]),
            self::right(__('Run dry runs', 'ticketmigration'), self::RIGHT_DRY_RUN, [READ => __('Execute')]),
            self::right(__('Run imports', 'ticketmigration'), self::RIGHT_RUN, [READ => __('Execute')]),
            self::right(__('View migration history', 'ticketmigration'), self::RIGHT_HISTORY, [READ => __('Read')]),
            self::right(__('Manage plugin configuration', 'ticketmigration'), self::RIGHT_CONFIG, [
                READ => __('Read'),
                UPDATE => __('Update'),
            ]),
        ];
    }

    private static function right(string $label, string $field, array $rights): array
    {
        return [
            'label' => $label,
            'field' => $field,
            'rights' => $rights,
        ];
    }
}
