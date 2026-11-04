<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Source;

use GlpiPlugin\Ticketmigration\Source\SourceFileStorage;
use PHPUnit\Framework\TestCase;

final class SourceFileStorageTest extends TestCase
{
    public function testRejectsTraversalAndStoresWithRandomInternalName(): void
    {
        $base = sys_get_temp_dir() . '/ticketmigration-test-' . bin2hex(random_bytes(5));
        mkdir($base, 0700, true);
        $source = $base . '/upload.tmp';
        file_put_contents($source, "id;title\n1;Test\n");
        try {
            $stored = (new SourceFileStorage($base . '/stored'))->store($source, '../../legacy.csv');
            self::assertSame('legacy.csv', $stored->sourceFilename);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}\.csv$/', $stored->internalFilename);
            self::assertFileExists($stored->path);
            self::assertSame(hash_file('sha256', $stored->path), $stored->sha256);
        } finally {
            foreach (glob($base . '/stored/*') ?: [] as $file) { unlink($file); }
            if (is_dir($base . '/stored')) { rmdir($base . '/stored'); }
            if (is_file($source)) { unlink($source); }
            rmdir($base);
        }
    }

    public function testRejectsNonCsvMimeEvenWithCsvExtension(): void
    {
        $base = sys_get_temp_dir() . '/ticketmigration-mime-' . bin2hex(random_bytes(5));
        mkdir($base, 0700, true);
        $source = $base . '/upload.tmp';
        file_put_contents(
            $source,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('MIME type');
            (new SourceFileStorage($base . '/stored'))->store($source, 'image.csv');
        } finally {
            foreach (glob($base . '/stored/*') ?: [] as $file) { unlink($file); }
            if (is_dir($base . '/stored')) { rmdir($base . '/stored'); }
            if (is_file($source)) { unlink($source); }
            rmdir($base);
        }
    }
}
