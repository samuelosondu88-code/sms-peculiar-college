<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
$classId = (int)($_GET['class_id'] ?? 0);
if (!$classId) { echo json_encode([]); exit; }
$db = getDB();
$stmt = $db->prepare("SELECT s.id, u.first_name, u.last_name, s.admission_no FROM students s JOIN users u ON s.user_id = u.id WHERE s.class_id = ? AND s.status = 'active' ORDER BY u.first_name");
$stmt->execute([$classId]);
echo json_encode($stmt->fetchAll());
