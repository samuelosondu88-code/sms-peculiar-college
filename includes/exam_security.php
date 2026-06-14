<?php
if (defined('EXAM_SECURITY_LOADED')) return;
define('EXAM_SECURITY_LOADED', true);

function getExamSecuritySettings($db, $examId) {
    $stmt = $db->prepare("SELECT * FROM exam_security_settings WHERE exam_id = ?");
    $stmt->execute([$examId]);
    $settings = $stmt->fetch();
    if (!$settings) {
        $stmt = $db->prepare("INSERT INTO exam_security_settings (exam_id) VALUES (?)");
        $stmt->execute([$examId]);
        return [
            'id' => (int)$db->lastInsertId(),
            'exam_id' => $examId,
            'require_fullscreen' => 1,
            'require_camera' => 0,
            'max_tab_switches' => 3,
            'max_fullscreen_exits' => 3,
            'max_camera_errors' => 5,
            'max_face_violations' => 5,
            'inactivity_timeout_minutes' => 5,
            'auto_submit_on_violation' => 1,
            'restrict_device' => 0,
            'allowed_ips' => null,
            'shuffle_questions' => 1,
            'shuffle_options' => 1
        ];
    }
    return $settings;
}

function saveExamSecuritySettings($db, $examId, $data) {
    $stmt = $db->prepare("INSERT INTO exam_security_settings (exam_id, require_fullscreen, require_camera, max_tab_switches, max_fullscreen_exits, max_camera_errors, max_face_violations, inactivity_timeout_minutes, auto_submit_on_violation, restrict_device, allowed_ips, shuffle_questions, shuffle_options) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE require_fullscreen=VALUES(require_fullscreen), require_camera=VALUES(require_camera), max_tab_switches=VALUES(max_tab_switches), max_fullscreen_exits=VALUES(max_fullscreen_exits), max_camera_errors=VALUES(max_camera_errors), max_face_violations=VALUES(max_face_violations), inactivity_timeout_minutes=VALUES(inactivity_timeout_minutes), auto_submit_on_violation=VALUES(auto_submit_on_violation), restrict_device=VALUES(restrict_device), allowed_ips=VALUES(allowed_ips), shuffle_questions=VALUES(shuffle_questions), shuffle_options=VALUES(shuffle_options)");
    return $stmt->execute([
        $examId,
        (int)($data['require_fullscreen'] ?? 1),
        (int)($data['require_camera'] ?? 0),
        (int)($data['max_tab_switches'] ?? 3),
        (int)($data['max_fullscreen_exits'] ?? 3),
        (int)($data['max_camera_errors'] ?? 5),
        (int)($data['max_face_violations'] ?? 5),
        (int)($data['inactivity_timeout_minutes'] ?? 5),
        (int)($data['auto_submit_on_violation'] ?? 1),
        (int)($data['restrict_device'] ?? 0),
        sanitizeInput($data['allowed_ips'] ?? ''),
        (int)($data['shuffle_questions'] ?? 1),
        (int)($data['shuffle_options'] ?? 1)
    ]);
}

