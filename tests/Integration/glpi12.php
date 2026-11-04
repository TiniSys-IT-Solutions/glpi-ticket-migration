<?php

declare(strict_types=1);

// This runner boots GLPI and may initialize plugin state. Never target production.
$root = getenv('GLPI_TEST_ROOT');
if (!$root || !is_file($root . '/.tinisys-disposable-test')) {
    fwrite(STDERR, "GLPI_TEST_ROOT must point to an explicitly marked disposable installation.\n");
    exit(2);
}
require $root . '/vendor/autoload.php';
$kernel = new Glpi\Kernel\Kernel();
$kernel->boot();
if (!preg_match('/^12\.0\./', GLPI_VERSION)) {
    throw new RuntimeException('This runner requires GLPI 12.0.x.');
}
$pluginRoot = dirname(__DIR__, 2);
require_once $pluginRoot . '/vendor/autoload.php';
require_once $pluginRoot . '/setup.php';
$key = 'ticketmigration';
$metadata = ("plugin_version_$key")();
if ($metadata['requirements']['glpi'] !== ['min' => '12.0.0', 'max' => '12.1.0']
    || $metadata['requirements']['php']['min'] !== '8.3'
    || !("plugin_{$key}_check_prerequisites")()) {
    throw new RuntimeException('Inconsistent GLPI/PHP compatibility metadata.');
}
if (!Plugin::isPluginActive($key)) {
    throw new RuntimeException('Install and activate the plugin before running this check.');
}
// Explicit initialization catches errors that the native plugin loader may log
// and swallow, such as removed hook constants.
("plugin_init_$key")();
$loaded = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot . '/src', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $text = file_get_contents($file->getPathname());
    if (!preg_match('/^namespace ([^;]+);/m', $text, $namespace)
        || !preg_match('/^(?:final |abstract )?(?:readonly )?(?:class|interface|trait|enum) (\w+)/m', $text, $match)) {
        continue;
    }
    $class = $namespace[1] . '\\' . $match[1];
    if (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !enum_exists($class)) {
        throw new RuntimeException('Unable to load ' . $class);
    }
    ++$loaded;
}
if ($loaded === 0) {
    throw new RuntimeException('No plugin classes discovered.');
}
echo "PASS: GLPI " . GLPI_VERSION . ", $key initialized, $loaded declarations loaded\n";
