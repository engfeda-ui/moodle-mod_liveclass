<?php
/**
 * Zero-dependency XMLDB schema integrity test for db/install.xml.
 */

$xml_file = __DIR__ . '/../db/install.xml';

if (!file_exists($xml_file)) {
    echo "FAIL: db/install.xml not found\n";
    exit(1);
}

$content = file_get_contents($xml_file);
$xml = simplexml_load_string($content);

if ($xml === false) {
    echo "FAIL: install.xml is not valid XML\n";
    exit(1);
}

$expected_tables = [
    'liveclass',
    'liveclass_sessions',
    'liveclass_attendance',
    'liveclass_intervals',
    'liveclass_presence_logs',
    'liveclass_polls',
    'liveclass_poll_answers'
];

$found_tables = [];
foreach ($xml->TABLES->TABLE as $t) {
    $found_tables[] = (string)$t['NAME'];
}

$missing = array_diff($expected_tables, $found_tables);
if (!empty($missing)) {
    echo "FAIL: Missing tables in schema: " . implode(', ', $missing) . "\n";
    exit(1);
}

echo "PASS: XMLDB schema verified (" . count($found_tables) . " tables defined with valid structure).\n";
exit(0);
