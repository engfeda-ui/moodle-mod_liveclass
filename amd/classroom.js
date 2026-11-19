/**
 * LiveClass WebRTC Virtual Classroom Controller with LiveKit SFU Integration.
 *
 * @module     mod_liveclass/classroom
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'core/ajax', 'mod_liveclass/livekit_client'], function($, Notification, Ajax, LivekitClient) {
    'use strict';

    function escHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    class LiveClassroom {
        constructor(config) {
            this.config = config;
            this.livekitRoom = null;
            this.localStream = null;
            this.screenStream = null;
            this.captureVideo = null;
            this.isMicMuted = false;
            this.isCamOff = false;
            this.isScreenSharing = false;
            this.isHandRaised = false;
            this.tabSwitchCount = 0;
            this.elapsedSeconds = 0;
            this.layoutMode = 'gallery'; // Default to gallery grid
            this.participants = [];
            this.timerInterval = null;
            this.heartbeatInterval = null;
            this.pollInterval = null;
            this.handAlertTimeout = null;
            this.raisedHandUsers = new Set();
            this.activePollId = 0;
            this.isRecording = (this.config.recordingMode === 1);
            this.presenceCheckMode = (typeof this.config.presenceCheckMode === 'number') ? this.config.presenceCheckMode : 0;
            this.interactionCheckInterval = null;
            this.lastInteractionActivity = Date.now();

            this.bindEvents();
        }

        bindEvents() {
            const self = this;

            $('#btn-join-liveclass').on('click', function(e) {
                e.preventDefault();
                self.startClassroom();
            });

            // Tab-Switching & Active Presence Detection.
            if (this.config.warnTabSwitch) {
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        self.handleTabHidden();
                    } else {
                        self.handleTabRestored();
                    }
                });

                window.addEventListener('blur', () => {
                    self.logEvent('focus_lost', 'Window lost focus');
                });
            }

            // Clean leave on window unload.
            window.addEventListener('beforeunload', () => {
                self.sendLeaveBeacon();
            });
        }

        startClassroom() {
            const self = this;
            const stage = $('#liveclass-stage');

            // Student join cap: check current LiveKit connected participants.
            if (!self.config.isModerator && typeof self.config.maxParticipants === 'number' && self.config.maxParticipants > 0) {
                const connected = (self.livekitRoom && self.livekitRoom.remoteParticipants) ? (self.livekitRoom.remoteParticipants.size || 0) : 0;
                if (connected >= self.config.maxParticipants) {
                    stage.html(`
                        <div class="text-center p-8">
                            <div class="mb-3">
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary rounded-circle p-4 d-inline-block"><i class="fa fa-users fa-4x"></i></span>
                            </div>
                            <h4>${escHtml(self.config.classFullTitle || '')}</h4>
                            <p class="text-muted">${escHtml(self.config.classFullMsg || '')}</p>
                        </div>
                    `);
                    self.logEvent('join_blocked', 'Class full: max participants reached');
                    return;
                }
            }

            const instructorLabel = self.config.isModerator ? 
                `${self.config.displayName} (أنت - المدرب)` : 
                (self.config.instructorName || 'المدرب');

            // Distinct Viewport layouts: Moderator vs Student in Speaker Mode
            const speakerModeContentHtml = self.config.isModerator ? `
                <div id="video-grid" class="w-100 h-100 d-flex flex-wrap justify-content-center align-items-center p-2 gap-2 position-relative">
                    <div class="video-card local-video-card position-relative bg-dark rounded overflow-hidden shadow" style="width: 100%; max-width: 900px; height: 500px;">
                        <video id="local-video" class="w-100 h-100" style="object-fit: cover;" autoplay playsinline muted></video>
                        <div class="position-absolute bottom-0 start-0 p-2 bg-dark bg-opacity-75 text-white m-2 rounded small z-2">
                            <i class="fa fa-shield text-danger"></i> ${self.config.displayName} (المدرب - شاشة البث للشعبة)
                        </div>
                    </div>
                </div>
            ` : `
                <div class="w-100 h-100 d-flex justify-content-center align-items-center p-2 position-relative">
                    <!-- Instructor Stage Screen -->
                    <div class="instructor-stage-card shadow-lg d-flex flex-column justify-content-between p-3" style="width: 100%; max-width: 960px; height: 500px; position: relative;">
                        <video id="livekit-speaker-video" class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; border-radius: 12px; z-index: 1; display: none;" autoplay playsinline></video>
                        <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-2 z-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger rounded-pill px-3 py-1 animate-pulse">
                                    <i class="fa fa-circle"></i> مباشر
                                </span>
                                <span class="text-white fw-bold">
                                    <i class="fa fa-video-camera text-primary"></i> بث وشرح المحاضر: <span class="text-info">${self.config.instructorName || instructorLabel}</span>
                                </span>
                            </div>
                            <div class="classmates-strip shadow-sm" id="classmates-counter-badge">
                                <i class="fa fa-users text-info"></i> <span id="online-students-count">1</span> متواجدون في القاعة
                            </div>
                        </div>

                        <!-- Central Visual Presentation Stage Fallback -->
                        <div id="speaker-fallback-stage" class="d-flex flex-column justify-content-center align-items-center text-center my-auto py-4 z-1">
                            <div class="mb-3">
                                <span class="badge bg-primary bg-opacity-25 text-info p-3 rounded-circle shadow">
                                    <i class="fa fa-graduation-cap fa-4x"></i>
                                </span>
                            </div>
                            <h4 class="text-white fw-bold mb-1">${self.config.instructorName || instructorLabel}</h4>
                            <p class="text-light opacity-75 small mb-3">
                                <i class="fa fa-podcast text-success"></i> القاعة الافتراضية نشطة وبث الشرح جاري الآن
                            </p>
                            <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                <i class="fa fa-check-circle"></i> أنت متصل ومشارك في الحصة بنجاح
                            </span>
                        </div>

                        <!-- Bottom Status Bar -->
                        <div class="d-flex justify-content-between align-items-center text-muted small pt-2 border-top border-secondary z-2">
                            <span><i class="fa fa-info-circle"></i> يتم احتساب وقت تواجدك ومشاركتك آلياً بدقة</span>
                            <span id="student-live-pct" class="badge bg-secondary">0% الحضور</span>
                        </div>
                    </div>

                    <!-- Floating Student Self-Preview (PiP) -->
                    <div class="student-pip-preview shadow-lg">
                        <video id="local-video" class="w-100 h-100" style="object-fit: cover;" autoplay playsinline muted></video>
                        <div class="position-absolute bottom-0 start-0 w-100 p-1 bg-dark bg-opacity-75 text-white text-center text-truncate z-2" style="font-size: 0.75rem;">
                            <i class="fa fa-user"></i> ${self.config.displayName} (صورتي ومشاركتي)
                        </div>
                    </div>
                </div>
            `;

            stage.html(`
                <div class="liveclass-active-workspace w-100 h-100 d-flex flex-column" style="min-height: 580px;">
                    <!-- Top Lecture Bar -->
                    <div class="liveclass-top-bar d-flex justify-content-between align-items-center p-2 bg-dark text-white border-bottom border-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger rounded-pill px-3 py-2 animate-pulse">
                                <i class="fa fa-circle"></i> مباشر
                            </span>
                            <span id="recording-badge" class="badge bg-danger rounded-pill px-3 py-2 animate-pulse ${self.isRecording ? '' : 'd-none'}">
                                <i class="fa fa-circle"></i> REC
                            </span>
                            <span class="fw-bold ms-2">${self.config.roomName}</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-secondary p-2" id="live-timer"><i class="fa fa-clock-o"></i> 00:00:00</span>
                            <span class="badge bg-info p-2" id="attendance-status-badge"><i class="fa fa-percent"></i> 0% الحضور</span>
                        </div>
                    </div>

                    <!-- Main Media Viewport -->
                    <div class="liveclass-media-viewport flex-grow-1 position-relative bg-black" style="min-height: 520px; overflow: hidden;">
                        <!-- Mode A: Speaker View Container -->
                        <div id="container-speaker" class="w-100 h-100 position-relative justify-content-center align-items-center" style="display: none;">
                            ${speakerModeContentHtml}
                            <div id="speaker-filmstrip" class="attendees-filmstrip d-none"></div>
                        </div>

                        <!-- Mode B: Gallery Grid View Container (Active by default) -->
                        <div id="container-gallery" class="w-100 h-100 position-relative" style="display: block;">
                            <div id="gallery-grid" class="gallery-grid-container"></div>
                        </div>

                        <!-- Hand Raise Notification Banner for Instructor -->
                        <div id="hand-raise-alert" class="position-absolute top-0 start-50 translate-middle-x mt-3 alert alert-warning shadow-lg border border-warning d-none" style="z-index: 1070; min-width: 320px;">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fs-4">✋</span>
                                    <div class="text-start">
                                        <strong id="hand-raise-student-name">طالب</strong>
                                        <div class="small text-dark">قام برفع اليد ويطلب التحدث أو المشاركة الآن</div>
                                    </div>
                                </div>
                                <button id="btn-dismiss-hand-alert" type="button" class="btn btn-sm btn-outline-dark rounded-circle px-2 py-0">&times;</button>
                            </div>
                        </div>

                        <!-- Spontaneous Poll Overlay for Students -->
                        <div id="poll-overlay" class="position-absolute top-50 start-50 translate-middle p-4 bg-white text-dark rounded-3 shadow-lg border border-warning d-none" style="z-index: 1060; width: 90%; max-width: 480px;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0 text-warning"><i class="fa fa-bullhorn"></i> تحقق التواجد والمشاركة</h5>
                                <span class="badge bg-danger rounded-pill px-2 py-1" id="poll-timer-badge">30s</span>
                            </div>
                            <div class="progress mb-3" style="height: 6px;">
                                <div id="poll-progress-bar" class="progress-bar bg-warning progress-bar-striped progress-bar-animated" style="width: 100%;"></div>
                            </div>
                            <p class="fw-bold mb-3 fs-6" id="poll-question-text"></p>
                            <form id="poll-answer-form">
                                <div id="poll-options-container" class="mb-3"></div>
                                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">
                                    <i class="fa fa-check-circle"></i> تأكيد الإجابة والتواجد
                                </button>
                            </form>
                        </div>

                        <!-- Warning Toast Overlay for Tab Switching -->
                        <div id="presence-alert" class="position-absolute top-0 start-50 translate-middle-x mt-3 alert alert-warning shadow d-none" style="z-index: 1050;">
                            <i class="fa fa-exclamation-triangle"></i> <strong>تنبيه:</strong> لقد قمت بمغادرة نافذة الحصة! يتم تسجيل هذا السلوك آلياً في سجل التواجد.
                        </div>

                        <!-- System Mute Notification Toast -->
                        <div id="mute-notice" class="position-absolute top-0 start-50 translate-middle-x mt-3 alert alert-info shadow d-none" style="z-index: 1050;">
                            <i class="fa fa-microphone-slash"></i> <strong>تنبيه:</strong> قام المحاضر بكتم صوت الميكروفون لجميع المتدربين.
                        </div>
                    </div>

                    <!-- Bottom Controls Toolbar -->
                    <div class="liveclass-toolbar bg-dark border-top border-secondary">
                        <!-- Group 1: Media Audio/Video Controls -->
                        <div class="toolbar-group">
                            <button id="btn-toggle-mic" class="btn btn-outline-light rounded-circle" title="كتم/تشغيل المايك">
                                <i class="fa fa-microphone fa-lg"></i>
                            </button>
                            <button id="btn-toggle-cam" class="btn btn-outline-light rounded-circle" title="تشغيل/إيقاف الكاميرا">
                                <i class="fa fa-video-camera fa-lg"></i>
                            </button>
                            <button id="btn-toggle-screen" class="btn btn-outline-light rounded-circle" title="مشاركة الشاشة">
                                <i class="fa fa-desktop fa-lg"></i>
                            </button>
                            <button id="btn-raise-hand" class="btn btn-outline-warning rounded-circle" title="رفع اليد للمشاركة">
                                <i class="fa fa-hand-paper-o fa-lg"></i>
                            </button>
                        </div>

                        <!-- Group 2: Layout View Switcher (Speaker vs Gallery Grid) -->
                        <div class="toolbar-group">
                            <button id="btn-toggle-layout" class="btn btn-info text-white rounded-pill shadow-sm" title="تبديل طريقة العرض (شرح / شبكة المتدربين)">
                                <i class="fa fa-television"></i> <span id="layout-btn-label">وضع الشرح (Speaker)</span>
                            </button>
                        </div>

                        <!-- Group 3: Moderator Special Commands -->
                        ${self.config.isModerator ? `
                            <div class="toolbar-group">
                                <button id="btn-stage-mute-all" class="btn btn-danger rounded-pill shadow-sm" title="كتم صوت جميع الطلاب">
                                    <i class="fa fa-microphone-slash"></i> كتم الجميع
                                </button>
                                <button id="btn-stage-lower-hands" class="btn btn-warning rounded-pill shadow-sm" title="خفض جميع أيدي المتدربين">
                                    <i class="fa fa-hand-paper-o"></i> خفض الأيدي
                                </button>
                                ${self.config.recordingMode === 2 ? `
                                    <button id="btn-toggle-recording" class="btn ${self.isRecording ? 'btn-danger' : 'btn-outline-danger'} rounded-pill shadow-sm" title="بدء / إيقاف التسجيل">
                                        <i class="fa ${self.isRecording ? 'fa-stop' : 'fa-circle'}"></i> 
                                        <span id="recording-btn-label">${self.isRecording ? 'إيقاف التسجيل' : 'بدء التسجيل'}</span>
                                    </button>
                                ` : ''}
                            </div>
                        ` : ''}

                        <!-- Group 4: Viewport & Exit Actions -->
                        <div class="toolbar-group">
                            <button id="btn-fullscreen" class="btn btn-outline-light rounded-circle" title="ملء الشاشة">
                                <i class="fa fa-arrows-alt fa-lg"></i>
                            </button>
                            <button id="btn-leave" class="btn btn-danger rounded-pill shadow-sm" title="مغادرة الحصة">
                                <i class="fa fa-phone"></i> مغادرة
                            </button>
                        </div>
                    </div>
                </div>
            `);

            self.initLiveKit();
            self.startAttendanceTimer();
            self.bindToolbarControls();
            self.startPollWatcher();
            self.startHeartbeatLoop();
            self.startInteractionCheck();
        }

        // Random interaction checks (presence_check_mode === 2): periodically
        // alert when the student is inactive (no mic, no cam, no hand raised).
        startInteractionCheck() {
            const self = this;
            if (this.presenceCheckMode !== 2) {
                return;
            }
            this.interactionCheckInterval = setInterval(() => {
                const idle = (self.isMicMuted && self.isCamOff && !self.isHandRaised);
                if (idle) {
                    $('#presence-alert').removeClass('d-none');
                    setTimeout(() => $('#presence-alert').addClass('d-none'), 6000);
                    self.logEvent('interaction_check_missed', 'Interaction check missed: student inactive');
                }
            }, 60000);
        }

        markActivity() {
            this.lastInteractionActivity = Date.now();
        }

        showToast(type, message) {
            const stage = $('#liveclass-stage');
            const bgClass = (type === 'success') ? 'bg-success text-white' : 'bg-warning text-dark';
            const icon = (type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
            const toast = $(`
                <div class="liveclass-toast ${bgClass}">
                    <i class="fa ${icon} me-1"></i> ${message}
                </div>
            `);
            stage.append(toast);
            setTimeout(() => {
                toast.fadeOut(400, function() { $(this).remove(); });
            }, 4000);
        }

        async initLiveKit() {
            const self = this;
            const LK = (LivekitClient && LivekitClient.Room) ? LivekitClient : (window.LivekitClient || {});
            if (!LK || !LK.Room) {
                console.error('[LiveClass] LiveKit WebRTC SDK not loaded.');
                return;
            }

            try {
                console.log('[LiveClass] Connecting to LiveKit SFU:', self.config.host, self.config.roomName);
                const isMod = !!self.config.isModerator;
                const targetPreset = isMod ?
                    (LK.VideoPresets && LK.VideoPresets.h720 ? LK.VideoPresets.h720 : { resolution: { width: 1280, height: 720 }, encoding: { maxBitrate: 1700000, maxFramerate: 30 } }) :
                    (LK.VideoPresets && LK.VideoPresets.h540 ? LK.VideoPresets.h540 : { resolution: { width: 960, height: 540 }, encoding: { maxBitrate: 800000, maxFramerate: 25 } });

                const room = new LK.Room({
                    adaptiveStream: false,
                    dynacast: true,
                    videoCaptureDefaults: {
                        resolution: targetPreset.resolution
                    },
                    publishDefaults: {
                        simulcast: true,
                        videoCodec: 'vp8'
                    }
                });
                self.livekitRoom = room;

                // Track Subscribed Event (Remote user video or audio incoming)
                room.on(LK.RoomEvent.TrackSubscribed, (track, publication, participant) => {
                    console.log('[LiveClass] Track Subscribed from:', participant.identity, track.kind);
                    self.handleTrackSubscribed(track, publication, participant);
                });

                // Track Unsubscribed Event
                room.on(LK.RoomEvent.TrackUnsubscribed, (track, publication, participant) => {
                    console.log('[LiveClass] Track Unsubscribed from:', participant.identity, track.kind);
                    self.handleTrackUnsubscribed(track, participant);
                });

                // Track Muted/Unmuted Events (for mic badge updates)
                room.on(LK.RoomEvent.TrackMuted, (publication, participant) => {
                    self.handleTrackMuteState(participant, true);
                });
                room.on(LK.RoomEvent.TrackUnmuted, (publication, participant) => {
                    self.handleTrackMuteState(participant, false);
                });

                // Real-time DataChannel Messages (Instant Mute All, Hand Raises, Lower Hands, Polls)
                room.on(LK.RoomEvent.DataReceived, (payload, participant) => {
                    try {
                        const str = new TextDecoder().decode(payload);
                        const data = JSON.parse(str);

                        if (data.type === 'mute_all' && !self.config.isModerator) {
                            self.muteLocalMic();
                            $('#mute-notice').removeClass('d-none');
                            setTimeout(() => { $('#mute-notice').addClass('d-none'); }, 5000);
                        } else if (data.type === 'lower_all_hands') {
                            if (!self.config.isModerator) {
                                self.isHandRaised = false;
                                $('#btn-raise-hand').removeClass('btn-warning text-dark').addClass('btn-outline-warning');
                                self.showToast('info', 'قام المحاضر بخفض جميع الأيدي المرتفعة');
                            } else {
                                self.raisedHandUsers = new Set();
                                $('#hand-raise-alert').addClass('d-none');
                                $('.gallery-tile .hand-badge').remove();
                            }
                        } else if (data.type === 'lower_hand') {
                            if (data.userid === self.config.currentUserId && !self.config.isModerator) {
                                self.isHandRaised = false;
                                $('#btn-raise-hand').removeClass('btn-warning text-dark').addClass('btn-outline-warning');
                                self.showToast('info', 'قام المحاضر بخفض يدك');
                            } else if (self.config.isModerator && data.userid) {
                                self.raisedHandUsers.delete(data.userid);
                                $(`#gallery-tile-${data.userid} .hand-badge`).remove();
                                if (self.raisedHandUsers.size === 0) {
                                    $('#hand-raise-alert').addClass('d-none');
                                }
                            }
                        } else if (data.type === 'hand_state_changed') {
                            if (self.config.isModerator && data.userid) {
                                if (data.handRaised) {
                                    self.playHandRaiseChime();
                                    $('#hand-raise-student-name').text(data.fullname || 'متدرب');
                                    $('#hand-raise-alert').removeClass('d-none');
                                    self.raisedHandUsers.add(data.userid);
                                    const tile = $(`#gallery-tile-${data.userid}`);
                                    if (tile.length && !tile.find('.hand-badge').length) {
                                        tile.prepend(`<div class="hand-badge hand-badge-clickable animate-bounce" data-userid="${escHtml(Math.floor(Number(data.userid) || 0))}" title="انقر لخفض يد الطالب">✋</div>`);
                                    }
                                } else {
                                    self.raisedHandUsers.delete(data.userid);
                                    $(`#gallery-tile-${data.userid} .hand-badge`).remove();
                                    if (self.raisedHandUsers.size === 0) {
                                        $('#hand-raise-alert').addClass('d-none');
                                    }
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('[LiveClass] DataReceived parse error:', e);
                    }
                });

                // Participant Connect / Disconnect events for instantaneous stage updating
                room.on(LK.RoomEvent.ParticipantConnected, (participant) => {
                    console.log('[LiveClass] Participant connected to room:', participant.identity);
                    self.renderParticipants();
                });
                room.on(LK.RoomEvent.ParticipantDisconnected, (participant) => {
                    console.log('[LiveClass] Participant disconnected from room:', participant.identity);
                    self.renderParticipants();
                });

                // Connect to LiveKit Room over WSS (port 443 via Nginx /rtc)
                await room.connect(self.config.host, self.config.token);
                console.log('[LiveClass] Successfully joined LiveKit WebRTC room:', room.name);

                // Publish Local Media (Camera & Mic) via LiveKit
                try {
                    await room.localParticipant.setCameraEnabled(true, {
                        resolution: targetPreset.resolution,
                        facingMode: 'user'
                    });
                    console.log('[LiveClass] Camera published at', targetPreset.resolution.width, 'x', targetPreset.resolution.height);
                } catch (camErr) {
                    console.warn('[LiveClass] Camera permission denied or device busy:', camErr);
                    self.isCamOff = true;
                    $('#btn-toggle-cam').removeClass('btn-outline-light').addClass('btn-danger');
                    $('#btn-toggle-cam').find('i').removeClass('fa-video-camera').addClass('fa-eye-slash');
                }

                try {
                    await room.localParticipant.setMicrophoneEnabled(true);
                    console.log('[LiveClass] Microphone published!');
                } catch (micErr) {
                    console.warn('[LiveClass] Microphone permission denied:', micErr);
                    self.isMicMuted = true;
                    $('#btn-toggle-mic').removeClass('btn-outline-light').addClass('btn-danger');
                    $('#btn-toggle-mic').find('i').removeClass('fa-microphone').addClass('fa-microphone-slash');
                }

                // Attach local video track to UI
                const camPub = room.localParticipant.getTrackPublication(LK.Track.Source.Camera);
                if (camPub && camPub.track && camPub.track.mediaStreamTrack) {
                    const localTrack = camPub.track;
                    self.localStream = new MediaStream([localTrack.mediaStreamTrack]);

                    const micPub = room.localParticipant.getTrackPublication(LK.Track.Source.Microphone);
                    if (micPub && micPub.track && micPub.track.mediaStreamTrack) {
                        self.localStream.addTrack(micPub.track.mediaStreamTrack);
                    }

                    const lv = document.getElementById('local-video');
                    if (lv) localTrack.attach(lv);

                    if (self.config.isModerator) {
                        const giv = document.getElementById('gallery-instructor-video');
                        if (giv) localTrack.attach(giv);
                    } else {
                        const gmv = document.querySelector('.gallery-my-video');
                        if (gmv) localTrack.attach(gmv);
                    }
                    self.markActivity();
                }
            } catch (err) {
                console.error('[LiveClass] LiveKit WebRTC connection error:', err);
                self.initMediaStream();
            }
        }

        handleTrackSubscribed(track, publication, participant) {
            const self = this;
            const uidMatch = (participant.identity || '').match(/user_(\d+)/);
            const userId = uidMatch ? parseInt(uidMatch[1], 10) : 0;
            if (!userId) return;

            const instructorId = self.config.instructorId || 0;
            const isInstructor = (userId === instructorId || participant.name === self.config.instructorName);

            if (track.kind === 'video') {
                if (isInstructor && !self.config.isModerator) {
                    // 1. Attach to Speaker View Main Stage for Students
                    let spkVideo = document.getElementById('livekit-speaker-video');
                    const speakerCard = document.querySelector('.instructor-stage-card');
                    if (!spkVideo && speakerCard) {
                        spkVideo = document.createElement('video');
                        spkVideo.id = 'livekit-speaker-video';
                        spkVideo.className = 'w-100 h-100 position-absolute top-0 start-0';
                        spkVideo.style.objectFit = 'cover';
                        spkVideo.style.borderRadius = '12px';
                        spkVideo.style.zIndex = '1';
                        spkVideo.autoplay = true;
                        spkVideo.playsInline = true;
                        speakerCard.prepend(spkVideo);
                    }
                    if (spkVideo) {
                        if (spkVideo.srcObject !== track.mediaStreamTrack) {
                            track.attach(spkVideo);
                        }
                        spkVideo.style.display = 'block';
                        $('#speaker-fallback-stage').hide();
                    }

                    // 2. Attach to Gallery Grid Instructor Tile
                    let instVideo = document.getElementById('livekit-inst-gallery-video');
                    const instTile = document.querySelector('.instructor-tile');
                    if (!instVideo && instTile) {
                        instVideo = document.createElement('video');
                        instVideo.id = 'livekit-inst-gallery-video';
                        instVideo.className = 'w-100 h-100 position-absolute top-0 start-0';
                        instVideo.style.objectFit = 'cover';
                        instVideo.style.zIndex = '2';
                        instVideo.autoplay = true;
                        instVideo.playsInline = true;
                        instTile.prepend(instVideo);
                    }
                    if (instVideo) {
                        if (instVideo.srcObject !== track.mediaStreamTrack) {
                            track.attach(instVideo);
                        }
                        instVideo.style.display = 'block';
                        $('#fallback-instructor-avatar').hide();
                    }
                } else {
                    // Student Video Tile in Gallery Grid
                    const tileEl = document.getElementById(`gallery-tile-${userId}`);
                    if (tileEl) {
                        let studentVideo = document.getElementById(`livekit-video-${userId}`);
                        if (!studentVideo) {
                            studentVideo = document.createElement('video');
                            studentVideo.id = `livekit-video-${userId}`;
                            studentVideo.className = 'w-100 h-100 position-absolute top-0 start-0';
                            studentVideo.style.objectFit = 'cover';
                            studentVideo.style.zIndex = '2';
                            studentVideo.autoplay = true;
                            studentVideo.playsInline = true;
                            tileEl.prepend(studentVideo);
                        }
                        if (studentVideo.srcObject !== track.mediaStreamTrack) {
                            track.attach(studentVideo);
                        }
                        studentVideo.style.display = 'block';
                        $(`#fallback-avatar-${userId}`).hide();
                    }
                }
            }

            if (track.kind === 'audio') {
                if (userId !== self.config.currentUserId) {
                    let audioEl = document.getElementById(`livekit-audio-${userId}`);
                    if (!audioEl) {
                        audioEl = document.createElement('audio');
                        audioEl.id = `livekit-audio-${userId}`;
                        audioEl.autoplay = true;
                        document.body.appendChild(audioEl);
                    }
                    if (audioEl.srcObject !== track.mediaStreamTrack) {
                        track.attach(audioEl);
                    }
                }
            }
        }

        handleTrackUnsubscribed(track, participant) {
            const uidMatch = (participant.identity || '').match(/user_(\d+)/);
            const userId = uidMatch ? parseInt(uidMatch[1], 10) : 0;
            const instructorId = this.config.instructorId || 0;
            const isInstructor = (userId === instructorId || participant.name === this.config.instructorName);

            track.detach().forEach(el => el.remove());

            if (track.kind === 'video') {
                if (isInstructor) {
                    $('#speaker-fallback-stage').show();
                    $('#fallback-instructor-avatar').show();
                } else {
                    $(`#fallback-avatar-${userId}`).show();
                }
            }
        }

        handleTrackMuteState(participant, isMuted) {
            const uidMatch = (participant.identity || '').match(/user_(\d+)/);
            const userId = uidMatch ? parseInt(uidMatch[1], 10) : 0;
            if (!userId) return;

            const tile = document.getElementById(`gallery-tile-${userId}`);
            if (tile) {
                const micIcon = tile.querySelector('.fa-microphone, .fa-microphone-slash');
                if (micIcon) {
                    if (isMuted) {
                        micIcon.className = 'fa fa-microphone-slash text-muted';
                    } else {
                        micIcon.className = 'fa fa-microphone text-success';
                    }
                }
            }
        }

        async initMediaStream() {
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: { width: 640, height: 480 }
                });
                const videoEl = document.getElementById('local-video');
                if (videoEl) {
                    videoEl.srcObject = this.localStream;
                }

                // Dedicated offscreen capture video element for snapshot fallback
                this.captureVideo = document.createElement('video');
                this.captureVideo.muted = true;
                this.captureVideo.playsInline = true;
                this.captureVideo.autoplay = true;
                this.captureVideo.srcObject = this.localStream;
                this.captureVideo.play().catch(() => {});

                // Mirror for gallery instructor tile
                if (this.config.isModerator) {
                    const giv = document.getElementById('gallery-instructor-video');
                    if (giv) giv.srcObject = this.localStream;
                }
            } catch (err) {
                console.warn('[LiveClass] Camera/Mic access denied or partially unavailable:', err);
                try {
                    this.localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                } catch (e) {
                    console.error('[LiveClass] Complete media permission failure:', e);
                }
            }
        }

        toggleLayout() {
            this.layoutMode = (this.layoutMode === 'speaker') ? 'gallery' : 'speaker';
            const speakerEl = document.getElementById('container-speaker');
            const galleryEl = document.getElementById('container-gallery');

            if (this.layoutMode === 'gallery') {
                if (speakerEl) speakerEl.style.display = 'none';
                if (galleryEl) galleryEl.style.display = 'block';
                $('#layout-btn-label').text('وضع الشرح (Speaker)');
                $('#btn-toggle-layout').find('i').removeClass('fa-th-large').addClass('fa-television');
                $('#btn-toggle-layout').removeClass('btn-outline-info').addClass('btn-info text-white');
            } else {
                if (galleryEl) galleryEl.style.display = 'none';
                if (speakerEl) speakerEl.style.display = 'flex';
                $('#layout-btn-label').text('عرض الشبكة (Gallery)');
                $('#btn-toggle-layout').find('i').removeClass('fa-television').addClass('fa-th-large');
                $('#btn-toggle-layout').removeClass('btn-info text-white').addClass('btn-outline-info');
            }
            this.renderParticipants();
        }

        playHandRaiseChime() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sine';
                const now = ctx.currentTime;
                // Dual tone bell chime: 880Hz (A5) then 1318.5Hz (E6)
                osc.frequency.setValueAtTime(880, now);
                osc.frequency.setValueAtTime(1318.5, now + 0.15);

                gain.gain.setValueAtTime(0.25, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.6);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(now);
                osc.stop(now + 0.6);
            } catch (e) {
                console.warn('[LiveClass] Web Audio chime unavailable:', e);
            }
        }

        renderParticipants() {
            const self = this;
            if (!self.participants || self.participants.length === 0) return;

            // 1. Render / Update Gallery Grid
            const gridEl = $('#gallery-grid');
            if (gridEl.length) {
                // Ensure Instructor Tile exists
                let instTile = $('#gallery-instructor-tile');
                const instructorTitle = escHtml(self.config.isModerator ? 
                    `${self.config.displayName} (أنت - المدرب)` : 
                    (self.config.instructorName || 'المحاضر'));

                if (!instTile.length) {
                    const instHtml = `
                        <div class="gallery-tile instructor-tile shadow position-relative" id="gallery-instructor-tile">
                            <div class="position-absolute top-0 start-0 m-2 z-2">
                                <span class="badge bg-danger rounded-pill px-2 py-1"><i class="fa fa-shield"></i> المدرب</span>
                            </div>
                            ${self.config.isModerator ? `
                                <video id="gallery-instructor-video" class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; z-index:2;" autoplay playsinline muted></video>
                            ` : `
                                <video id="livekit-inst-gallery-video" class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; z-index:2; display:none;" autoplay playsinline></video>
                                <div id="fallback-instructor-avatar" class="d-flex flex-column align-items-center justify-content-center my-auto z-1">
                                    <div class="badge bg-danger bg-opacity-25 text-danger rounded-circle p-3 mb-2">
                                        <i class="fa fa-graduation-cap fa-2x"></i>
                                    </div>
                                </div>
                            `}
                            <div class="tile-name mt-auto z-3">${instructorTitle}</div>
                            <div class="tile-footer z-3">
                                <span class="text-success small"><i class="fa fa-circle"></i> بث مباشر</span>
                                <span class="text-white small"><i class="fa fa-microphone"></i></span>
                            </div>
                        </div>
                    `;
                    gridEl.prepend(instHtml);
                    if (self.config.isModerator && self.localStream) {
                        const giv = document.getElementById('gallery-instructor-video');
                        if (giv && giv.srcObject !== self.localStream) {
                            giv.srcObject = self.localStream;
                            giv.play().catch(() => {});
                        }
                    }
                }

                // Gather user IDs actively connected via LiveKit room
                const livekitConnectedUids = new Set();
                if (self.livekitRoom && self.livekitRoom.remoteParticipants) {
                    self.livekitRoom.remoteParticipants.forEach(p => {
                        const m = (p.identity || '').match(/user_(\d+)/);
                        if (m) livekitConnectedUids.add(parseInt(m[1], 10));
                    });
                }

                // Connected students (in class or in LiveKit)
                const connectedStudents = (self.participants || []).filter(st => {
                    if (st.id === self.config.currentUserId) return false;
                    return st.status === 'in_class' || livekitConnectedUids.has(st.id);
                });

                if (connectedStudents.length === 0) {
                    // Remove any old student tiles
                    gridEl.find('.student-tile').remove();
                    if (!$('#gallery-empty-notice').length) {
                        gridEl.append(`
                            <div id="gallery-empty-notice" class="gallery-empty-notice d-flex flex-column align-items-center justify-content-center p-4 text-center my-auto" style="grid-column: 2 / -1; min-height: 220px;">
                                <div class="badge bg-secondary bg-opacity-25 text-white-50 rounded-circle p-3 mb-2">
                                    <i class="fa fa-users fa-2x"></i>
                                </div>
                                <div class="text-white-50 small">لا يوجد متدربون متصلون حالياً في القاعة الافتراضية</div>
                                <div class="text-secondary small mt-1" style="font-size:0.75rem;">(يظهر المتدربون هنا تلقائياً فور انضمامهم للبث)</div>
                            </div>
                        `);
                    }
                } else {
                    $('#gallery-empty-notice').remove();
                    const activeUids = new Set(connectedStudents.map(s => s.id));

                    // Prune stale student tiles without touching active tiles
                    gridEl.find('.student-tile').each(function() {
                        const uid = parseInt($(this).data('userid'), 10);
                        if (!activeUids.has(uid)) {
                            $(this).remove();
                        }
                    });

                    // Add or update active student tiles incrementally
                    connectedStudents.forEach(st => {
                        const existingTile = $(`#gallery-tile-${st.id}`);
                        if (existingTile.length) {
                            // Incremental Update: DO NOT touch the playing <video> element!
                            existingTile.find('.badge-percent').text(`${st.percent}%`);
                            const handEl = existingTile.find('.hand-badge');
                            if (st.handRaised && !handEl.length) {
                                existingTile.prepend(`<div class="hand-badge animate-bounce ${self.config.isModerator ? 'hand-badge-clickable' : ''}" data-userid="${st.id}" title="${self.config.isModerator ? 'انقر لخفض يد الطالب' : 'رفع اليد للمشاركة'}">✋</div>`);
                            } else if (!st.handRaised && handEl.length) {
                                handEl.remove();
                            }
                        } else {
                            // New student joined: append tile
                            const newTileHtml = `
                                <div class="gallery-tile student-tile tile-active shadow position-relative" id="gallery-tile-${escHtml(Math.floor(Number(st.id) || 0))}" data-userid="${escHtml(Math.floor(Number(st.id) || 0))}">
                                    ${st.handRaised ? `<div class="hand-badge animate-bounce ${self.config.isModerator ? 'hand-badge-clickable' : ''}" data-userid="${escHtml(Math.floor(Number(st.id) || 0))}" title="${escHtml(self.config.isModerator ? 'انقر لخفض يد الطالب' : 'رفع اليد للمشاركة')}">✋</div>` : ''}
                                    <video id="livekit-video-${escHtml(Math.floor(Number(st.id) || 0))}" class="w-100 h-100 position-absolute top-0 start-0" style="object-fit: cover; z-index: 2; display: none;" autoplay playsinline></video>
                                    <img id="fallback-avatar-${escHtml(Math.floor(Number(st.id) || 0))}" src="${escHtml(st.avatar)}" class="tile-avatar shadow-sm" alt="${escHtml(st.fullname || '')}" onerror="this.src=M.cfg.wwwroot+'/pix/u/f1.png'">
                                    <div class="tile-name mt-auto z-3" title="${escHtml(st.fullname || '')}">${escHtml(st.fullname || '')}</div>
                                    <div class="tile-footer z-3">
                                        <div><span class="badge bg-success-subtle text-success border border-success"><span class="pulse-dot pulse-dot-green"></span> متصل</span></div>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="text-muted small"><i class="fa fa-microphone text-success"></i></span>
                                            <span class="badge bg-secondary badge-percent" style="font-size: 0.65rem;">${escHtml(st.percent)}%</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                            gridEl.append(newTileHtml);

                            // Check if this student already published a track in LiveKit
                            if (self.livekitRoom && self.livekitRoom.remoteParticipants) {
                                const rp = Array.from(self.livekitRoom.remoteParticipants.values()).find(p => {
                                    const m = (p.identity || '').match(/user_(\d+)/);
                                    return m && parseInt(m[1], 10) === st.id;
                                });
                                if (rp) {
                                    const pubs = (typeof rp.getTracks === 'function') ? rp.getTracks() : (rp.tracks ? Array.from(rp.tracks.values()) : []);
                                    pubs.forEach(pub => {
                                        if (pub && pub.isSubscribed && pub.track && pub.track.kind === 'video') {
                                            self.handleTrackSubscribed(pub.track, pub, rp);
                                        }
                                    });
                                }
                            }
                        }
                    });
                }
            }

            // 2. Render Speaker View Filmstrip Incrementally
            const filmstripEl = $('#speaker-filmstrip');
            if (filmstripEl.length) {
                const livekitConnectedUids = new Set();
                if (self.livekitRoom && self.livekitRoom.remoteParticipants) {
                    self.livekitRoom.remoteParticipants.forEach(p => {
                        const m = (p.identity || '').match(/user_(\d+)/);
                        if (m) livekitConnectedUids.add(parseInt(m[1], 10));
                    });
                }
                const connectedStudents = (self.participants || []).filter(st => {
                    return st.status === 'in_class' || livekitConnectedUids.has(st.id);
                });
                if (connectedStudents.length > 0) {
                    filmstripEl.removeClass('d-none');
                    let filmstripHtml = '';
                    connectedStudents.forEach(st => {
                        filmstripHtml += `
                            <div class="filmstrip-card card-active shadow-sm" title="${escHtml(st.fullname || '')}">
                                ${st.handRaised ? `<div class="hand-badge ${self.config.isModerator ? 'hand-badge-clickable' : ''}" data-userid="${escHtml(st.id)}" style="top:2px; right:2px; width:22px; height:22px; font-size:0.75rem;" title="${escHtml(self.config.isModerator ? 'انقر لخفض يد الطالب' : '')}">✋</div>` : ''}
                                <img src="${escHtml(st.avatar)}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #22c55e;" onerror="this.src=M.cfg.wwwroot+'/pix/u/f1.png'">
                                <div class="text-white text-truncate small mt-1 w-100 text-center" style="font-size: 0.75rem;">${escHtml(st.fullname || '')}</div>
                                <span class="badge bg-success" style="font-size: 0.6rem;">${escHtml(st.percent)}%</span>
                            </div>
                        `;
                    });
                    filmstripEl.html(filmstripHtml);
                } else {
                    filmstripEl.addClass('d-none');
                }
            }
        }

        startHeartbeatLoop() {
            const self = this;

            // 1. Send immediate Join notice to register active interval
            $.ajax({
                url: M.cfg.wwwroot + '/mod/liveclass/heartbeat.php',
                type: 'POST',
                data: {
                    action: 'join',
                    sesskey: self.config.sesskey,
                    cmid: self.config.cmid,
                    sessionid: self.config.sessionId
                },
                dataType: 'json',
                success: function() {
                    self.fetchHeartbeat();
                }
            });

            // 2. Continuous 10-second heartbeat loop
            this.heartbeatInterval = setInterval(() => {
                self.fetchHeartbeat();
            }, 10000);
        }

        fetchHeartbeat() {
            const self = this;
            $.ajax({
                url: M.cfg.wwwroot + '/mod/liveclass/heartbeat.php',
                type: 'POST',
                data: {
                    action: 'heartbeat',
                    sesskey: self.config.sesskey,
                    cmid: self.config.cmid,
                    sessionid: self.config.sessionId,
                    mic: self.isMicMuted ? 0 : 1,
                    cam: self.isCamOff ? 0 : 1,
                    hand: self.isHandRaised ? 1 : 0,
                    screen: self.isScreenSharing ? 1 : 0
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.status === 'ended') {
                        self.leaveClassroom();
                        return;
                    }
                    if (res && res.status === 'success') {
                        if (res.active_students !== undefined) {
                            $('#online-students-count').text(res.active_students);
                        }

                        if (res.participants) {
                            self.participants = res.participants;

                            // Hand raise detection & chime notification
                            const currentRaised = new Set();
                            res.participants.forEach(st => {
                                if (st.handRaised) {
                                    currentRaised.add(st.id);
                                    if (!self.raisedHandUsers.has(st.id) && st.id !== self.config.currentUserId) {
                                        // New hand raised by student!
                                        self.playHandRaiseChime();
                                        $('#hand-raise-student-name').text(st.fullname);
                                        $('#hand-raise-alert').removeClass('d-none');

                                        if (self.handAlertTimeout) clearTimeout(self.handAlertTimeout);
                                        self.handAlertTimeout = setTimeout(() => {
                                            $('#hand-raise-alert').addClass('d-none');
                                        }, 8000);
                                    }
                                }
                            });
                            self.raisedHandUsers = currentRaised;

                            self.renderParticipants();
                        }

                        // Reconcile student's own hand raise state with authoritative server state
                        if (!self.config.isModerator && res.my_hand_raised !== undefined) {
                            if (self.isHandRaised !== res.my_hand_raised) {
                                self.isHandRaised = res.my_hand_raised;
                                $('#btn-raise-hand').toggleClass('btn-outline-warning btn-warning text-dark', self.isHandRaised);
                                if (!self.isHandRaised) {
                                    self.showToast('info', 'تم خفض يدك من قِبل المحاضر');
                                }
                            }
                        }

                        // Remote Mute enforcement from moderator
                        if (res.must_mute && !self.isMicMuted && !self.config.isModerator) {
                            self.muteLocalMic();
                            $('#mute-notice').removeClass('d-none');
                            setTimeout(() => { $('#mute-notice').addClass('d-none'); }, 5000);
                        }

                        // Recording badge sync
                        if (res.is_recording) {
                            $('#recording-badge').removeClass('d-none');
                        } else if (self.config.recordingMode !== 1) {
                            $('#recording-badge').addClass('d-none');
                        }
                    }
                }
            });
        }

        muteLocalMic() {
            this.isMicMuted = true;
            if (this.livekitRoom && this.livekitRoom.localParticipant) {
                try {
                    this.livekitRoom.localParticipant.setMicrophoneEnabled(false);
                } catch (e) {
                    console.warn('[LiveClass] LiveKit mic disable error:', e);
                }
            }
            if (this.localStream) {
                const audioTrack = this.localStream.getAudioTracks()[0];
                if (audioTrack) {
                    audioTrack.enabled = false;
                }
            }
            $('#btn-toggle-mic').removeClass('btn-outline-light').addClass('btn-danger');
            $('#btn-toggle-mic').find('i').removeClass('fa-microphone').addClass('fa-microphone-slash');
            this.logEvent('mic_muted', 'Microphone muted by moderator');
        }

        sendLeaveBeacon() {
            const url = M.cfg.wwwroot + '/mod/liveclass/heartbeat.php?action=leave&cmid=' + 
                        this.config.cmid + '&sessionid=' + this.config.sessionId + '&sesskey=' + this.config.sesskey;
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url);
            } else {
                $.ajax({ url: url, type: 'GET', async: false });
            }
        }

        bindToolbarControls() {
            const self = this;

            // Layout View Toggle Button (Speaker vs Gallery)
            $('#btn-toggle-layout').on('click', function() {
                self.toggleLayout();
            });

            // Dismiss Hand Raise Alert Banner
            $('#btn-dismiss-hand-alert').on('click', function() {
                $('#hand-raise-alert').addClass('d-none');
            });

            $('#btn-toggle-mic').on('click', async function() {
                if (self.livekitRoom && self.livekitRoom.localParticipant) {
                    const isMuted = self.isMicMuted;
                    try {
                        await self.livekitRoom.localParticipant.setMicrophoneEnabled(isMuted);
                        self.isMicMuted = !isMuted;
                        $(this).toggleClass('btn-outline-light btn-danger', self.isMicMuted);
                        $(this).find('i').toggleClass('fa-microphone fa-microphone-slash');
                        self.logEvent(self.isMicMuted ? 'mic_muted' : 'mic_unmuted', 'Microphone state toggled');
                        self.markActivity();
                    } catch (e) {
                        console.warn('[LiveClass] Toggle mic error:', e);
                    }
                } else if (self.localStream) {
                    const audioTrack = self.localStream.getAudioTracks()[0];
                    if (audioTrack) {
                        self.isMicMuted = !self.isMicMuted;
                        audioTrack.enabled = !self.isMicMuted;
                        $(this).toggleClass('btn-outline-light btn-danger', self.isMicMuted);
                        $(this).find('i').toggleClass('fa-microphone fa-microphone-slash');
                        self.logEvent(self.isMicMuted ? 'mic_muted' : 'mic_unmuted', 'Microphone state toggled');
                    }
                }
            });

            $('#btn-toggle-cam').on('click', async function() {
                const LK = (LivekitClient && LivekitClient.Room) ? LivekitClient : (window.LivekitClient || {});
                if (self.livekitRoom && self.livekitRoom.localParticipant) {
                    const isOff = self.isCamOff;
                    try {
                        const isMod = !!self.config.isModerator;
                        const targetPreset = isMod ?
                            (LK.VideoPresets && LK.VideoPresets.h720 ? LK.VideoPresets.h720 : { resolution: { width: 1280, height: 720 } }) :
                            (LK.VideoPresets && LK.VideoPresets.h540 ? LK.VideoPresets.h540 : { resolution: { width: 960, height: 540 } });

                        await self.livekitRoom.localParticipant.setCameraEnabled(isOff, {
                            resolution: targetPreset.resolution,
                            facingMode: 'user'
                        });
                        self.isCamOff = !isOff;
                        $(this).toggleClass('btn-outline-light btn-danger', self.isCamOff);
                        $(this).find('i').toggleClass('fa-video-camera fa-eye-slash');
                        self.logEvent(self.isCamOff ? 'cam_disabled' : 'cam_enabled', 'Camera state toggled');

                        const camPub = self.livekitRoom.localParticipant.getTrackPublication(LK.Track.Source.Camera);
                        if (camPub && camPub.track && !self.isCamOff) {
                            const lv = document.getElementById('local-video');
                            if (lv) camPub.track.attach(lv);
                            if (self.config.isModerator) {
                                const giv = document.getElementById('gallery-instructor-video');
                                if (giv) camPub.track.attach(giv);
                            }
                        }
                        self.markActivity();
                    } catch (e) {
                        console.warn('[LiveClass] Toggle cam error:', e);
                    }
                } else if (self.localStream) {
                    const videoTrack = self.localStream.getVideoTracks()[0];
                    if (videoTrack) {
                        self.isCamOff = !self.isCamOff;
                        videoTrack.enabled = !self.isCamOff;
                        $(this).toggleClass('btn-outline-light btn-danger', self.isCamOff);
                        $(this).find('i').toggleClass('fa-video-camera fa-eye-slash');
                        self.logEvent(self.isCamOff ? 'cam_disabled' : 'cam_enabled', 'Camera state toggled');
                        self.markActivity();
                    }
                }
            });

            $('#btn-toggle-screen').on('click', async function() {
                if (self.livekitRoom && self.livekitRoom.localParticipant) {
                    if (!self.isScreenSharing) {
                        try {
                            await self.livekitRoom.localParticipant.setScreenShareEnabled(true);
                            self.isScreenSharing = true;
                            $(this).removeClass('btn-outline-light').addClass('btn-success');
                            self.logEvent('screenshare_started', 'User initiated screen share');
                        } catch (err) {
                            console.warn('[LiveClass] Screen share cancelled:', err);
                        }
                    } else {
                        await self.livekitRoom.localParticipant.setScreenShareEnabled(false);
                        self.isScreenSharing = false;
                        $(this).removeClass('btn-success').addClass('btn-outline-light');
                        self.logEvent('screenshare_stopped', 'Screen share ended');
                    }
                } else {
                    if (!self.isScreenSharing) {
                        try {
                            self.screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                            const videoEl = document.getElementById('local-video');
                            if (videoEl) {
                                videoEl.srcObject = self.screenStream;
                            }
                            self.isScreenSharing = true;
                            $(this).removeClass('btn-outline-light').addClass('btn-success');
                            self.logEvent('screenshare_started', 'User initiated screen share');

                            self.screenStream.getVideoTracks()[0].onended = () => {
                                self.stopScreenShare();
                            };
                        } catch (err) {
                            console.warn('[LiveClass] Screen share cancelled:', err);
                        }
                    } else {
                        self.stopScreenShare();
                    }
                }
            });

            $('#btn-raise-hand').on('click', function() {
                self.isHandRaised = !self.isHandRaised;
                $(this).toggleClass('btn-outline-warning btn-warning text-dark', self.isHandRaised);
                self.logEvent(self.isHandRaised ? 'raise_hand' : 'lower_hand', 'Student toggled hand raise');
                self.markActivity();

                // Instant LiveKit DataChannel broadcast to instructor and peers
                if (self.livekitRoom && self.livekitRoom.localParticipant) {
                    try {
                        const LK = (LivekitClient && LivekitClient.Room) ? LivekitClient : (window.LivekitClient || {});
                        const payload = new TextEncoder().encode(JSON.stringify({
                            type: 'hand_state_changed',
                            userid: self.config.currentUserId,
                            fullname: self.config.displayName,
                            handRaised: self.isHandRaised
                        }));
                        const kind = (LK.DataPacket_Kind && LK.DataPacket_Kind.RELIABLE !== undefined) ? LK.DataPacket_Kind.RELIABLE : 0;
                        self.livekitRoom.localParticipant.publishData(payload, kind);
                    } catch (e) {
                        console.warn('[LiveClass] Hand raise publish error:', e);
                    }
                }

                self.fetchHeartbeat();
            });

            // Moderator Command: Mute all participants
            $('#btn-stage-mute-all').on('click', function() {
                const LK = (LivekitClient && LivekitClient.Room) ? LivekitClient : (window.LivekitClient || {});
                if (confirm('هل أنت متأكد من كتم صوت الميكروفون لجميع المتدربين؟')) {
                    // 1. Instant LiveKit WebRTC broadcast
                    if (self.livekitRoom && self.livekitRoom.localParticipant) {
                        try {
                            const payload = new TextEncoder().encode(JSON.stringify({ type: 'mute_all' }));
                            const kind = (LK.DataPacket_Kind && LK.DataPacket_Kind.RELIABLE !== undefined) ? LK.DataPacket_Kind.RELIABLE : 0;
                            self.livekitRoom.localParticipant.publishData(payload, kind);
                        } catch (e) {
                            console.warn('[LiveClass] WebRTC DataChannel broadcast error:', e);
                        }
                    }
                    // 2. Persist in presence logs via control.php for HTTP polling fallback
                    fetch(M.cfg.wwwroot + '/mod/liveclass/control.php?cmid=' + self.config.cmid + '&action=muteall&sesskey=' + self.config.sesskey)
                        .then(r => r.json())
                        .then(res => {
                            self.showToast('success', res.message || 'تم كتم صوت الميكروفون لجميع المتدربين بنجاح');
                        })
                        .catch(err => {
                            console.error('[LiveClass] Mute all error:', err);
                        });
                }
            });

            // Moderator Command: Lower all hands
            $('#btn-stage-lower-hands').on('click', function() {
                const LK = (LivekitClient && LivekitClient.Room) ? LivekitClient : (window.LivekitClient || {});
                // 1. Instant DataChannel broadcast to all students
                if (self.livekitRoom && self.livekitRoom.localParticipant) {
                    try {
                        const payload = new TextEncoder().encode(JSON.stringify({ type: 'lower_all_hands' }));
                        const kind = (LK.DataPacket_Kind && LK.DataPacket_Kind.RELIABLE !== undefined) ? LK.DataPacket_Kind.RELIABLE : 0;
                        self.livekitRoom.localParticipant.publishData(payload, kind);
                    } catch (e) {
                        console.warn('[LiveClass] Lower all hands WebRTC broadcast error:', e);
                    }
                }
                self.raisedHandUsers = new Set();
                $('#hand-raise-alert').addClass('d-none');
                $('.gallery-tile .hand-badge').remove();

                // 2. Persist in DB via control.php
                fetch(M.cfg.wwwroot + '/mod/liveclass/control.php?cmid=' + self.config.cmid + '&action=lower_all_hands&sesskey=' + self.config.sesskey)
                    .then(r => r.json())
                    .then(res => {
                        self.showToast('success', res.message || 'تم خفض جميع الأيدي المرتفعة بنجاح');
                        self.fetchHeartbeat();
                    })
                    .catch(err => {
                        console.error('[LiveClass] Lower all hands error:', err);
                    });
            });

            // Moderator Command: Lower single student hand on tile click
            $(document).on('click', '.hand-badge-clickable', function(e) {
                e.stopPropagation();
                if (!self.config.isModerator) return;
                const uid = parseInt($(this).data('userid'), 10);
                if (uid) {
                    self.lowerStudentHand(uid);
                }
            });

            // Moderator Command: Toggle Recording (recordingMode === 2)
            $('#btn-toggle-recording').on('click', function() {
                const action = self.isRecording ? 'stop_recording' : 'start_recording';
                const confirmPrompt = self.isRecording ? 'هل أنت متأكد من إيقاف تسجيل الحصة؟' : 'هل تريد بدء تسجيل الحصة الآن؟';

                if (confirm(confirmPrompt)) {
                    fetch(M.cfg.wwwroot + '/mod/liveclass/control.php?cmid=' + self.config.cmid + '&action=' + action + '&sesskey=' + self.config.sesskey)
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === 'success') {
                                self.isRecording = !self.isRecording;
                                if (self.isRecording) {
                                    $('#recording-badge').removeClass('d-none');
                                    $('#btn-toggle-recording')
                                        .removeClass('btn-outline-danger')
                                        .addClass('btn-danger')
                                        .find('i').removeClass('fa-circle').addClass('fa-stop');
                                    $('#recording-btn-label').text('إيقاف التسجيل');
                                } else {
                                    $('#recording-badge').addClass('d-none');
                                    $('#btn-toggle-recording')
                                        .removeClass('btn-danger')
                                        .addClass('btn-outline-danger')
                                        .find('i').removeClass('fa-stop').addClass('fa-circle');
                                    $('#recording-btn-label').text('بدء التسجيل');
                                }
                                alert(res.message);
                            }
                        })
                        .catch(err => {
                            console.error('[LiveClass] Recording control error:', err);
                        });
                }
            });

            $('#btn-fullscreen').on('click', function() {
                const stageEl = document.getElementById('liveclass-stage');
                if (!document.fullscreenElement) {
                    stageEl.requestFullscreen().catch(err => console.error(err));
                } else {
                    document.exitFullscreen();
                }
            });

            $('#btn-leave').on('click', function() {
                if (confirm('هل تريد بالتأكيد مغادرة الحصة الافتراضية؟')) {
                    self.leaveClassroom();
                }
            });

            // Student Poll Answer Submit
            $(document).on('submit', '#poll-answer-form', function(e) {
                e.preventDefault();
                const selected = $('input[name="poll_option"]:checked').val();
                if (!selected) {
                    alert('يرجى اختيار إجابة أولاً');
                    return;
                }

                $.ajax({
                    url: M.cfg.wwwroot + '/mod/liveclass/poll_api.php',
                    type: 'POST',
                    data: {
                        action: 'answer',
                        sesskey: self.config.sesskey,
                        cmid: self.config.cmid,
                        poll_id: self.activePollId,
                        answer: selected
                    },
                    dataType: 'json',
                    success: function() {
                        $('#poll-overlay').addClass('d-none');
                        self.logEvent('poll_answered', `Answered poll ${self.activePollId}: ${selected}`);
                    }
                });
            });
        }

        stopScreenShare() {
            if (this.screenStream) {
                this.screenStream.getTracks().forEach(t => t.stop());
                this.screenStream = null;
            }
            const videoEl = document.getElementById('local-video');
            if (videoEl && this.localStream) {
                videoEl.srcObject = this.localStream;
            }
            this.isScreenSharing = false;
            $('#btn-toggle-screen').removeClass('btn-success').addClass('btn-outline-light');
            this.logEvent('screenshare_stopped', 'Screen share ended');
        }

        startAttendanceTimer() {
            const self = this;
            this.timerInterval = setInterval(() => {
                self.elapsedSeconds++;

                const hrs = String(Math.floor(self.elapsedSeconds / 3600)).padStart(2, '0');
                const mins = String(Math.floor((self.elapsedSeconds % 3600) / 60)).padStart(2, '0');
                const secs = String(self.elapsedSeconds % 60).padStart(2, '0');
                $('#live-timer').html(`<i class="fa fa-clock-o"></i> ${hrs}:${mins}:${secs}`);

                const totalSessionSecs = Math.max(1, self.config.endTime - self.config.startTime);
                const currentPct = Math.min(100, Math.round((self.elapsedSeconds / totalSessionSecs) * 100));

                $('#attendance-status-badge').html(`<i class="fa fa-percent"></i> ${currentPct}% الحضور`);
                $('#student-live-pct').text(`${currentPct}% الحضور`);
                $('#attendance-progress').css('width', currentPct + '%').text(currentPct + '%');

                if (currentPct >= self.config.minAttendancePct) {
                    $('#attendance-progress').removeClass('bg-info').addClass('bg-success');
                    $('#attendance-status-badge').removeClass('bg-info').addClass('bg-success');
                }
            }, 1000);
        }

        startPollWatcher() {
            const self = this;
            this.pollInterval = setInterval(() => {
                $.getJSON(M.cfg.wwwroot + '/mod/liveclass/poll_api.php', {
                    action: 'get_active',
                    cmid: self.config.cmid,
                    sesskey: self.config.sesskey
                }, function(data) {
                    if (data && data.status === 'active' && data.poll) {
                        const p = data.poll;
                        if (!p.has_answered && p.time_left > 0 && self.activePollId !== p.id) {
                            self.activePollId = p.id;
                            self.showPollPopup(p);
                        }
                    }
                });
            }, 8000);
        }

        showPollPopup(poll) {
            $('#poll-question-text').text(poll.question || '');
            const optionsContainer = document.getElementById('poll-options-container');
            if (optionsContainer) {
                optionsContainer.innerHTML = '';
                (poll.options || []).forEach((opt, idx) => {
                    const div = document.createElement('div');
                    div.className = 'form-check p-2 rounded border mb-2 bg-light';
                    const input = document.createElement('input');
                    input.className = 'form-check-input ms-2';
                    input.type = 'radio';
                    input.name = 'poll_option';
                    input.id = 'poll_opt_' + idx;
                    input.value = String(opt); // Safe: value attribute, not HTML interpreted.
                    input.required = true;
                    const label = document.createElement('label');
                    label.className = 'form-check-label fw-bold cursor-pointer';
                    label.htmlFor = 'poll_opt_' + idx;
                    label.textContent = String(opt); // Safe: textContent, no HTML interpretation.
                    div.appendChild(input);
                    div.appendChild(label);
                    optionsContainer.appendChild(div);
                });
            }
            $('#poll-overlay').removeClass('d-none');

            let timeLeft = poll.time_left;
            const totalDuration = poll.duration || 30;

            const countdown = setInterval(() => {
                timeLeft--;
                $('#poll-timer-badge').text(timeLeft + 's');
                const pct = Math.max(0, Math.round((timeLeft / totalDuration) * 100));
                $('#poll-progress-bar').css('width', pct + '%');

                if (timeLeft <= 0) {
                    clearInterval(countdown);
                    $('#poll-overlay').addClass('d-none');
                }
            }, 1000);
        }

        handleTabHidden() {
            this.tabSwitchCount++;
            $('#presence-alert').removeClass('d-none');
            this.logEvent('tab_hidden', `Tab hidden count: ${this.tabSwitchCount}`);
        }

        handleTabRestored() {
            setTimeout(() => {
                $('#presence-alert').addClass('d-none');
            }, 3000);
            this.logEvent('tab_restored', 'Student returned to classroom window');
        }

        logEvent(eventType, details) {
            $.ajax({
                url: M.cfg.wwwroot + '/mod/liveclass/log_event.php',
                type: 'POST',
                data: {
                    sesskey: this.config.sesskey,
                    cmid: this.config.cmid,
                    sessionid: this.config.sessionId,
                    eventtype: eventType,
                    details: details
                },
                dataType: 'json'
            });
        }

        lowerStudentHand(userId) {
            const self = this;
            const LK = (LivekitClient && LivekitClient.Room) ? LivekitClient : (window.LivekitClient || {});

            // 1. Instant DataChannel broadcast to target student
            if (self.livekitRoom && self.livekitRoom.localParticipant) {
                try {
                    const payload = new TextEncoder().encode(JSON.stringify({ type: 'lower_hand', userid: userId }));
                    const kind = (LK.DataPacket_Kind && LK.DataPacket_Kind.RELIABLE !== undefined) ? LK.DataPacket_Kind.RELIABLE : 0;
                    self.livekitRoom.localParticipant.publishData(payload, kind);
                } catch (e) {
                    console.warn('[LiveClass] Lower single hand publish error:', e);
                }
            }
            self.raisedHandUsers.delete(userId);
            $(`#gallery-tile-${userId} .hand-badge`).remove();
            if (self.raisedHandUsers.size === 0) {
                $('#hand-raise-alert').addClass('d-none');
            }

            // 2. Persist in DB via control.php
            fetch(M.cfg.wwwroot + '/mod/liveclass/control.php?cmid=' + self.config.cmid + '&action=lower_hand&target_userid=' + userId + '&sesskey=' + self.config.sesskey)
                .then(r => r.json())
                .then(res => {
                    self.showToast('success', res.message || 'تم خفض يد المتدرب');
                    self.fetchHeartbeat();
                })
                .catch(err => {
                    console.error('[LiveClass] Lower student hand error:', err);
                });
        }

        leaveClassroom() {
            this.sendLeaveBeacon();
            if (this.livekitRoom) {
                try {
                    this.livekitRoom.disconnect();
                } catch (e) {}
            }
            if (this.timerInterval) clearInterval(this.timerInterval);
            if (this.heartbeatInterval) clearInterval(this.heartbeatInterval);
            if (this.pollInterval) clearInterval(this.pollInterval);
            if (this.interactionCheckInterval) clearInterval(this.interactionCheckInterval);
            if (this.handAlertTimeout) clearTimeout(this.handAlertTimeout);
            if (this.localStream) this.localStream.getTracks().forEach(t => t.stop());
            if (this.screenStream) this.screenStream.getTracks().forEach(t => t.stop());
            window.location.reload();
        }
    }

    return {
        init: function(config) {
            return new LiveClassroom(config);
        }
    };
});
