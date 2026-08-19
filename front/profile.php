<?php

include('../../../inc/includes.php');

Session::checkRight(\GlpiPlugin\Ticketmigration\ProfileRight::RIGHT_VIEW_PROFILES, READ);
Html::header(__('Ticket Migration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
echo '<div class="alert alert-info m-3">' . __s('Migration profile management is under development.', 'ticketmigration') . '</div>';
Html::footer();
