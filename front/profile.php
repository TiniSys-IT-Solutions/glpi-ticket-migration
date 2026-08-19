<?php

include('../../../inc/includes.php');

Session::checkRight(\GlpiPlugin\Ticketmigration\ProfileRight::RIGHT_VIEW_PROFILES, READ);
Html::header(__('Ticket Migration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
\Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/profile/index.html.twig');
Html::footer();
