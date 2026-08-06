<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

use App\Services\AiAssistantService;
use App\Services\ExportService;

$pageTitle = 'AI Teaching Assistant';
$db = getDB();
$userId = (int)$_SESSION['user_id'];

$msg = '';
$msgType = 'danger';

/* Section key => human label used for rendering and export. */
$sectionLabels = [
    'title'                 => 'Topic',
    'sub_topic'             => 'Sub-topic',
    'learning_objectives'   => 'Learning Objectives',
    'objectives'            => 'Lesson Objectives',
    'introduction'          => 'Introduction / Set Induction',
    'body'                  => 'Lesson Content',
    'summary'               => 'Lesson Summary',
    'assignment'            => 'Assignment / Homework',
    'reference_materials'   => 'Reference Materials',
    'previous_knowledge'    => 'Previous Knowledge',
    'instructional_materials' => 'Instructional Materials',
    'teaching_methods'      => 'Teaching Methods',
    'presentation_steps'    => 'Presentation Steps',
    'classroom_activities'  => 'Teacher Activities',
    'student_activities'    => 'Student Activities',
    'assessment'            => 'Assessment / Evaluation',
    'remarks'               => 'Remarks',
    'quiz_questions'        => 'Quiz Questions',
    'exam_questions'        => 'Examination Questions',
    'marking_guide'         => 'Marking Guide',
    'differentiation'       => 'Differentiation',
    'blooms_objectives'     => "Bloom's Taxonomy Objectives",
    'curriculum_alignment'  => 'Curriculum Alignment (NERDC/WAEC/NECO/BECE)',
];

/* ------------------------------------------------------------------ *
 *  Teacher scoping data
 * ------------------------------------------------------------------ */
