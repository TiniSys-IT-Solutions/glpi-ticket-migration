<?php

include('../../../inc/includes.php');

Session::checkRight(\GlpiPlugin\Ticketmigration\ProfileRight::RIGHT_CONFIG, READ);
Html::header(__('Ticket Migration configuration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
echo '<div class="alert alert-info m-3">' . __s('Plugin configuration is under development.', 'ticketmigration') . '</div>';
Html::footer();