function logExamActivity($db, $attemptId, $eventType, $data = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("INSERT INTO exam_activity_logs (attempt_id, event_type, event_data, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $eventData = $data ? json_encode($data) : null;
    $stmt->execute([$attemptId, $eventType, $eventData, $ip, $ua]);
    return (int)$db->lastInsertId();
}

function logViolation($db, $attemptId, $violationType, $data = null) {
    logExamActivity($db, $attemptId, $violationType, $data);
    $db->prepare("UPDATE exam_attempts SET violation_count = COALESCE(violation_count,0) + 1 WHERE id = ?")->execute([$attemptId]);
}

function updateLastActivity($db, $attemptId) {
    $db->prepare("UPDATE exam_attempts SET last_activity_at = NOW() WHERE id = ?")->execute([$attemptId]);
}

function checkInactivity($db, $attemptId, $timeoutMinutes = 5) {
    $stmt = $db->prepare("SELECT last_activity_at FROM exam_attempts WHERE id = ?");
    $stmt->execute([$attemptId]);
    $row = $stmt->fetch();
    if (!$row || !$row['last_activity_at']) return false;
    $inactive = time() - strtotime($row['last_activity_at']);
    return $inactive > ($timeoutMinutes * 60);
}

function autoSubmitExam($db, $attemptId, $reason = 'auto_submit') {
    $stmt = $db->prepare("SELECT exam_id, student_id FROM exam_attempts WHERE id = ? AND status = 'in_progress'");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    if (!$attempt) return false;

    $db->prepare("UPDATE exam_attempts SET status = 'submitted', submitted_at = NOW(), auto_submitted = 1, submit_reason = ? WHERE id = ?")->execute([$reason, $attemptId]);

    $autoTotal = $db->prepare("SELECT COALESCE(SUM(auto_score),0) FROM exam_responses WHERE attempt_id = ?");
    $autoTotal->execute([$attemptId]);
    $db->prepare("UPDATE exam_attempts SET auto_score = ?, total_score = auto_score + manual_score WHERE id = ?")->execute([$autoTotal->fetchColumn(), $attemptId]);

    logExamActivity($db, $attemptId, 'auto_submit', ['reason' => $reason]);
    computeIntegrityScore($db, $attemptId);
    return true;
}

function computeIntegrityScore($db, $attemptId) {
    $tabSwitches = $db->prepare("SELECT COUNT(*) FROM exam_activity_logs WHERE attempt_id = ? AND event_type = 'tab_switch'");
    $tabSwitches->execute([$attemptId]); $tabCount = (int)$tabSwitches->fetchColumn();

    $fsExits = $db->prepare("SELECT COUNT(*) FROM exam_activity_logs WHERE attempt_id = ? AND event_type = 'fullscreen_exit'");
    $fsExits->execute([$attemptId]); $fsCount = (int)$fsExits->fetchColumn();

    $camErrors = $db->prepare("SELECT COUNT(*) FROM exam_activity_logs WHERE attempt_id = ? AND event_type IN ('camera_violation','face_absent','multiple_faces','face_obstructed')");
    $camErrors->execute([$attemptId]); $camCount = (int)$camErrors->fetchColumn();

    $identityVerified = $db->prepare("SELECT COUNT(*) FROM exam_activity_logs WHERE attempt_id = ? AND event_type = 'identity_captured'");
    $identityVerified->execute([$attemptId]); $identity = (int)$identityVerified->fetchColumn() > 0 ? 1 : 0;

    $cameraCompliance = max(0, 100 - ($camCount * 10));
    $tabPenalty = min(40, $tabCount * 10);
    $fsPenalty = min(30, $fsCount * 8);
    $suspiciousPenalty = 0;
    foreach (['copy_attempt','print_attempt','right_click','keyboard_shortcut','devtools_open'] as $evt) {
        $cnt = $db->prepare("SELECT COUNT(*) FROM exam_activity_logs WHERE attempt_id = ? AND event_type = ?");
        $cnt->execute([$attemptId, $evt]); $suspiciousPenalty += (int)$cnt->fetchColumn() * 5;
    }
    $suspiciousPenalty = min(30, $suspiciousPenalty);

    $overall = max(0, 100 - $tabPenalty - $fsPenalty - $suspiciousPenalty);
    if (!$identity) $overall *= 0.8;
    $overall = round($overall, 2);

    $riskLevel = 'low';
    if ($overall < 30) $riskLevel = 'critical';
    elseif ($overall < 50) $riskLevel = 'high';
    elseif ($overall < 70) $riskLevel = 'medium';

    $stmt = $db->prepare("INSERT INTO exam_integrity_scores (attempt_id, camera_compliance, tab_switch_count, fullscreen_exit_count, camera_error_count, face_violation_count, identity_verified, overall_score, risk_level) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE camera_compliance=VALUES(camera_compliance), tab_switch_count=VALUES(tab_switch_count), fullscreen_exit_count=VALUES(fullscreen_exit_count), camera_error_count=VALUES(camera_error_count), face_violation_count=VALUES(face_violation_count), identity_verified=VALUES(identity_verified), overall_score=VALUES(overall_score), risk_level=VALUES(risk_level)");
    return $stmt->execute([$attemptId, $cameraCompliance, $tabCount, $fsCount, $camCount, $camCount, $identity, $overall, $riskLevel]);
}

function getIntegrityScore($db, $attemptId) {
    $stmt = $db->prepare("SELECT * FROM exam_integrity_scores WHERE attempt_id = ?");
    $stmt->execute([$attemptId]);
    return $stmt->fetch();
}

function getExamActivityLog($db, $attemptId, $limit = 100) {
    $stmt = $db->prepare("SELECT * FROM exam_activity_logs WHERE attempt_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $attemptId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function registerDeviceFingerprint($db, $attemptId, $fp) {
    $existing = $db->prepare("SELECT id FROM exam_device_fingerprints WHERE attempt_id = ?");
    $existing->execute([$attemptId]);
    if ($existing->fetch()) return true;

    $stmt = $db->prepare("INSERT INTO exam_device_fingerprints (attempt_id, fingerprint_hash, ip_address, screen_resolution, timezone_offset, platform, language, hardware_concurrency, device_memory) VALUES (?,?,?,?,?,?,?,?,?)");
    return $stmt->execute([
        $attemptId,
        $fp['hash'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $fp['resolution'] ?? '',
        (int)($fp['timezone'] ?? 0),
        $fp['platform'] ?? '',
        $fp['language'] ?? '',
        (int)($fp['concurrency'] ?? 0),
        (float)($fp['memory'] ?? 0)
    ]);
}

function checkDeviceRestriction($db, $examId, $fingerprintHash) {
    $stmt = $db->prepare("SELECT ea.id FROM exam_attempts ea JOIN exam_device_fingerprints edf ON ea.id = edf.attempt_id WHERE ea.exam_id = ? AND edf.fingerprint_hash = ? AND ea.status = 'in_progress'");
    $stmt->execute([$examId, $fingerprintHash]);
    return $stmt->fetch() ? true : false;
}

function getActiveExamsForMonitoring($db, $teacherId = null, $limit = 50) {
    $sql = "SELECT ea.id as attempt_id, ea.started_at, ea.last_activity_at, ea.violation_count, ea.status,
                   te.id as exam_id, te.title, te.duration_minutes, te.total_marks,
                   u.first_name, u.last_name, u.email,
                   s.admission_no,
                   (SELECT COUNT(*) FROM exam_activity_logs eal WHERE eal.attempt_id = ea.id AND eal.event_type IN ('tab_switch','fullscreen_exit','camera_violation','multiple_faces')) as total_violations,
                   (SELECT overall_score FROM exam_integrity_scores eis WHERE eis.attempt_id = ea.id) as integrity_score,
                   (SELECT risk_level FROM exam_integrity_scores eis WHERE eis.attempt_id = ea.id) as risk_level
            FROM exam_attempts ea
            JOIN teacher_exams te ON ea.exam_id = te.id
            JOIN users u ON ea.student_id = u.id
            LEFT JOIN students s ON u.id = s.user_id";
    $params = [];
    $conditions = ["ea.status = 'in_progress'"];
    if ($teacherId) { $conditions[] = "te.teacher_id = ?"; $params[] = $teacherId; }
    $sql .= " WHERE " . implode(' AND ', $conditions);
    $sql .= " ORDER BY ea.last_activity_at DESC LIMIT " . (int)$limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getExamViolationSummary($db, $examId) {
    $sql = "SELECT eal.event_type, COUNT(*) as cnt FROM exam_activity_logs eal JOIN exam_attempts ea ON eal.attempt_id = ea.id WHERE ea.exam_id = ? GROUP BY eal.event_type ORDER BY cnt DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$examId]);
    return $stmt->fetchAll();
}

function getExamIntegrityReport($db, $examId) {
    $sql = "SELECT u.first_name, u.last_name, s.admission_no, eis.*, ea.total_score, ea.status
            FROM exam_attempts ea
            JOIN users u ON ea.student_id = u.id
            LEFT JOIN students s ON u.id = s.user_id
            LEFT JOIN exam_integrity_scores eis ON ea.id = eis.attempt_id
            WHERE ea.exam_id = ?
            ORDER BY eis.overall_score ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$examId]);
    return $stmt->fetchAll();
}