$subjectsStmt = $db->prepare("SELECT s.id, s.name, c.name AS class_name, c.section, c.id AS class_id
                              FROM subjects s JOIN classes c ON s.class_id = c.id
                              WHERE s.teacher_id = ? ORDER BY c.name, c.section, s.name");
$subjectsStmt->execute([$userId]);
$mySubjects = $subjectsStmt->fetchAll();

$teacherStmt = $db->prepare("SELECT id, employee_id FROM teachers WHERE user_id = ?");
$teacherStmt->execute([$userId]);
$teacher = $teacherStmt->fetch();

$sessions = $db->query("SELECT id, session_name, is_current FROM academic_sessions ORDER BY id DESC")->fetchAll();
$terms = $db->query("SELECT t.id, t.term_name, t.session_id, t.is_current, s.session_name
                     FROM terms t JOIN academic_sessions s ON t.session_id = s.id
                     ORDER BY s.id DESC, t.id")->fetchAll();
$currentTerm = getCurrentTerm();

$assistant = new AiAssistantService();
$generatedSections = [];
$activeAction = 'lesson_note';

/* ------------------------------------------------------------------ *
 *  Helpers
 * ------------------------------------------------------------------ */
function sectionsToHtml(array $sections, array $labels): string
{
    $html = '';
    foreach ($sections as $key => $value) {
        $value = trim((string)$value);
        if ($value === '') continue;
        $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
        $html .= '<h3>' . htmlspecialchars($label, ENT_QUOTES) . '</h3>';

        if ($key === 'quiz_questions' || $key === 'exam_questions') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $html .= '<ol>';
                foreach ($decoded as $item) {
                    $q = htmlspecialchars((string)($item['question'] ?? ''), ENT_QUOTES);
                    $html .= '<li><strong>' . $q . '</strong>';
                    if (!empty($item['options']) && is_array($item['options'])) {
                        $html .= '<ul class="text-muted">';
                        foreach ($item['options'] as $opt) $html .= '<li>' . htmlspecialchars((string)$opt, ENT_QUOTES) . '</li>';
                        $html .= '</ul>';
                    }
                    $html .= '</li>';
                }
                $html .= '</ol>';
                continue;
            }
        }

        $paragraphs = preg_split('/\R{2,}/', $value);
        foreach ($paragraphs as $para) {
            $lines = preg_split('/\R/', trim($para));
            $isList = false;
            foreach ($lines as $line) {
                if (preg_match('/^[-*]\s+/', $line)) { $isList = true; break; }
            }
            if ($isList) {
                $html .= '<ul>';
                foreach ($lines as $line) {
                    $clean = preg_replace('/^[-*]\s+/', '', $line);
                    if (trim($clean) === '') continue;
                    $html .= '<li>' . htmlspecialchars($clean, ENT_QUOTES) . '</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= '<p>' . nl2br(htmlspecialchars($para, ENT_QUOTES)) . '</p>';
            }
        }
    }
    return $html;
}

/* ------------------------------------------------------------------ *
 *  POST actions
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = sanitizeInput($_POST['action'] ?? '');

    /* ---------- Generate (all sections) ---------- */
    if ($postAction === 'generate') {
        $actionKey = sanitizeInput($_POST['action_key'] ?? 'lesson_note');
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $classId = (int)($_POST['class_id'] ?? 0);
        $termId = (int)($_POST['term_id'] ?? 0);
        $sessionId = (int)($_POST['academic_session_id'] ?? 0);
        $topic = sanitizeInput($_POST['topic'] ?? '');
        $week = (int)($_POST['week'] ?? 1);
        $extra = sanitizeInput($_POST['extra'] ?? '');

        if (!isset(AiAssistantService::ACTIONS[$actionKey])) { $actionKey = 'lesson_note'; }
        if ($topic === '' || !$subjectId) {
            $msg = 'Please select a subject and enter a topic to generate content.';
        } else {
            $ctx = [
                'subject_id'   => $subjectId,
                'subject_name' => '',
                'class_id'     => $classId,
                'class_name'   => '',
                'level'        => '',
                'topic'        => $topic,
                'week'         => $week,
                'term_id'      => $termId,
                'term_name'    => '',
                'session_id'   => $sessionId,
                'session_name' => '',
                'extra'        => $extra,
            ];
            foreach ($mySubjects as $s) {
                if ((int)$s['id'] === $subjectId) {
                    $ctx['subject_name'] = $s['name'];
                    $ctx['class_id'] = (int)$s['class_id'];
                    $ctx['class_name'] = className($s['class_name'], $s['section']);
                    $ctx['level'] = $s['class_name'];
                }
            }
            if ($classId === 0) $classId = $ctx['class_id'];
            foreach ($terms as $t) {
                if ((int)$t['id'] === $termId) { $ctx['term_name'] = $t['term_name']; $ctx['session_name'] = $t['session_name']; }
            }

            try {
                $generatedSections = $assistant->generate($actionKey, $ctx);
                $activeAction = $actionKey;
                $msg = 'Content generated successfully. Review and edit before saving.';
                $msgType = 'success';
                // Keep the generation context for regeneration + saving.
                $_SESSION['ai_assistant'] = [
                    'action'    => $actionKey,
                    'subject_id'=> $subjectId,
                    'class_id'  => $classId,
                    'term_id'   => $termId,
                    'session_id'=> $sessionId,
                    'topic'     => $topic,
                    'week'      => $week,
                    'extra'     => $extra,
                ];
            } catch (\Throwable $e) {
                $msg = 'Generation failed: ' . $e->getMessage();
            }
        }
    }

    /* ---------- Regenerate a single section ---------- */
    if ($postAction === 'regenerate') {
        $section = sanitizeInput($_POST['section'] ?? '');
        $sections = isset($_POST['sec']) && is_array($_POST['sec']) ? array_map('trim', $_POST['sec']) : [];
        $stored = $_SESSION['ai_assistant'] ?? [];
        if ($section !== '' && !empty($stored) && isset(AiAssistantService::ACTIONS[$stored['action'] ?? ''])) {
            $ctx = [
                'subject_id' => (int)($stored['subject_id'] ?? 0),
                'subject_name' => '',
                'class_id' => (int)($stored['class_id'] ?? 0),
                'class_name' => '',
                'level' => '',
                'topic' => $stored['topic'] ?? '',
                'week' => (int)($stored['week'] ?? 1),
                'term_name' => '',
                'session_name' => '',
                'extra' => $stored['extra'] ?? '',
            ];
            foreach ($mySubjects as $s) {
                if ((int)$s['id'] === (int)$ctx['subject_id']) {
                    $ctx['subject_name'] = $s['name'];
                    $ctx['class_name'] = className($s['class_name'], $s['section']);
                    $ctx['level'] = $s['class_name'];
                }
            }
            foreach ($terms as $t) {
                if ((int)$t['id'] === (int)($stored['term_id'] ?? 0)) {
                    $ctx['term_name'] = $t['term_name'];
                    $ctx['session_name'] = $t['session_name'];
                }
            }
            try {
                $regenerated = $assistant->generate($stored['action'], $ctx, $section);
                $sections[$section] = $regenerated[$section] ?? '';
                $generatedSections = $sections;
                $activeAction = $stored['action'];
                $msg = 'Section "' . ($sectionLabels[$section] ?? $section) . '" regenerated.';
                $msgType = 'success';
            } catch (\Throwable $e) {
                $msg = 'Regeneration failed: ' . $e->getMessage();
                $generatedSections = $sections;
                $activeAction = $stored['action'];
            }
        } else {
            $msg = 'Regeneration context is missing. Please generate again.';
        }
    }

    /* ---------- Save as lesson note ---------- */
    if ($postAction === 'save_note') {
        $sections = isset($_POST['sec']) && is_array($_POST['sec']) ? array_map('trim', $_POST['sec']) : [];
        $stored = $_SESSION['ai_assistant'] ?? [];
        $topic = sanitizeInput($_POST['topic'] ?? ($stored['topic'] ?? ''));
        $subjectId = (int)($_POST['subject_id'] ?? ($stored['subject_id'] ?? 0));

        /* Validate the subject belongs to this teacher; derive class from it. */
        $classId = 0;
        foreach ($mySubjects as $s) {
            if ((int)$s['id'] === $subjectId) { $classId = (int)$s['class_id']; break; }
        }
        $termId = (int)($_POST['term_id'] ?? ($stored['term_id'] ?? 0));
        $sessionId = (int)($_POST['academic_session_id'] ?? ($stored['session_id'] ?? 0));
        $week = (int)($_POST['week'] ?? ($stored['week'] ?? 1));
        $status = in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? $_POST['status'] : 'draft';

        if ($topic !== '' && $subjectId && $classId) {
            $content = sectionsToHtml($sections, $sectionLabels);
            $summary = $sections['summary'] ?? '';
            if ($summary === '') {
                $summary = mb_substr(strip_tags($sections['body'] ?? $topic), 0, 200);
            }
            $stmt = $db->prepare("INSERT INTO lesson_notes (teacher_id, subject_id, class_id, academic_session_id, term_id, topic, content, week, summary, status, is_ai_generated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$userId, $subjectId, $classId, $sessionId, $termId, $topic, $content, $week, $summary, $status]);
            logActivity($userId, 'create_lesson_note_ai', 'lesson_notes', (int)$db->lastInsertId());
            redirect('/teacher/lesson-notes.php?status=' . $status);
        } else {
            $msg = 'Missing subject, class or topic when saving the lesson note.';
        }
    }

    /* ---------- Save as lesson plan ---------- */
    if ($postAction === 'save_plan') {
        $sections = isset($_POST['sec']) && is_array($_POST['sec']) ? array_map('trim', $_POST['sec']) : [];
        $stored = $_SESSION['ai_assistant'] ?? [];
        $topic = sanitizeInput($_POST['topic'] ?? ($stored['topic'] ?? ''));
        $subjectId = (int)($_POST['subject_id'] ?? ($stored['subject_id'] ?? 0));

        $classId = 0;
        foreach ($mySubjects as $s) {
            if ((int)$s['id'] === $subjectId) { $classId = (int)$s['class_id']; break; }
        }
        $termId = (int)($_POST['term_id'] ?? ($stored['term_id'] ?? 0));
        $sessionId = (int)($_POST['academic_session_id'] ?? ($stored['session_id'] ?? 0));

        if ($topic !== '' && $subjectId && $classId) {
            $stmt = $db->prepare("INSERT INTO lesson_plans (teacher_id, staff_id, subject_id, class_id, term_id, academic_session_id, topic, sub_topic, learning_objectives, previous_knowledge, instructional_materials, teaching_methods, introduction, presentation_steps, classroom_activities, student_activities, assessment, assignment, reference_materials, remarks, status, completion_percentage) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 100)");
            $stmt->execute([
                $userId,
                $teacher['employee_id'] ?? '',
                $subjectId,
                $classId,
                $termId,
                $sessionId,
                $topic,
                $sections['sub_topic'] ?? '',
                $sections['learning_objectives'] ?? $sections['objectives'] ?? '',
                $sections['previous_knowledge'] ?? '',
                $sections['instructional_materials'] ?? '',
                $sections['teaching_methods'] ?? '',
                $sections['introduction'] ?? '',
                $sections['presentation_steps'] ?? '',
                $sections['classroom_activities'] ?? '',
                $sections['student_activities'] ?? '',
                $sections['assessment'] ?? '',
                $sections['assignment'] ?? '',
                $sections['reference_materials'] ?? '',
                $sections['remarks'] ?? '',
            ]);
            logActivity($userId, 'create_lesson_plan_ai', 'lesson_plans', (int)$db->lastInsertId());
            redirect('/teacher/lesson-plans/index.php');
        } else {
            $msg = 'Missing subject, class or topic when saving the lesson plan.';
        }
    }

    /* ---------- Export PDF / DOCX ---------- */
    if ($postAction === 'export_pdf' || $postAction === 'export_docx') {
        $sections = isset($_POST['sec']) && is_array($_POST['sec']) ? array_map('trim', $_POST['sec']) : [];
        $topic = sanitizeInput($_POST['topic'] ?? ($_SESSION['ai_assistant']['topic'] ?? 'Lesson Content'));
        $exportSections = [];
        foreach ($sections as $key => $value) {
            if (trim($value) === '') continue;
            $exportSections[$sectionLabels[$key] ?? ucwords(str_replace('_', ' ', $key))] = $value;
        }
        if (empty($exportSections)) {
            $msg = 'Nothing to export yet. Generate content first.';
        } elseif ($postAction === 'export_pdf') {
            try {
                ExportService::toPdf($topic, $exportSections, SCHOOL_NAME);
            } catch (\Throwable $e) {
                $msg = 'PDF export failed: ' . $e->getMessage();
            }
        } else {
            try {
                ExportService::toDocx($topic, $exportSections, SCHOOL_NAME);
            } catch (\Throwable $e) {
                $msg = 'DOCX export failed: ' . $e->getMessage();
            }
        }
    }
}

/* Restore the last generated content into view (e.g. after a validation error). */
if (empty($generatedSections) && isset($_POST['sec']) && is_array($_POST['sec'])) {
    $generatedSections = array_map('trim', $_POST['sec']);
    $activeAction = $_SESSION['ai_assistant']['action'] ?? 'lesson_note';
}

$activeProvider = $assistant->provider();
$providerLabel = $activeProvider->label();
$isTemplate = $assistant->isTemplate();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-robot me-2"></i>AI Teaching Assistant</h4>
        <p class="text-muted small mb-0">Generate lesson notes, lesson plans, objectives, questions, marking guides and more.</p>
    </div>
    <div>
        <span class="badge bg-<?= $isTemplate ? 'secondary' : 'success' ?>">
            <i class="fas fa-<?= $isTemplate ? 'database' : 'brain' ?> me-1"></i><?= sanitizeInput($providerLabel) ?>
        </span>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-magic me-2"></i>Generate</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <div class="mb-3">
                        <label class="form-label">Content Type *</label>
                        <select name="action_key" class="form-select">
                            <?php foreach (AiAssistantService::ACTIONS as $key => $def): ?>
                            <option value="<?= $key ?>" <?= $activeAction === $key ? 'selected' : '' ?>><?= sanitizeInput($def['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <select name="subject_id" id="aiSubject" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($mySubjects as $s): ?>
                            <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>" data-level="<?= sanitizeInput($s['class_name']) ?>"
                                <?= (int)($_SESSION['ai_assistant']['subject_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= sanitizeInput($s['name'] . ' - ' . className($s['class_name'], $s['section'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <input type="text" class="form-control" id="aiClass" readonly placeholder="Auto-set from subject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Topic *</label>
                        <input type="text" name="topic" class="form-control" required placeholder="e.g. Photosynthesis, Fractions, The Solar System" value="<?= sanitizeInput($_SESSION['ai_assistant']['topic'] ?? '') ?>">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Week</label>
                            <input type="number" name="week" class="form-control" min="1" max="15" value="<?= (int)($_SESSION['ai_assistant']['week'] ?? 1) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Term</label>
                            <select name="term_id" class="form-select">
                                <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (int)($_SESSION['ai_assistant']['term_id'] ?? ($currentTerm['id'] ?? 0)) === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= sanitizeInput($t['term_name']) ?><?= $t['is_current'] ? ' (current)' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Academic Session</label>
                        <select name="academic_session_id" class="form-select">
                            <?php foreach ($sessions as $sess): ?>
                            <option value="<?= $sess['id'] ?>" <?= (int)($_SESSION['ai_assistant']['session_id'] ?? ($currentTerm['session_id'] ?? 0)) === (int)$sess['id'] ? 'selected' : '' ?>>
                                <?= sanitizeInput($sess['session_name']) ?><?= $sess['is_current'] ? ' (current)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Context (optional)</label>
                        <textarea name="extra" class="form-control" rows="3" placeholder="e.g. Focus on practical examples; include local Nigerian context; students are struggling with X."><?= sanitizeInput($_SESSION['ai_assistant']['extra'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-gold w-100"><i class="fas fa-wand-magic-sparkles me-1"></i>Generate</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if (empty($generatedSections)): ?>
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-robot fa-3x mb-3 d-block"></i>
                <p class="mb-1">Choose a content type, pick a subject and topic, then click <strong>Generate</strong>.</p>
                <small>Generated sections can be edited, regenerated individually, saved as a lesson note or lesson plan, and exported to PDF/DOCX.</small>
            </div>
        </div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" id="editorAction" value="">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-alt me-2"></i><?= sanitizeInput(AiAssistantService::ACTIONS[$activeAction]['label'] ?? 'Generated Content') ?></span>
                    <span class="badge bg-success">AI Generated</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Topic *</label>
                            <input type="text" name="topic" class="form-control" value="<?= sanitizeInput($_SESSION['ai_assistant']['topic'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Week</label>
                            <input type="number" name="week" class="form-control" value="<?= (int)($_SESSION['ai_assistant']['week'] ?? 1) ?>">
                        </div>
                    </div>
                    <input type="hidden" name="subject_id" value="<?= (int)($_SESSION['ai_assistant']['subject_id'] ?? 0) ?>">
                    <input type="hidden" name="class_id" value="<?= (int)($_SESSION['ai_assistant']['class_id'] ?? 0) ?>">
                    <input type="hidden" name="term_id" value="<?= (int)($_SESSION['ai_assistant']['term_id'] ?? 0) ?>">
                    <input type="hidden" name="academic_session_id" value="<?= (int)($_SESSION['ai_assistant']['session_id'] ?? 0) ?>">

                    <p class="text-muted small mb-3"><i class="fas fa-edit me-1"></i>Edit any section before saving. Use <em>Regenerate</em> on a section to replace just that part.</p>

                    <?php foreach ($generatedSections as $key => $value): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold mb-0"><?= sanitizeInput($sectionLabels[$key] ?? ucwords(str_replace('_', ' ', $key))) ?></label>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-regen" data-section="<?= $key ?>"><i class="fas fa-sync-alt me-1"></i>Regenerate</button>
                        </div>
                        <textarea name="sec[<?= $key ?>]" class="form-control" rows="5"><?= sanitizeInput($value) ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                        <div class="col-md-9 d-flex flex-wrap gap-2 justify-content-end">
                            <button type="button" class="btn btn-outline-primary btn-export" data-fmt="pdf"><i class="fas fa-file-pdf me-1"></i>PDF</button>
                            <button type="button" class="btn btn-outline-primary btn-export" data-fmt="docx"><i class="fas fa-file-word me-1"></i>DOCX</button>
                            <button type="button" class="btn btn-info btn-save-plan"><i class="fas fa-book-open me-1"></i>Save as Lesson Plan</button>
                            <button type="button" class="btn btn-success btn-save-note"><i class="fas fa-sticky-note me-1"></i>Save as Lesson Note</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('aiSubject')?.addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    var out = document.getElementById('aiClass');
    out.value = opt.getAttribute('data-level') || '';
});

/* Editor form action dispatch */
var editorForm = null;
document.addEventListener('click', function (e) {
    var regen = e.target.closest('.btn-regen');
    var saveNote = e.target.closest('.btn-save-note');
    var savePlan = e.target.closest('.btn-save-plan');
    var exp = e.target.closest('.btn-export');
    var input = document.getElementById('editorAction');
    if (regen) {
        e.preventDefault();
        var section = regen.getAttribute('data-section');
        if (confirm('Regenerate the "' + section + '" section? Other edited sections will be kept.')) {
            input.value = 'regenerate';
            var s = document.createElement('input');
            s.type = 'hidden';
            s.name = 'section';
            s.value = section;
            regen.closest('form').appendChild(s);
            regen.closest('form').submit();
        }
    } else if (saveNote) {
        e.preventDefault();
        input.value = 'save_note';
        saveNote.closest('form').submit();
    } else if (savePlan) {
        e.preventDefault();
        if (confirm('Save this content as a new lesson plan?')) {
            input.value = 'save_plan';
            savePlan.closest('form').submit();
        }
    } else if (exp) {
        e.preventDefault();
        input.value = 'export_' + exp.getAttribute('data-fmt');
        exp.closest('form').submit();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
