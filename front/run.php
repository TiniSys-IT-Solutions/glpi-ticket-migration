<?php

include('../../../inc/includes.php');

Session::checkRight(\GlpiPlugin\Ticketmigration\ProfileRight::RIGHT_HISTORY, READ);
Html::header(__('Migration runs', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
echo '<div class="alert alert-info m-3">' . __s('Migration run history is under development.', 'ticketmigration') . '</div>';
Html::footer();
