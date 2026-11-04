<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Source;

use GlpiPlugin\Ticketmigration\Source\UploadError;
use PHPUnit\Framework\TestCase;

final class UploadErrorTest extends TestCase
{
    public function testDescribesPhpUploadLimit(): void
    {
        self::assertStringContainsString('upload_max_filesize', UploadError::describe(UPLOAD_ERR_INI_SIZE));
        self::assertSame('', UploadError::describe(UPLOAD_ERR_OK));
    }
}
