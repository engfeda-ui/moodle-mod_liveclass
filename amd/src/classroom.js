/**
 * LiveClass WebRTC Virtual Classroom Controller.
 *
 * @module     mod_liveclass/classroom
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'core/ajax'], function($, Notification, Ajax) {
    'use strict';

    class LiveClassroom {
        constructor(config) {
            this.config = config;
            this.localStream = null;
            this.screenStream = null;
            this.isMicMuted = false;
            this.isCamOff = false;
            this.isScreenSharing = false;
            this.tabSwitchCount = 0;
            this.elapsedSeconds = 0;
            this.timerInterval = null;
            this.pollInterval = null;
            this.activePollId = 0;

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
        }

        startClassroom() {
            const self = this;
            const stage = $('#liveclass-stage');

            stage.html(`
                <div class="liveclass-active-workspace w-100 h-100 d-flex flex-column" style="min-height: 580px;">
                    <!-- Top Lecture Bar -->
                    <div class="liveclass-top-bar d-flex justify-content-between align-items-center p-2 bg-dark text-white border-bottom border-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger rounded-pill px-3 py-2 animate-pulse">
                                <i class="fa fa-circle"></i> مباشر
                            </span>
                            <span class="fw-bold ms-2">${self.config.roomName}</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-secondary p-2" id="live-timer"><i class="fa fa-clock-o"></i> 00:00:00</span>
                            <span class="badge bg-info p-2" id="attendance-status-badge"><i class="fa fa-percent"></i> 0% الحضور</span>
                        </div>
                    </div>

                    <!-- Main Media Viewport -->
                    <div class="liveclass-media-viewport flex-grow-1 d-flex position-relative bg-black">
                        <div id="video-grid" class="w-100 h-100 d-flex flex-wrap justify-content-center align-items-center p-2 gap-2 position-relative">
                            <div class="video-card local-video-card position-relative bg-dark rounded overflow-hidden shadow" style="width: 100%; max-width: 800px; height: 450px;">
                                <video id="local-video" class="w-100 h-100" style="object-fit: cover;" autoplay playsinline muted></video>
                                <div class="position-absolute bottom-0 start-0 p-2 bg-dark bg-opacity-75 text-white m-2 rounded small">
                                    <i class="fa fa-user"></i> ${self.config.displayName} ${self.config.isModerator ? '(المدرب)' : ''}
                                </div>
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
                    </div>

                    <!-- Bottom Controls Toolbar -->
                    <div class="liveclass-toolbar bg-dark p-3 d-flex justify-content-center align-items-center gap-3 border-top border-secondary flex-wrap">
                        <button id="btn-toggle-mic" class="btn btn-outline-light rounded-circle p-3" title="كتم/تشغيل المايك">
                            <i class="fa fa-microphone fa-lg"></i>
                        </button>
                        <button id="btn-toggle-cam" class="btn btn-outline-light rounded-circle p-3" title="تشغيل/إيقاف الكاميرا">
                            <i class="fa fa-video-camera fa-lg"></i>
                        </button>
                        <button id="btn-toggle-screen" class="btn btn-outline-light rounded-circle p-3" title="مشاركة الشاشة">
                            <i class="fa fa-desktop fa-lg"></i>
                        </button>
                        <button id="btn-raise-hand" class="btn btn-outline-warning rounded-circle p-3" title="رفع اليد">
                            <i class="fa fa-hand-paper-o fa-lg"></i>
                        </button>
                        ${self.config.isModerator ? `
                            <button id="btn-stage-mute-all" class="btn btn-danger rounded-pill px-3 py-2 shadow-sm" title="كتم صوت جميع الطلاب">
                                <i class="fa fa-microphone-slash"></i> كتم الجميع
                            </button>
                        ` : ''}
                        <button id="btn-fullscreen" class="btn btn-outline-light rounded-circle p-3" title="ملء الشاشة">
                            <i class="fa fa-arrows-alt fa-lg"></i>
                        </button>
                        <button id="btn-leave" class="btn btn-danger rounded-pill px-4 ms-2" title="مغادرة الحصة">
                            <i class="fa fa-phone"></i> مغادرة
                        </button>
                    </div>
                </div>
            `);

            self.initMediaStream();
            self.startAttendanceTimer();
            self.bindToolbarControls();
            self.startPollWatcher();
        }

        async initMediaStream() {
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: { width: 1280, height: 720 }
                });
                const videoEl = document.getElementById('local-video');
                if (videoEl) {
                    videoEl.srcObject = this.localStream;
                }
            } catch (err) {
                console.warn('[LiveClass] Camera/Mic access denied:', err);
                try {
                    this.localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                } catch (e) {
                    console.error('[LiveClass] Complete media permission failure:', e);
                }
            }
        }

        bindToolbarControls() {
            const self = this;

            $('#btn-toggle-mic').on('click', function() {
                if (self.localStream) {
                    const audioTrack = self.localStream.getAudioTracks()[0];
                    if (audioTrack) {
                        self.isMicMuted = !self.isMicMuted;
                        audioTrack.enabled = !self.isMicMuted;
                        $(this).toggleClass('btn-outline-light btn-danger');
                        $(this).find('i').toggleClass('fa-microphone fa-microphone-slash');
                        self.logEvent(self.isMicMuted ? 'mic_muted' : 'mic_unmuted', 'Microphone state toggled');
                    }
                }
            });

            $('#btn-toggle-cam').on('click', function() {
                if (self.localStream) {
                    const videoTrack = self.localStream.getVideoTracks()[0];
                    if (videoTrack) {
                        self.isCamOff = !self.isCamOff;
                        videoTrack.enabled = !self.isCamOff;
                        $(this).toggleClass('btn-outline-light btn-danger');
                        $(this).find('i').toggleClass('fa-video-camera fa-eye-slash');
                        self.logEvent(self.isCamOff ? 'cam_disabled' : 'cam_enabled', 'Camera state toggled');
                    }
                }
            });

            $('#btn-toggle-screen').on('click', async function() {
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
            });

            $('#btn-raise-hand').on('click', function() {
                $(this).toggleClass('btn-outline-warning btn-warning text-dark');
                self.logEvent('raise_hand', 'Student raised hand');
            });

            // Mute all participants from stage
            $('#btn-stage-mute-all').on('click', function() {
                if (confirm('هل أنت متأكد من كتم صوت الميكروفون لجميع المتدربين؟')) {
                    fetch(M.cfg.wwwroot + '/mod/liveclass/control.php?cmid=' + self.config.cmid + '&action=muteall&sesskey=' + self.config.sesskey)
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === 'success') {
                                alert(res.message);
                            }
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
                    success: function(resp) {
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
                $('#attendance-progress').css('width', currentPct + '%').text(currentPct + '%');

                if (currentPct >= self.config.minAttendancePct) {
                    $('#attendance-progress').removeClass('bg-info').addClass('bg-success');
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
            }, 10000);
        }

        showPollPopup(poll) {
            $('#poll-question-text').text(poll.question);
            let optionsHtml = '';
            (poll.options || []).forEach((opt, idx) => {
                optionsHtml += `
                    <div class="form-check p-2 rounded border mb-2 bg-light">
                        <input class="form-check-input ms-2" type="radio" name="poll_option" id="poll_opt_${idx}" value="${opt}" required>
                        <label class="form-check-label fw-bold cursor-pointer" for="poll_opt_${idx}">
                            ${opt}
                        </label>
                    </div>
                `;
            });
            $('#poll-options-container').html(optionsHtml);
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

        leaveClassroom() {
            if (this.timerInterval) clearInterval(this.timerInterval);
            if (this.pollInterval) clearInterval(this.pollInterval);
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
