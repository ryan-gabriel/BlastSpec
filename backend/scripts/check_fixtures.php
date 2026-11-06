#!/usr/bin/env php
<?php

/**
 * Check that every BR-### rule has a fixture directory.
 *
 * Usage: php check_fixtures.php
 */

$breakingChanges = file_get_contents(__DIR__ . '/../BREAKING_CHANGES.md');

preg_match_all('/`BR-(\d{3})`/', $breakingChanges, $matches);
$rules = array_unique($matches[1]);
sort($rules);

$fixtureBase = __DIR__ . '/../tests/Fixtures/specs/breaking';
$missing = [];

foreach ($rules as $rule) {
    $dir = $fixtureBase . '/BR-' . $rule;
    if (! is_dir($dir)) {
        $missing[] = 'BR-' . $rule;
        continue;
    }
    foreach (['before.yaml', 'after.yaml', 'expected.json'] as $file) {
        if (! file_exists($dir . '/' . $file)) {
            $missing[] = 'BR-' . $rule . '/' . $file;
        }
    }
}

if (count($missing) > 0) {
    echo "Missing fixtures:\n";
    foreach ($missing as $m) {
        echo "  - {$m}\n";
    }
    exit(1);
}

echo "All " . count($rules) . " rules have complete fixture sets.\n";
exit(0);
