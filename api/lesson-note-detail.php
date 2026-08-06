<?php
// Returns a single lesson note (rich content) for the view modal.
// Scoped by role: teachers see their own notes, students see only published
// notes for their own class/current term, admins see everything.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); echo json_encode(['error' => 'Invalid note.']); exit; }

$db = getDB();
$role = $_SESSION['role'] ?? '';
$userId = (int)$_SESSION['user_id'];

$sql = "SELECT ln.id, ln.topic, ln.content, ln.file_path, ln.status,
               sub.name AS subject_name, c.name AS class_name, c.section,
               sess.session_name, t.term_name, ln.week
        FROM lesson_notes ln
        JOIN subjects sub ON ln.subject_id = sub.id
        JOIN classes c ON ln.class_id = c.id
        LEFT JOIN academic_sessions sess ON ln.academic_session_id = sess.id
        LEFT JOIN terms t ON ln.term_id = t.id
        WHERE ln.id = ?";
$params = [$id];

if ($role === 'teacher') {
    $sql .= " AND ln.teacher_id = ?";
    $params[] = $userId;
} elseif ($role === 'student') {
    $studentStmt = $db->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
    $studentStmt->execute([$userId]);
    $student = $studentStmt->fetch();
    if (!$student) { http_response_code(403); echo json_encode(['error' => 'No student profile.']); exit; }
    $term = getCurrentTerm();
    $sql .= " AND ln.status = 'published' AND ln.class_id = ? AND ln.term_id = ?";
    $params[] = (int)$student['class_id'];
    $params[] = (int)($term['id'] ?? 0);
} elseif ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$note = $stmt->fetch();
if (!$note) { http_response_code(404); echo json_encode(['error' => 'Note not found.']); exit; }

echo json_encode([
    'id'       => (int)$note['id'],
    'topic'    => $note['topic'],
    'content'  => $note['content'],
    'file_path'=> $note['file_path'],
    'subject'  => $note['subject_name'],
    'class'    => className($note['class_name'], $note['section']),
    'session'  => $note['session_name'],
    'term'     => $note['term_name'],
    'week'     => (int)$note['week'],
    'status'   => $note['status'],
]);
