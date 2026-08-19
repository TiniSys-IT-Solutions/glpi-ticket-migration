<?php

use GlpiPlugin\Ticketmigration\Install\Installer;

function plugin_ticketmigration_install(): bool
{
    return (new Installer())->install();
}

function plugin_ticketmigration_uninstall(): bool
{
    return (new Installer())->uninstall();
}
