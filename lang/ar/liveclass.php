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
$string['messageprovider:nudge'] = 'تنبيهات وإشعارات حضور الطلاب المباشرة';

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

// Command Center & Roster Strings.
$string['roster_command_center'] = 'مركز متابعة الحضور وتواجد المجموعات اللحظي';
$string['target_group'] = 'المجموعة المستهدفة';
$string['allowed_groups'] = 'المجموعات المصرح لها بالحضور';
$string['restricted_groups'] = 'المجموعات غير المصرح لها (مقيدة)';
$string['no_group_restriction'] = 'جميع المتدربين المسجلين (بدون تقييد بمجموعة)';
$string['status_in_class'] = 'في المحاضرة الآن';
$string['status_online_moodle'] = 'فاتح مودل (خارج المحاضرة)';
$string['status_absent'] = 'غائب / غير متصل';
$string['total_students'] = 'إجمالي طلاب المجموعة';
$string['in_class_count'] = 'داخل المحاضرة';
$string['online_moodle_count'] = 'نشط على مودل';
$string['absent_count'] = 'غائب';
$string['student_name'] = 'المتدرب';
$string['student_id'] = 'الرقم التدريبي';
$string['attendance_progress_col'] = 'نسبة الحضور';
$string['last_seen_moodle'] = 'التواجد على مودل';
$string['tab_switches_col'] = 'مغادرة التبويب';
$string['action_nudge'] = 'تنبيه المتدرب';
$string['refresh_roster'] = 'تحديث حي';
$string['group_filter'] = 'تصفية حسب المجموعة';
$string['all_cohort_members'] = 'قائمة طلاب المجموعة';
$string['online_now'] = 'نشط الآن';
$string['min_ago'] = 'منذ {$a} دقيقة';
$string['hours_ago'] = 'منذ {$a} ساعة';
$string['never_logged'] = 'لم يسجل دخول';
$string['nudge_sent'] = 'تم إرسال رسالة التنبيه للمتدرب بنجاح';
$string['nudge_message_body'] = 'تنبيه عاجل: المحاضرة الافتراضية "{$a->classname}" منعقدة الآن، وأنت متواجد على المنصة ولكن لم تنضم بعد. يرجى الدخول فوراً: {$a->url}';
$string['active_in_class_desc'] = 'متصل حالياً ويشارك داخل غرفة البث الافتراضية';
$string['online_moodle_desc'] = 'سجل دخوله إلى مودل ولكنه لم ينضم للحصة بعد';
$string['absent_offline_desc'] = 'لم يسجل دخوله إلى المنصة أو يدخل الحصة';
$string['allowed_badge'] = 'مسموح لها';
$string['restricted_badge'] = 'غير مصرح لها';

