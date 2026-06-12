<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Create Lesson Plan';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$editId = (int)($_GET['id'] ?? 0);
$plan = null;

$stmt = $db->prepare("SELECT id, employee_id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

$currentTerm = getCurrentTerm();
$termId = $currentTerm['id'] ?? 0;
$sessionId = $currentTerm['session_id'] ?? 0;

if ($editId) {
    $pageTitle = 'Edit Lesson Plan';
    $stmt = $db->prepare("SELECT * FROM lesson_plans WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$editId, $userId]);
    $plan = $stmt->fetch();
    if (!$plan) { redirect('/teacher/lesson-plans/index.php'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lp'])) {
    $topic = sanitizeInput($_POST['topic'] ?? '');
    $subTopic = sanitizeInput($_POST['sub_topic'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $term_id = (int)($_POST['term_id'] ?? $termId);
    $session_id = (int)($_POST['session_id'] ?? $sessionId);
    $weekNo = (int)($_POST['week_no'] ?? 0);
    $datePlanned = sanitizeInput($_POST['date_planned'] ?? '');
    $duration = sanitizeInput($_POST['duration'] ?? '');
    $learningObjectives = sanitizeInput($_POST['learning_objectives'] ?? '');
    $previousKnowledge = sanitizeInput($_POST['previous_knowledge'] ?? '');
    $instructionalMaterials = sanitizeInput($_POST['instructional_materials'] ?? '');
    $teachingMethods = sanitizeInput($_POST['teaching_methods'] ?? '');
    $introduction = sanitizeInput($_POST['introduction'] ?? '');
    $presentationSteps = sanitizeInput($_POST['presentation_steps'] ?? '');
    $classroomActivities = sanitizeInput($_POST['classroom_activities'] ?? '');
    $studentActivities = sanitizeInput($_POST['student_activities'] ?? '');
    $assessment = sanitizeInput($_POST['assessment'] ?? '');
    $assignment = sanitizeInput($_POST['assignment'] ?? '');
    $referenceMaterials = sanitizeInput($_POST['reference_materials'] ?? '');
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'draft');
    $staffId = sanitizeInput($_POST['staff_id'] ?? ($teacher['employee_id'] ?? ''));

    $filled = 0;
    $total = 18;
    if ($topic) $filled++; if ($subTopic) $filled++; if ($learningObjectives) $filled++;
    if ($previousKnowledge) $filled++; if ($instructionalMaterials) $filled++; if ($teachingMethods) $filled++;
    if ($introduction) $filled++; if ($presentationSteps) $filled++; if ($classroomActivities) $filled++;
    if ($studentActivities) $filled++; if ($assessment) $filled++; if ($assignment) $filled++;
    if ($referenceMaterials) $filled++; if ($remarks) $filled++; if ($duration) $filled++;
    if ($datePlanned) $filled++; if ($weekNo) $filled++; if ($subjectId) $filled++;
    $completion = round(($filled / $total) * 100);

    if ($topic && $subjectId && $classId) {
        if ($editId) {
            $stmt = $db->prepare("UPDATE lesson_plans SET staff_id=?, subject_id=?, class_id=?, term_id=?, academic_session_id=?, week_no=?, date_planned=?, duration=?, topic=?, sub_topic=?, learning_objectives=?, previous_knowledge=?, instructional_materials=?, teaching_methods=?, introduction=?, presentation_steps=?, classroom_activities=?, student_activities=?, assessment=?, assignment=?, reference_materials=?, remarks=?, status=?, completion_percentage=? WHERE id=? AND teacher_id=?");
            $stmt->execute([
                $staffId, $subjectId, $classId, $term_id, $session_id, $weekNo,
                $datePlanned, $duration, $topic, $subTopic, $learningObjectives,
                $previousKnowledge, $instructionalMaterials, $teachingMethods,
                $introduction, $presentationSteps, $classroomActivities,
                $studentActivities, $assessment, $assignment, $referenceMaterials,
                $remarks, $status, $completion, $editId, $userId
            ]);
            $msg = 'Lesson plan updated.';
        } else {
            $stmt = $db->prepare("INSERT INTO lesson_plans (teacher_id, staff_id, subject_id, class_id, term_id, academic_session_id, week_no, date_planned, duration, topic, sub_topic, learning_objectives, previous_knowledge, instructional_materials, teaching_methods, introduction, presentation_steps, classroom_activities, student_activities, assessment, assignment, reference_materials, remarks, status, completion_percentage) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $userId, $staffId, $subjectId, $classId, $term_id, $session_id,
                $weekNo, $datePlanned, $duration, $topic, $subTopic,
                $learningObjectives, $previousKnowledge, $instructionalMaterials,
                $teachingMethods, $introduction, $presentationSteps,
                $classroomActivities, $studentActivities, $assessment, $assignment,
                $referenceMaterials, $remarks, $status, $completion
            ]);
            $editId = (int)$db->lastInsertId();
            $msg = 'Lesson plan saved.';
        }
        $stmt = $db->prepare("SELECT * FROM lesson_plans WHERE id = ?");
        $stmt->execute([$editId]);
        $plan = $stmt->fetch();
        $msgType = 'success';
    } else {
        $msg = 'Topic, Subject, and Class are required.';
        $msgType = 'danger';
    }
}

if (!$plan) {
    $subjects = $db->prepare("SELECT s.id, s.name, s.class_id, c.name as class_name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = ?");
    $subjects->execute([$userId]);
} else {
    $subjects = $db->prepare("SELECT s.id, s.name, s.class_id, c.name as class_name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
    $subjects->execute([$plan['subject_id']]);
}
$mySubjects = $subjects->fetchAll();

$terms = $db->query("SELECT t.*, s.session_name FROM terms t JOIN academic_sessions s ON t.session_id = s.id ORDER BY s.start_date DESC, t.id ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-<?= $editId ? 'edit' : 'plus' ?> me-2"></i><?= $editId ? 'Edit' : 'Create' ?> Lesson Plan</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($plan && $plan['id']): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center">
    <span><i class="fas fa-info-circle me-1"></i> Completion: <strong><?= $plan['completion_percentage'] ?>%</strong></span>
    <a href="view.php?id=<?= $plan['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>View</a>
</div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Basic Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Teacher Name</label>
                            <input type="text" class="form-control" value="<?= sanitizeInput($_SESSION['user_name']) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Staff ID</label>
                            <input type="text" name="staff_id" class="form-control" value="<?= sanitizeInput($plan['staff_id'] ?? ($teacher['employee_id'] ?? '')) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subject *</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($mySubjects as $s): ?>
                                <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>" <?= ($plan['subject_id'] ?? 0) === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name'] . ' - ' . $s['class_name'] . ' ' . $s['section']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Class *</label>
                            <select name="class_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php
                                $classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
                                foreach ($classes as $c):
                                ?>
                                <option value="<?= $c['id'] ?>" <?= ($plan['class_id'] ?? 0) === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Term</label>
                            <select name="term_id" class="form-select">
                                <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($plan['term_id'] ?? $termId) === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name'] . ' - ' . $t['session_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Week</label>
                            <input type="number" name="week_no" class="form-control" min="1" max="15" value="<?= $plan['week_no'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="date_planned" class="form-control" value="<?= $plan['date_planned'] ?? date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-control" placeholder="e.g. 40 minutes" value="<?= sanitizeInput($plan['duration'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-heading me-2"></i>Topic</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Topic *</label>
                        <input type="text" name="topic" class="form-control" required value="<?= sanitizeInput($plan['topic'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Sub-topic</label>
                        <input type="text" name="sub_topic" class="form-control" value="<?= sanitizeInput($plan['sub_topic'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-bullseye me-2"></i>Objectives & Prior Knowledge</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Learning Objectives</label>
                        <textarea name="learning_objectives" class="form-control" rows="4" placeholder="By the end of this lesson, students should be able to:..."><?= sanitizeInput($plan['learning_objectives'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label">Previous Knowledge</label>
                        <textarea name="previous_knowledge" class="form-control" rows="3" placeholder="What students already know about this topic"><?= sanitizeInput($plan['previous_knowledge'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-tools me-2"></i>Materials & Methods</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Instructional Materials</label>
                        <textarea name="instructional_materials" class="form-control" rows="3" placeholder="Textbooks, charts, multimedia, lab equipment..."><?= sanitizeInput($plan['instructional_materials'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label">Teaching Methods</label>
                        <textarea name="teaching_methods" class="form-control" rows="3" placeholder="Lecture, discussion, demonstration, group work..."><?= sanitizeInput($plan['teaching_methods'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-chalkboard-teacher me-2"></i>Lesson Development</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Introduction / Set Induction</label>
                        <textarea name="introduction" class="form-control" rows="4" placeholder="How will you introduce the lesson and capture students' attention?"><?= sanitizeInput($plan['introduction'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Presentation / Lesson Development Steps</label>
                        <textarea name="presentation_steps" class="form-control" rows="6" placeholder="Step-by-step content delivery..."><?= sanitizeInput($plan['presentation_steps'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teacher's Activities</label>
                        <textarea name="classroom_activities" class="form-control" rows="3" placeholder="What the teacher will do during the lesson"><?= sanitizeInput($plan['classroom_activities'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label">Students' Activities</label>
                        <textarea name="student_activities" class="form-control" rows="3" placeholder="What students will do during the lesson"><?= sanitizeInput($plan['student_activities'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-clipboard-check me-2"></i>Assessment & Follow-up</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Assessment / Evaluation</label>
                        <textarea name="assessment" class="form-control" rows="4" placeholder="How will you assess student understanding? (questions, quiz, observation...)"><?= sanitizeInput($plan['assessment'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assignment / Homework</label>
                        <textarea name="assignment" class="form-control" rows="3"><?= sanitizeInput($plan['assignment'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Materials</label>
                        <textarea name="reference_materials" class="form-control" rows="3" placeholder="Textbooks, websites, resources used"><?= sanitizeInput($plan['reference_materials'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="After-teaching reflection notes"><?= sanitizeInput($plan['remarks'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-save me-2"></i>Save Options</div>
                <div class="card-body">
                    <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                    <button type="submit" name="save_lp" value="draft" class="btn btn-outline-secondary w-100 mb-2">
                        <i class="fas fa-save me-1"></i>Save as Draft
                    </button>
                    <button type="submit" name="save_lp" value="submitted" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-paper-plane me-1"></i>Save & Submit
                    </button>
                    <?php if ($editId): ?>
                    <a href="view.php?id=<?= $editId ?>" class="btn btn-outline-info w-100">
                        <i class="fas fa-eye me-1"></i>Preview
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-tips me-2"></i>Writing Tips</div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">Use measurable verbs in objectives (list, explain, demonstrate, analyze)</li>
                        <li class="mb-2">Align activities with learning objectives</li>
                        <li class="mb-2">Include differentiated activities for diverse learners</li>
                        <li class="mb-2">Plan for formative assessment throughout the lesson</li>
                        <li>Allow time for student questions and reflection</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.querySelector('[name="subject_id"]')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var classId = opt.getAttribute('data-class');
    if (classId) {
        document.querySelector('[name="class_id"]').value = classId;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
