<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

if (!\GlpiPlugin\Ticketmigration\ProfileRight::canViewHistory()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}
Html::header(__('Migration runs', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
echo '<div class="alert alert-info m-3">' . __s('Migration run history is under development.', 'ticketmigration') . '</div>';
Html::footer();
