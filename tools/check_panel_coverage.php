<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$rooms = require $root . '/apps/web-platform/src/config/rooms.php';
$expected = ['Student' => 14, 'Instructor' => 20, 'Admin' => 20];
$counts = array_fill_keys(array_keys($expected), 0);
$authenticated = 0;
$errors = [];

foreach ($rooms as $key => $metadata) {
    [$floor, $room] = explode('/', $key, 2);
    if (!isset($counts[$floor])) {
        continue;
    }
    $counts[$floor]++;
    if (in_array((string) ($metadata['role'] ?? ''), ['student', 'instructor', 'admin'], true)) {
        $authenticated++;
    }
    $directory = $root . '/apps/web-platform/src/' . $floor . '/' . $room;
    if (!is_file($directory . '/Controller.php') && !isset($metadata['controller_file'])) {
        $errors[] = 'Panel controller missing: ' . $key;
    }
}

foreach ($expected as $floor => $count) {
    if ($counts[$floor] !== $count) {
        $errors[] = sprintf('%s panel count changed: expected %d, found %d.', $floor, $count, $counts[$floor]);
    }
}
if ($authenticated !== 49) {
    $errors[] = 'Authenticated panel count must remain 49; found ' . $authenticated . '.';
}

foreach ([
    'apps/web-platform/src/Shared/Ui/PortalPage.php',
    'apps/web-platform/src/Shared/Ui/PanelFactory.php',
    'apps/web-platform/public/assets/css/app.css',
    'apps/web-platform/public/assets/js/app.js',
] as $file) {
    if (!is_file($root . '/' . $file) || filesize($root . '/' . $file) < 100) {
        $errors[] = 'Shared panel asset is missing or empty: ' . $file;
    }
}

if ($errors !== []) {
    echo "PANEL COVERAGE CHECK: FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo "PANEL COVERAGE CHECK: PASS\n";
echo "Panel routes: 54\n";
echo "Authenticated panels: 49\n";
