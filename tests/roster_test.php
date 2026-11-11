<?php
/**
 * Test group restriction parser logic.
 */
define('CLI_SCRIPT', true);
define('MOODLE_INTERNAL', true);

function test_extract_groups($availability_json) {
    if (empty($availability_json)) {
        return [];
    }
    $avail = json_decode($availability_json, true);
    if (!$avail || !isset($avail['c'])) {
        return [];
    }
    $groupids = [];
    $extract = function($conditions) use (&$extract, &$groupids) {
        foreach ($conditions as $c) {
            if (isset($c['type']) && $c['type'] === 'group' && isset($c['id'])) {
                $groupids[] = (int)$c['id'];
            } else if (isset($c['c']) && is_array($c['c'])) {
                $extract($c['c']);
            }
        }
    };
    $extract($avail['c']);
    return array_unique($groupids);
}

// Test with CM 104 JSON
$json = '{"op":"&","c":[{"type":"group","id":4}],"showc":[true]}';
$extracted = test_extract_groups($json);

if (in_array(4, $extracted) && count($extracted) === 1) {
    echo "PASS: Extracted group 4 from CM availability JSON.\n";
} else {
    echo "FAIL: Could not extract group 4.\n";
    exit(1);
}

// Test nested conditions
$nested = '{"op":"|","c":[{"type":"group","id":10},{"op":"&","c":[{"type":"group","id":20}]}]}';
$nested_extracted = test_extract_groups($nested);
if (in_array(10, $nested_extracted) && in_array(20, $nested_extracted) && count($nested_extracted) === 2) {
    echo "PASS: Extracted nested groups 10 and 20 successfully.\n";
} else {
    echo "FAIL: Nested group extraction failed.\n";
    exit(1);
}
