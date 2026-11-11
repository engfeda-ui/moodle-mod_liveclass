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

/**
 * Arabic language strings for mod_liveclass.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'فصول LiveClass الافتراضية';
$string['modulename'] = 'حصة افتراضية';
$string['modulenameplural'] = 'الحصص الافتراضية';
$string['pluginadministration'] = 'إدارة فصول LiveClass الافتراضية';

// Capabilities.
$string['liveclass:addinstance'] = 'إضافة حصة افتراضية جديدة';
$string['liveclass:view'] = 'عرض الحصة الافتراضية';
$string['liveclass:join'] = 'الانضمام للحصة المباشرة';
$string['liveclass:moderate'] = 'إدارة الحصة والتحكم بالمشاركين';
$string['liveclass:record'] = 'تسجيل الحصة المباشرة';
$string['liveclass:viewattendance'] = 'الاطلاع على تقارير الحضور اللحظية';

// General Form Strings.
$string['classname'] = 'عنوان الحصة';
$string['schedule'] = 'جدول وموعد الحصة';
$string['starttime'] = 'وقت البدء';
$string['endtime'] = 'وقت الانتهاء';
$string['attendance_settings'] = 'قواعد الحضور الآلي والتواجد الفعلي';
$string['min_attendance_pct'] = 'الحد الأدنى لنسبة الحضور المطلوبة (%)';
$string['min_attendance_pct_help'] = 'المتدرب الذي يحضر هذه النسبة على الأقل من إجمالي وقت الحصة الفعلي سيتم تسجيله "حاضراً" بشكل آلي تماماً.';
$string['min_attendance_pct_desc'] = 'النسبة المئوية الافتراضية المطلوبة لاعتماد حضور المتدرب.';
$string['auto_sync_attendance'] = 'مزامنة الحضور آلياً مع سجل المودل وإكمال النشاط';
$string['warn_tab_switch'] = 'رصد ومراقبة مغادرة نافذة الحصة (Tab Switching)';
$string['recording_mode'] = 'نمط التسجيل';
$string['recording_disabled'] = 'معطل';
$string['recording_automatic'] = 'تسجيل تلقائي';
$string['recording_moderator'] = 'بواسطة المدرب فقط';
$string['max_participants'] = 'الحد الأقصى للمشاركين';

// Server Administration Settings.
$string['server_settings'] = 'إعدادات الاتصال بسيرفر LiveKit SFU';
$string['server_settings_desc'] = 'قم بضبط عنوان وبيانات خادم LiveKit WebRTC الخاص بأكاديميتك.';
$string['livekit_host'] = 'رابط LiveKit WebSocket';
$string['livekit_host_desc'] = 'مثال: wss://lms.sanad.ws:7880 أو ws://150.230.241.37:7880';
$string['livekit_api_key'] = 'مفتاح API Key';
$string['livekit_api_key_desc'] = 'مفتاح API الخاص بـ LiveKit المكتوب في livekit.yaml';
$string['livekit_api_secret'] = 'المفتاح السري API Secret';
$string['livekit_api_secret_desc'] = 'المفتاح السري الخاص بـ LiveKit المكتوب في livekit.yaml';

// View Page Strings.
$string['role_moderator'] = 'مدرب / مسؤول الجلسة';
$string['role_student'] = 'متدرب';
$string['ready_to_join'] = 'جاهز للانضمام للحصة الافتراضية';
$string['ready_desc'] = 'اتصال فوري بصوت نقي، فيديو عالي الدقة، ومشاركة الشاشة دون برامج خارجية.';
$string['join_now'] = 'دخول الحصة الآن';
$string['session_details'] = 'معلومات وتفاصيل الحصة';
$string['min_attendance_req'] = 'نسبة الحضور المطلوبة';
$string['attendance_status'] = 'مستوى إثبات الحضور';
$string['attendance_auto_tracked_desc'] = 'يتم رصد وحساب دقائق حضورك الفعلي آلياً وبدقة عبر محرك الحضور الذكي.';

