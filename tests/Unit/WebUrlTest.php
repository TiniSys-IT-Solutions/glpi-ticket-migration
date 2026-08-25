<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit;

use GlpiPlugin\Ticketmigration\WebUrl;
use PHPUnit\Framework\TestCase;

final class WebUrlTest extends TestCase
{
    public function testBuildsCanonicalPluginUrlWithRootDocument(): void
    {
        $previous = $GLOBALS['CFG_GLPI'] ?? null;
        $GLOBALS['CFG_GLPI']['root_doc'] = '/glpi/';

        self::assertSame('/glpi/plugins/ticketmigration/front/profile.form.php', WebUrl::front('profile.form.php'));
        self::assertSame('/plugins/ticketmigration/front/profile.php', WebUrl::front('profile.php', false));

        if ($previous === null) {
            unset($GLOBALS['CFG_GLPI']);
        } else {
            $GLOBALS['CFG_GLPI'] = $previous;
        }
    }
}
