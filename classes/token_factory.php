<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_liveclass;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure PHP LiveKit Access Token Factory.
 * Generates HMAC-SHA256 JWT tokens for LiveKit WebRTC authentication without external dependencies.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_factory {

    /**
     * Generate a signed LiveKit Access Token for a participant.
     *
     * @param string $roomname Name of the LiveKit room.
     * @param string $identity Unique user identity (e.g. "user_123").
     * @param string $displayname Human-readable user name.
     * @param bool $is_moderator Whether participant has administrative/moderator permissions.
     * @param array $extra_metadata Optional custom metadata payload.
     * @param int $ttl Time to live in seconds (default: 6 hours = 21600).
     * @return string Signed JWT token.
     */
    public static function create_token(
        string $roomname,
        string $identity,
        string $displayname,
        bool $is_moderator = false,
        array $extra_metadata = [],
        int $ttl = 21600
    ): string {
        $apikey = get_config('mod_liveclass', 'livekit_api_key') ?: 'SANAD_API_KEY';
        $apisecret = get_config('mod_liveclass', 'livekit_api_secret') ?: 'sanad_proctoring_secret_key_2026_livekit';

        $now = time();

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        $grants = [
            'room'           => $roomname,
            'roomJoin'       => true,
            'canSubscribe'   => true,
            'canPublish'     => true,
            'canPublishData' => true,
        ];

        if ($is_moderator) {
            $grants['roomAdmin']  = true;
            $grants['roomRecord'] = true;
        }

        $payload = [
            'iss'      => $apikey,
            'sub'      => $identity,
            'name'     => $displayname,
            'nbf'      => $now - 5,
            'exp'      => $now + $ttl,
            'video'    => $grants,
            'metadata' => json_encode($extra_metadata, JSON_UNESCAPED_UNICODE)
        ];

        $encoded_header  = self::base64url_encode(json_encode($header));
        $encoded_payload = self::base64url_encode(json_encode($payload));

        $signature = hash_hmac('sha256', "$encoded_header.$encoded_payload", $apisecret, true);
        $encoded_signature = self::base64url_encode($signature);

        return "$encoded_header.$encoded_payload.$encoded_signature";
    }

    /**
     * URL-safe Base64 encoding according to RFC 7515.
     *
     * @param string $data Data to encode.
     * @return string Base64URL string.
     */
    private static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
