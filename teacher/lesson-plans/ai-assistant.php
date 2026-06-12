<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'AI Lesson Plan Assistant';
$db = getDB();
$userId = $_SESSION['user_id'];
$generated = '';
$generatedPlan = [];
$msg = '';

$stmt = $db->prepare("SELECT id, employee_id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

$subjects = $db->prepare("SELECT s.id, s.name, c.name as class_name, c.section, c.id as class_id FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = ?");
$subjects->execute([$userId]);
$mySubjects = $subjects->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $topic = sanitizeInput($_POST['topic'] ?? '');
    $objectives = sanitizeInput($_POST['objectives'] ?? '');

    if ($topic && $subjectId) {
        $subjName = '';
        $className = '';
        foreach ($mySubjects as $s) {
            if ($s['id'] === $subjectId) { $subjName = $s['name']; $className = $s['class_name'] . ' ' . $s['section']; break; }
        }

        $target = $objectives ?: "understand and apply the concepts of $topic";
        $objectivesList = "- Define and explain key concepts related to $topic\n- Analyze practical applications of $topic\n- Demonstrate understanding through relevant exercises\n- Evaluate and create solutions using principles of $topic";
        if ($objectives) {
            $objectivesList = $objectives;
        }

        $generatedPlan = [
            'topic' => $topic,
            'sub_topic' => 'Introduction to ' . $topic,
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'duration' => '40 minutes',
            'learning_objectives' => $objectivesList,
            'previous_knowledge' => "Students have basic foundational knowledge of related concepts from previous lessons. They are familiar with basic terminology and have completed introductory exercises in this subject area.",
            'instructional_materials' => "- Whiteboard/Interactive Board and markers\n- Textbook: " . $subjName . " for " . $className . "\n- Handout with key terms and concepts\n- Multimedia projector for visual aids\n- Worksheets for practice exercises",
            'teaching_methods' => "- Interactive lecture with questioning\n- Demonstration and guided practice\n- Group discussion and collaborative learning\n- Think-Pair-Share activities\n- Formative assessment through questioning",
            'introduction' => "Begin the lesson by asking students what they already know about $topic (5 minutes). Show a relevant image or short video clip to capture interest. Pose an intriguing question related to the topic to stimulate curiosity and set the stage for learning. Share the learning objectives with the class.",
            'presentation_steps' => "**Step 1: Introduction to Key Concepts (10 minutes)**\nDefine and explain the main concepts of $topic. Use real-world examples to illustrate each concept. Write key terms on the board and have students copy them.\n\n**Step 2: Guided Practice (10 minutes)**\nWork through examples together as a class. Demonstrate the step-by-step process. Ask probing questions to check understanding at each stage.\n\n**Step 3: Application (10 minutes)**\nStudents work in pairs or small groups to solve problems related to $topic. Circulate around the room to provide support and identify common misconceptions.\n\n**Step 4: Review and Consolidation (5 minutes)**\nReview key points covered. Address common errors. Connect the lesson to real-life applications of $topic.",
            'classroom_activities' => "- Teacher presents key concepts using visual aids and real-world examples\n- Teacher demonstrates problem-solving techniques\n- Teacher facilitates group discussions and guides student thinking\n- Teacher provides immediate feedback during practice activities\n- Teacher assesses understanding through questioning and observation",
            'student_activities' => "- Students listen, take notes, and ask questions during presentation\n- Students participate in class discussions and answer questions\n- Students work collaboratively on practice exercises\n- Students present their findings to the class\n- Students complete a short reflection on what they learned",
            'assessment' => "- Oral questioning throughout the lesson to check understanding\n- Observation of student participation during group work\n- Exit ticket: Students write one thing they learned and one question they still have\n- Review of completed practice exercises\n- Short quiz at the beginning of the next lesson",
            'assignment' => "1. Complete the practice questions on $topic (pages X-X from the textbook)\n2. Write a short paragraph explaining how $topic applies to everyday life\n3. Prepare one question about $topic for class discussion next lesson",
            'reference_materials' => "- " . $subjName . " Textbook for " . $className . " (Pages relevant to $topic)\n- Curriculum guide for " . $subjName . "\n- Online educational resources and videos\n- Teacher's lesson notes and scheme of work",
            'remarks' => ''
        ];
        $generated = true;
    } else {
        $msg = 'Please select a subject and enter a topic.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_generated'])) {
    $topic = sanitizeInput($_POST['topic'] ?? '');
    $subTopic = sanitizeInput($_POST['sub_topic'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $duration = sanitizeInput($_POST['duration'] ?? '40 minutes');
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

    $currentTerm = getCurrentTerm();
    $termId = $currentTerm['id'] ?? 0;
    $sessionId = $currentTerm['session_id'] ?? 0;

    $stmt = $db->prepare("INSERT INTO lesson_plans (teacher_id, staff_id, subject_id, class_id, term_id, academic_session_id, topic, sub_topic, duration, learning_objectives, previous_knowledge, instructional_materials, teaching_methods, introduction, presentation_steps, classroom_activities, student_activities, assessment, assignment, reference_materials, remarks, status, completion_percentage) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?)");
    $stmt->execute([
        $userId, $teacher['employee_id'] ?? '', $subjectId, $classId, $termId, $sessionId,
        $topic, $subTopic, $duration, $learningObjectives, $previousKnowledge,
        $instructionalMaterials, $teachingMethods, $introduction, $presentationSteps,
        $classroomActivities, $studentActivities, $assessment, $assignment,
        $referenceMaterials, $remarks, 100
    ]);
    redirect('/teacher/lesson-plans/index.php');
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-robot me-2"></i>AI Lesson Plan Assistant</h4>
        <p class="text-muted small mb-0">Generate professional lesson plans instantly</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($msg): ?><div class="alert alert-danger"><?= $msg ?></div><?php endif; ?>

<?php if (!$generated): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-magic me-2"></i>Generate Lesson Plan</div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Subject *</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($mySubjects as $s): ?>
                        <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>"><?= sanitizeInput($s['name'] . ' - ' . $s['class_name'] . ' ' . $s['section']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select">
                        <option value="">Auto-detect</option>
                        <?php
                        $classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
                        foreach ($classes as $c):
                        ?>
                        <option value="<?= $c['id'] ?>"><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Topic *</label>
                    <input type="text" name="topic" class="form-control" required placeholder="e.g. Fractions, Photosynthesis, The Solar System">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" name="generate" class="btn btn-gold w-100"><i class="fas fa-wand-magic-sparkles me-1"></i>Generate</button>
                </div>
                <div class="col-12">
                    <label class="form-label">Learning Objectives (optional)</label>
                    <textarea name="objectives" class="form-control" rows="3" placeholder="By the end of this lesson, students should be able to... (leave blank for auto-generation)"></textarea>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($generated): ?>
<form method="POST">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-robot me-2"></i>Generated Lesson Plan</span>
            <span class="badge bg-success">AI Generated</span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3"><i class="fas fa-edit me-1"></i>Review and edit the generated plan below before saving.</p>
            <input type="hidden" name="subject_id" value="<?= $generatedPlan['subject_id'] ?>">
            <input type="hidden" name="class_id" value="<?= $generatedPlan['class_id'] ?>">

            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label">Topic *</label>
                    <input type="text" name="topic" class="form-control" value="<?= sanitizeInput($generatedPlan['topic']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Duration</label>
                    <input type="text" name="duration" class="form-control" value="<?= sanitizeInput($generatedPlan['duration']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Sub-topic</label>
                    <input type="text" name="sub_topic" class="form-control" value="<?= sanitizeInput($generatedPlan['sub_topic']) ?>">
                </div>
            </div>

            <?php $editable = [
                'Learning Objectives' => 'learning_objectives',
                'Previous Knowledge' => 'previous_knowledge',
                'Instructional Materials' => 'instructional_materials',
                'Teaching Methods' => 'teaching_methods',
                'Introduction / Set Induction' => 'introduction',
                'Presentation Steps' => 'presentation_steps',
                'Teacher Activities' => 'classroom_activities',
                'Student Activities' => 'student_activities',
                'Assessment / Evaluation' => 'assessment',
                'Assignment / Homework' => 'assignment',
                'Reference Materials' => 'reference_materials',
                'Remarks' => 'remarks',
            ]; ?>
            <?php foreach ($editable as $label => $col): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold"><?= $label ?></label>
                <textarea name="<?= $col ?>" class="form-control" rows="4"><?= sanitizeInput($generatedPlan[$col] ?? '') ?></textarea>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a href="ai-assistant.php" class="btn btn-outline-secondary"><i class="fas fa-undo me-1"></i>Regenerate</a>
            <button type="submit" name="save_generated" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Lesson Plan</button>
        </div>
    </div>
</form>
<?php endif; ?>

<script>
document.querySelector('[name="subject_id"]')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var classId = opt.getAttribute('data-class');
    var classSelect = document.querySelector('[name="class_id"]');
    if (classId && classSelect) {
        for (var i = 0; i < classSelect.options.length; i++) {
            if (classSelect.options[i].value === classId) {
                classSelect.value = classId;
                break;
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