// Advanced Features Strings.
$string['export_attendance'] = 'تصدير كشف الحضور';
$string['export_csv'] = 'تصدير كملف إكسل (CSV)';
$string['mute_all'] = 'كتم صوت الجميع';
$string['mute_all_confirm'] = 'هل أنت متأكد من كتم صوت الميكروفون لجميع المتدربين؟';
$string['mute_all_success'] = 'تم كتم صوت جميع المتدربين بنجاح';
$string['muted_by_instructor'] = 'تم كتم صوت الميكروفون الخاص بك من قِبل المحاضر';
$string['quick_poll'] = 'استطلاع خاطف / إثبات تواجد';
$string['create_poll'] = 'إطلاق الاستطلاع';
$string['poll_question'] = 'سؤال الاستطلاع';
$string['poll_options'] = 'خيارات الإجابة (خيار في كل سطر)';
$string['poll_duration'] = 'مدة الإجابة (بالثواني)';
$string['poll_active_title'] = 'تحقق التواجد والتفاعل الفعلي';
$string['poll_submit'] = 'تأكيد الإجابة';
$string['poll_answered'] = 'شكراً لك! تم تأكيد تواجدك وتفاعلك في الحصة بنجاح.';
$string['poll_results'] = 'نتائج الاستطلاع';
$string['poll_total_responses'] = 'إجمالي الإجابات';
$string['recordings_archive'] = 'سجل وتسجيلات الجلسات';
$string['no_recordings_yet'] = 'لا توجد تسجيلات متوفرة لهذه الحصة بعد.';
$string['play_recording'] = 'مشاهدة التسجيل';
$string['session_date'] = 'التاريخ';
$string['session_duration'] = 'مدة الجلسة';
$string['session_attendees'] = 'عدد الحضور';
$string['recording_status'] = 'حالة التسجيل';
$string['recording_ready'] = 'جاهز للمشاهدة';
$string['recording_none'] = 'غير مسجل';
$string['start_recording'] = 'بدء التسجيل';
$string['stop_recording'] = 'إيقاف التسجيل';
$string['recording_in_progress'] = 'جاري التسجيل (REC)';
$string['recording_started_msg'] = 'تم بدء تسجيل الجلسة من قِبل المحاضر';
$string['recording_stopped_msg'] = 'تم إيقاف التسجيل بنجاح';
$string['instructor'] = 'المحاضر';
$string['classmates_in_room'] = 'المتواجدون في القاعة';
$string['joined_classroom'] = 'متصل بالقاعة الافتراضية';
$string['gallery_view'] = 'عرض الشبكة';
$string['speaker_view'] = 'وضع الشرح';
$string['lower_all_hands'] = 'خفض جميع الأيادي';
$string['lower_all_hands_success'] = 'تم خفض جميع أيدي المتدربين';
$string['lower_hand'] = 'خفض اليد';
$string['lower_hand_success'] = 'تم خفض يد المتدرب بنجاح';
$string['hand_raised_alert'] = 'قام الطالب {$a} برفع اليد للمشاركة!';
$string['focus_alerts_desc'] = 'يرصد هذا المؤشر عدد المرات التي غادر فيها المتدرب نافذة الشرح المباشر (مثل: تصغير المتصفح، أو فتح علامة تبويب أخرى، أو الانتقال لتطبيق آخر).';
$string['focus_tab_hidden'] = 'مغادرة نافذة الحصة (تبديل التبويب)';
$string['focus_window_lost'] = 'فقدان التركيز على النافذة';
$string['focus_tab_restored'] = 'العودة لشاشة الحصة المباشرة';
$string['focus_modal_title'] = 'سجل تنبيهات التركيز والانتباه للمتدرب';
$string['hand_raised_badge'] = 'رافع يده (يطلب المشاركة)';
$string['class_not_started'] = 'لم تبدأ الحصة بعد (غرفة الانتظار)';
$string['class_not_started_msg'] = 'هذه الحصة الافتراضية مجدولة لتبدأ في: {$a}. يرجى الانتظار، سيتم فتح القاعة تلقائياً عند حلول الوقت.';
$string['starts_in'] = 'تبدأ الحصة خلال';
$string['class_ended'] = 'انتهت هذه الحصة الافتراضية';
$string['class_ended_msg'] = 'انتهت فترة البث المباشر المجدولة لهذه الحصة في: {$a}.';
$string['attendance_summary'] = 'ملخص حضورك في الحصة';
$string['attendance_status_met'] = 'تم استيفاء شرط الحضور بنجاح';
$string['attendance_status_unmet'] = 'لم يتم استيفاء الحد الأدنى لنسبة الحضور';
$string['return_to_course'] = 'العودة للمقرر الدراسي';
$string['moderator_early_notice'] = 'أنت داخل الحصة كمدرب قبل موعد البدء المجدول. الطلاب في غرفة الانتظار وسينضمون تلقائياً عند حلول الموعد.';
$string['moderator_ended_notice'] = 'انتهى وقت الحصة المجدول. تم إغلاق الدخول أمام المتدربين حالياً، ويمكنك مراجعة تقارير الحضور أو تمديد الموعد من إعدادات النشاط.';
$string['end_session_btn'] = 'إنهاء الحصة وإغلاقها';
$string['end_session_confirm'] = 'هل أنت متأكد من رغبتك في إنهاء الحصة وإغلاق القاعة لجميع المتدربين؟';

// v1.2.2 — New strings.
$string['recorded_duration_label'] = 'مدة حضورك المسجلة:';
$string['minutes_unit'] = 'دقيقة';
$string['attendance_pct_label'] = 'نسبة الحضور المحققة:';
$string['attendance_min_label'] = 'الحد الأدنى';
$string['moderator_notice_prefix'] = 'تنبيه للمدرب:';
$string['scheduled_start'] = 'موعد البدء المجدول';
$string['scheduled_end'] = 'انتهى موعد الحصة المجدول';
$string['students_blocked_msg'] = 'المتدربون لا يستطيعون الدخول حالياً.';
$string['nostudentsyet'] = 'لا يوجد متدربين بعد';
$string['raise_hand'] = 'رفع اليد';
$string['focus_alert_detail'] = '(تفاصيل)';
$string['focus_alert_badge_label'] = 'التنبيهات';
$string['alerts_unit'] = 'تنبيهات';
$string['default_poll_question'] = 'هل أنت متابع ومستوعب للشرح الآن؟';
$string['default_poll_options'] = 'نعم، مستوعب تماماً\nأحتاج إعادة نقطة معيدة\nلدي سؤال';
$string['poll_duration_20'] = '20 ثانية';
$string['poll_duration_30'] = '30 ثانية';
$string['poll_duration_45'] = '45 ثانية';
$string['poll_duration_60'] = '60 ثانية';
$string['actions'] = 'إجراءات';
$string['refresh_now'] = 'تحديث الآن';
$string['user_not_enrolled'] = 'المستخدم المستهدف غير مسجل في هذه الكورس.';
$string['session_ended_msg'] = 'تم إنهاء الجلسة';

