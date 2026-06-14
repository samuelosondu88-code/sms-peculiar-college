/* Exam Security & Anti-Malpractice System */
var ExamSecurity = (function () {
    var config = {
        autoSaveInterval: 5000,
        heartbeatInterval: 15000,
        inactivityWarningAfter: 240,
        maxTabSwitches: 3,
        maxFullscreenExits: 3,
        maxCameraErrors: 5,
        maxFaceViolations: 5,
        cameraCheckInterval: 5000,
        requireFullscreen: true,
        requireCamera: false,
        attemptId: 0,
        examId: 0,
        saveAnswerUrl: '',
        logEventUrl: '',
        submitUrl: '',
        warningDuration: 5000,
    };

    var state = {
        fullscreen: false,
        tabSwitches: 0,
        fullscreenExits: 0,
        cameraErrors: 0,
        faceViolations: 0,
        isActive: true,
        cameraStream: null,
        faceDetector: null,
        videoElement: null,
        canvasElement: null,
        warningActive: false,
        lastActivity: Date.now(),
        questionAnswered: {},
        currentQuestion: 0,
        totalQuestions: 0,
        autoSubmitted: false,
        connectionLost: false,
    };

    var timers = {
        autoSave: null,
        heartbeat: null,
        inactivity: null,
        camera: null,
        timer: null,
    };

    var callbacks = {
        onViolation: null,
        onAutoSubmit: null,
        onWarning: null,
        onTimerUpdate: null,
        onAnswerSave: null,
    };

    function _logEvent(eventType, eventData) {
        if (!config.logEventUrl) return;
        var data = { attempt_id: config.attemptId, event_type: eventType, event_data: JSON.stringify(eventData || {}) };
        var params = new URLSearchParams();
        for (var k in data) params.append(k, data[k]);
        fetch(config.logEventUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() });
    }

    function _saveAnswer(questionId, value) {
        if (!config.saveAnswerUrl) return;
        state.lastActivity = Date.now();
        var params = 'action=save_answer&qid=' + questionId + '&answer=' + encodeURIComponent(value);
        return fetch(config.saveAnswerUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
    }

    function _autoSubmit(reason) {
        if (state.autoSubmitted) return;
        state.autoSubmitted = true;
        _logEvent('auto_submit', { reason: reason });
        if (callbacks.onAutoSubmit) callbacks.onAutoSubmit(reason);
        var params = 'action=auto_submit&reason=' + encodeURIComponent(reason);
        fetch(config.submitUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params }).then(function () {
            window.onbeforeunload = null;
            if (reason !== 'timer_expired') {
                document.title = 'Exam Auto-Submitted';
                document.body.innerHTML = '<div class="container mt-5"><div class="alert alert-warning text-center"><h4>Exam Auto-Submitted</h4><p>Reason: ' + reason.replace(/_/g, ' ') + '</p><a href="results.php?exam_id=' + config.examId + '" class="btn btn-primary mt-3">View Results</a></div></div>';
            }
        });
    }

    function _showWarning(message, type) {
        if (state.warningActive) return;
        state.warningActive = true;
        if (callbacks.onWarning) callbacks.onWarning(message, type);
        var existing = document.getElementById('exam-security-warning');
        if (existing) existing.remove();
        var div = document.createElement('div');
        div.id = 'exam-security-warning';
        div.className = 'exam-warning-overlay';
        div.innerHTML = '<div class="exam-warning-box exam-warning-' + type + '"><div class="exam-warning-icon"><i class="fas ' + (type === 'danger' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') + '"></i></div><div class="exam-warning-msg">' + message + '</div><div class="exam-warning-close" onclick="this.parentElement.parentElement.remove(); ExamSecurity && (ExamSecurity._state && (ExamSecurity._state.warningActive = false));">&times;</div></div>';
        document.body.appendChild(div);
        setTimeout(function () {
            var el = document.getElementById('exam-security-warning');
            if (el) { el.remove(); }
            state.warningActive = false;
        }, config.warningDuration);
    }

    function _checkFullscreen() {
        var fs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || document.mozFullScreenElement;
        if (!fs && state.fullscreen && state.isActive) {
            state.fullscreen = false;
            state.fullscreenExits++;
            _logEvent('fullscreen_exit', { count: state.fullscreenExits });
            _showWarning('You exited full-screen mode (' + state.fullscreenExits + '/' + config.maxFullscreenExits + '). Please return to full-screen immediately.', 'danger');
            if (callbacks.onViolation) callbacks.onViolation('fullscreen_exit', state.fullscreenExits);
            if (state.fullscreenExits >= config.maxFullscreenExits) {
                _autoSubmit('excessive_fullscreen_exits');
            } else {
                _requestFullscreen();
            }
        } else if (fs && !state.fullscreen) {
            state.fullscreen = true;
            _logEvent('fullscreen_enter', {});
        }
    }

    function _requestFullscreen() {
        var el = document.documentElement;
        if (el.requestFullscreen) el.requestFullscreen();
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
        else if (el.msRequestFullscreen) el.msRequestFullscreen();
        else if (el.mozRequestFullScreen) el.mozRequestFullScreen();
    }

    function _onVisibilityChange() {
        if (document.hidden && state.isActive) {
            state.tabSwitches++;
            state.isActive = false;
            _logEvent('tab_switch', { count: state.tabSwitches });
            _showWarning('Tab switch detected (' + state.tabSwitches + '/' + config.maxTabSwitches + '). Stay on the exam page!', 'warning');
            if (callbacks.onViolation) callbacks.onViolation('tab_switch', state.tabSwitches);
            if (state.tabSwitches >= config.maxTabSwitches) {
                _autoSubmit('excessive_tab_switches');
            }
        } else if (!document.hidden && !state.isActive) {
            state.isActive = true;
            _logEvent('tab_return', {});
            state.lastActivity = Date.now();
        }
    }

    function _onBlur() {
        if (state.isActive) {
            _logEvent('window_blur', {});
        }
    }

    function _onFocus() {
        state.isActive = true;
        state.lastActivity = Date.now();
    }

    function _blockKey(e) {
        var blocked = false;
        if (e.ctrlKey || e.metaKey) {
            switch (e.key.toLowerCase()) {
                case 'c': case 'v': case 'x': case 'p': case 's': case 'u': case 'a':
                    blocked = true;
                    _logEvent('keyboard_shortcut', { key: e.key, ctrl: e.ctrlKey });
                    break;
            }
        }
        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C'))) {
            blocked = true;
            _logEvent('devtools_attempt', { key: e.key });
        }
        if (e.key === 'PrintScreen' || e.key === 'Print') {
            blocked = true;
            _logEvent('screen_capture', {});
            _showWarning('Screenshots are not allowed during the exam.', 'warning');
        }
        if (blocked) { e.preventDefault(); e.stopPropagation(); return false; }
    }

    function _blockContextMenu(e) {
        _logEvent('right_click', {});
        _showWarning('Right-click is disabled during the exam.', 'warning');
        e.preventDefault();
        return false;
    }

    function _blockSelectStart(e) {
        e.preventDefault();
        return false;
    }

    function _blockCopy(e) {
        _logEvent('copy_attempt', {});
        e.preventDefault();
        return false;
    }

    function _checkInactivity() {
        var elapsed = (Date.now() - state.lastActivity) / 1000;
        if (elapsed > config.inactivityWarningAfter && state.isActive) {
            var mins = Math.floor(elapsed / 60);
            _showWarning('No activity detected for ' + mins + ' minute(s). Move your mouse or type to continue.', 'warning');
            _logEvent('inactivity_warning', { seconds: Math.floor(elapsed) });
        }
    }

    function _initCamera() {
        if (!config.requireCamera) return;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            _logEvent('camera_not_supported', {});
            return;
        }
        state.videoElement = document.createElement('video');
        state.videoElement.setAttribute('playsinline', '');
        state.videoElement.style.display = 'none';
        document.body.appendChild(state.videoElement);

        state.canvasElement = document.createElement('canvas');
        state.canvasElement.style.display = 'none';
        document.body.appendChild(state.canvasElement);

        navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240, facingMode: 'user' } })
            .then(function (stream) {
                state.cameraStream = stream;
                state.videoElement.srcObject = stream;
                state.videoElement.play();
                _logEvent('camera_started', {});
                _loadFaceDetector();
            })
            .catch(function (err) {
                _logEvent('camera_error', { error: err.message });
                state.cameraErrors++;
                _showWarning('Camera access denied (' + state.cameraErrors + '/' + config.maxCameraErrors + '). Grant camera permission for exam integrity.', 'warning');
                if (state.cameraErrors >= config.maxCameraErrors) {
                    _autoSubmit('camera_access_denied');
                }
            });
    }

    function _loadFaceDetector() {
        if (window.FaceDetector) {
            try {
                state.faceDetector = new window.FaceDetector({ maxDetectedFaces: 10, fastMode: true });
                timers.camera = setInterval(_checkCamera, config.cameraCheckInterval);
                return;
            } catch (e) { /* fall through */ }
        }
        /* Face Detection API not available — run basic presence checks */
        timers.camera = setInterval(_basicCameraCheck, config.cameraCheckInterval);
    }

    function _checkCamera() {
        if (!state.videoElement || !state.faceDetector || state.videoElement.readyState < 2) return;
        try {
            state.faceDetector.detect(state.videoElement).then(function (faces) {
                var faceCount = faces.length;
                if (faceCount === 0) {
                    state.faceViolations++;
                    _logEvent('face_absent', { count: state.faceViolations });
                    if (state.faceViolations % 2 === 0) {
                        _showWarning('No face detected (' + state.faceViolations + '/' + config.maxFaceViolations + '). Ensure you are visible to the camera.', 'warning');
                    }
                    if (state.faceViolations >= config.maxFaceViolations) {
                        _autoSubmit('face_not_detected');
                    }
                } else if (faceCount > 1) {
                    state.faceViolations++;
                    _logEvent('multiple_faces', { count: faceCount, violations: state.faceViolations });
                    _captureEvidence('multiple_faces', faceCount);
                    _showWarning('Multiple faces detected (' + faceCount + ' people). Only the candidate should be visible.', 'danger');
                    if (callbacks.onViolation) callbacks.onViolation('multiple_faces', faceCount);
                    if (state.faceViolations >= config.maxFaceViolations) {
                        _autoSubmit('multiple_faces_detected');
                    }
                } else {
                    /* one face — normal */
                }
            }).catch(function () {});
        } catch (e) { /* ignore */ }
    }

    function _basicCameraCheck() {
        if (!state.videoElement || state.videoElement.readyState < 2) return;
        var canvas = state.canvasElement;
        var video = state.videoElement;
        canvas.width = video.videoWidth || 160;
        canvas.height = video.videoHeight || 120;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var pixels = imageData.data;
        var totalBrightness = 0;
        for (var i = 0; i < pixels.length; i += 4) {
            totalBrightness += (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
        }
        var avgBrightness = totalBrightness / (pixels.length / 4);
        if (avgBrightness < 5) {
            state.faceViolations++;
            _logEvent('face_obstructed', { brightness: avgBrightness });
            if (state.faceViolations >= config.maxFaceViolations) {
                _autoSubmit('camera_obstructed');
            }
        }
    }

    function _captureEvidence(violationType, faceCount) {
        if (!state.videoElement || !state.canvasElement) return;
        var canvas = state.canvasElement;
        var video = state.videoElement;
        canvas.width = video.videoWidth || 320;
        canvas.height = video.videoHeight || 240;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        var dataUrl = canvas.toDataURL('image/jpeg', 0.5);
        var params = 'attempt_id=' + config.attemptId + '&violation_type=' + encodeURIComponent(violationType) + '&face_count=' + faceCount + '&image_data=' + encodeURIComponent(dataUrl);
        fetch(config.submitUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=capture_evidence&' + params });
    }

    function _initConnectionMonitor() {
        window.addEventListener('online', function () {
            state.connectionLost = false;
            _logEvent('connection_restored', {});
            _showWarning('Connection restored. Answers are being synchronized.', 'info');
        });
        window.addEventListener('offline', function () {
            state.connectionLost = true;
            _logEvent('connection_lost', {});
            _showWarning('Internet connection lost. Your answers are being saved locally and will sync when reconnected.', 'warning');
        });
    }

    function _autoSaveAll() {
        var inputs = document.querySelectorAll('[data-save-qid]');
        var promises = [];
        inputs.forEach(function (el) {
            var qid = parseInt(el.dataset.saveQid);
            if (!qid) return;
            var value = '';
            if (el.type === 'radio') {
                if (el.checked) value = el.value;
            } else if (el.type === 'checkbox') {
                value = el.checked ? el.value : '';
            } else {
                value = el.value;
            }
            if (value.trim() !== '') {
                promises.push(_saveAnswer(qid, value));
            }
        });
        return Promise.all(promises).catch(function () {});
    }

    function _heartbeat() {
        var params = 'action=heartbeat&attempt_id=' + config.attemptId;
        fetch(config.logEventUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
    }

    function _startTimer(durationMinutes, startTime) {
        var endTime = new Date(startTime.getTime() + durationMinutes * 60000);
        timers.timer = setInterval(function () {
            var now = new Date();
            var remaining = Math.max(0, endTime - now);
            var totalSecs = Math.floor(remaining / 1000);
            var mins = Math.floor(totalSecs / 60);
            var secs = totalSecs % 60;
            var display = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
            if (callbacks.onTimerUpdate) callbacks.onTimerUpdate(display, totalSecs);
            var el = document.getElementById('timer');
            if (el) {
                el.textContent = display;
                if (totalSecs <= 300) el.style.color = '#dc2626';
                else if (totalSecs <= 600) el.style.color = '#d97706';
            }
            if (totalSecs <= 0) {
                clearInterval(timers.timer);
                _autoSubmit('timer_expired');
                if (el) el.textContent = '00:00';
                if (callbacks.onTimerUpdate) callbacks.onTimerUpdate('00:00', 0);
            }
        }, 1000);
    }

    /* Public API */
    return {
        _state: state,
        init: function (opts) {
            for (var k in opts) config[k] = opts[k];
            state.lastActivity = Date.now();

            if (config.requireFullscreen) {
                _requestFullscreen();
                state.fullscreen = true;
                document.addEventListener('fullscreenchange', _checkFullscreen);
                document.addEventListener('webkitfullscreenchange', _checkFullscreen);
                document.addEventListener('msfullscreenchange', _checkFullscreen);
                document.addEventListener('mozfullscreenchange', _checkFullscreen);
            }

            document.addEventListener('visibilitychange', _onVisibilityChange);
            window.addEventListener('blur', _onBlur);
            window.addEventListener('focus', _onFocus);
            document.addEventListener('keydown', _blockKey, true);
            document.addEventListener('contextmenu', _blockContextMenu);
            document.addEventListener('selectstart', _blockSelectStart);
            document.addEventListener('copy', _blockCopy);
            document.addEventListener('cut', _blockCopy);
            document.addEventListener('paste', _blockCopy);

            _initConnectionMonitor();
            _initCamera();
            timers.autoSave = setInterval(_autoSaveAll, config.autoSaveInterval);
            timers.heartbeat = setInterval(_heartbeat, config.heartbeatInterval);
            timers.inactivity = setInterval(_checkInactivity, 30000);

            if (opts.startTime) {
                _startTimer(config.durationMinutes || 60, new Date(opts.startTime));
            }

            _logEvent('exam_start', {});
            _heartbeat();
        },

        saveAnswer: _saveAnswer,
        logEvent: _logEvent,

        goToQuestion: function (idx) {
            state.currentQuestion = idx;
            state.lastActivity = Date.now();
        },

        shutdown: function () {
            for (var t in timers) { if (timers[t]) clearInterval(timers[t]); }
            if (state.cameraStream) {
                state.cameraStream.getTracks().forEach(function (t) { t.stop(); });
            }
            document.removeEventListener('fullscreenchange', _checkFullscreen);
            document.removeEventListener('visibilitychange', _onVisibilityChange);
            document.removeEventListener('keydown', _blockKey, true);
            document.removeEventListener('contextmenu', _blockContextMenu);
            document.removeEventListener('selectstart', _blockSelectStart);
            document.removeEventListener('copy', _blockCopy);
            window.removeEventListener('blur', _onBlur);
            window.removeEventListener('focus', _onFocus);
        },

        on: function (event, fn) {
            if (event === 'violation') callbacks.onViolation = fn;
            if (event === 'autosubmit') callbacks.onAutoSubmit = fn;
            if (event === 'warning') callbacks.onWarning = fn;
            if (event === 'timerupdate') callbacks.onTimerUpdate = fn;
            if (event === 'answersave') callbacks.onAnswerSave = fn;
        },

        getState: function () {
            return {
                tabSwitches: state.tabSwitches,
                fullscreenExits: state.fullscreenExits,
                cameraErrors: state.cameraErrors,
                faceViolations: state.faceViolations,
                isActive: state.isActive,
                connectionLost: state.connectionLost,
                autoSubmitted: state.autoSubmitted,
            };
        },

        requestFullscreen: _requestFullscreen,
    };
})();
