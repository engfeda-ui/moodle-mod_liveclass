<?php
/**
 * Zero-dependency unit test for LiveKit token factory.
 */

if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
    return;
}
if (!defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', true);
}

if (!function_exists('get_config')) {
    function get_config($plugin, $key) {
        if ($key === 'livekit_api_key') return 'TEST_KEY';
        if ($key === 'livekit_api_secret') return 'TEST_SECRET_ABC123';
        return null;
    }
}

require_once(__DIR__ . '/../classes/token_factory.php');

$token = \mod_liveclass\token_factory::create_token(
    'test_room_101',
    'user_42',
    'Ahmed Salem',
    true,
    ['role' => 'moderator']
);

if (empty($token)) {
    echo "FAIL: Token is empty\n";
    exit(1);
}

$parts = explode('.', $token);
if (count($parts) !== 3) {
    echo "FAIL: Token is not a valid 3-part JWT\n";
    exit(1);
}

// Decode header and payload.
$header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

if ($header['alg'] !== 'HS256') {
    echo "FAIL: Algorithm is not HS256\n";
    exit(1);
}

if ($payload['iss'] !== 'TEST_KEY' || $payload['sub'] !== 'user_42' || $payload['name'] !== 'Ahmed Salem') {
    echo "FAIL: Claims mismatch\n";
    exit(1);
}

if (empty($payload['video']['roomJoin']) || $payload['video']['room'] !== 'test_room_101' || empty($payload['video']['roomAdmin'])) {
    echo "FAIL: Video grants mismatch\n";
    exit(1);
}

// Verify signature.
$expected_sig = hash_hmac('sha256', "{$parts[0]}.{$parts[1]}", 'TEST_SECRET_ABC123', true);
$encoded_expected_sig = rtrim(strtr(base64_encode($expected_sig), '+/', '-_'), '=');

if ($encoded_expected_sig !== $parts[2]) {
    echo "FAIL: Signature verification failed\n";
    exit(1);
}

echo "PASS: LiveKit token factory generated cryptographically valid JWT with correct claims.\n";
exit(0);

