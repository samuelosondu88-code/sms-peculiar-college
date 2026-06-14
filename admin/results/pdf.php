<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';
require_once __DIR__ . '/../../lib/fpdf.php';

$pageTitle = 'Download Report Card (PDF)';
$db = getDB();

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
$terms = $db->query("SELECT id, term_name, session_id FROM terms ORDER BY session_id, id")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedTerm = (int)($_GET['term_id'] ?? 0);
$selectedClass = (int)($_GET['class_id'] ?? 0);
$selectedStudent = (int)($_GET['student_id'] ?? 0);
$action = $_GET['action'] ?? '';

$students = [];
if ($selectedClass) {
    $stmt = $db->prepare("
        SELECT s.id, u.first_name, u.last_name, s.admission_no
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ? AND s.status = 'active'
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();
}

if ($action === 'download' && $selectedStudent && $selectedSession && $selectedTerm && $selectedClass) {
    while (ob_get_level()) ob_end_clean();

    $stmt = $db->prepare("
        SELECT s.id, s.admission_no, s.class_id, s.gender, u.first_name, u.last_name, u.avatar,
               c.name as class_name, c.section
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN classes c ON s.class_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$selectedStudent]);
    $student = $stmt->fetch();
    if (!$student) { redirect('/admin/results/pdf.php'); }

    $summary = getStudentTermSummary($db, $selectedStudent, $selectedSession, $selectedTerm);
    if ($summary['subject_count'] === 0) { redirect('/admin/results/pdf.php?error=no_scores'); }

    $position = getClassPosition($db, $selectedStudent, $selectedClass, $selectedSession, $selectedTerm);
    $attendance = getAttendanceStats($db, $selectedStudent, $selectedClass, $selectedSession, $selectedTerm);

    $stmt = $db->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
    $stmt->execute([$selectedSession]); $sessionName = $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT term_name FROM terms WHERE id = ?");
    $stmt->execute([$selectedTerm]); $termName = $stmt->fetchColumn();

    $p = $db->prepare("SELECT * FROM psychomotor_assessments WHERE student_id = ? AND session_id = ? AND term_id = ?");
    $p->execute([$selectedStudent, $selectedSession, $selectedTerm]); $psychomotor = $p->fetch() ?: [];
    $a = $db->prepare("SELECT * FROM affective_assessments WHERE student_id = ? AND session_id = ? AND term_id = ?");
    $a->execute([$selectedStudent, $selectedSession, $selectedTerm]); $affective = $a->fetch() ?: [];
    $c = $db->prepare("SELECT * FROM result_comments WHERE student_id = ? AND session_id = ? AND term_id = ?");
    $c->execute([$selectedStudent, $selectedSession, $selectedTerm]); $comments = $c->fetch() ?: [];
    $settings = getResultSettings($db, $selectedSession, $selectedTerm);

    class ReportCardPdf extends FPDF {
        private $navy = [11, 31, 58];
        private $gold = [212, 175, 55];
        private $dark = [51, 51, 51];
        private $muted = [120, 120, 120];
        private $lightGray = [245, 245, 245];
        private $borderGray = [210, 210, 210];
        private $white = [255, 255, 255];
        private $logoPath = '';
        private $passportPath = '';
        private $watermarkFile = '';

        function __construct($logo = '', $passport = '') {
            parent::__construct('P', 'mm', 'A4');
            $this->logoPath = $logo;
            $this->passportPath = $passport;
            $wm = __DIR__ . '/../../lib/font/watermark.png';
            if (file_exists($wm)) $this->watermarkFile = $wm;
            $this->SetMargins(12, 12, 12);
            $this->SetAutoPageBreak(true, 22);
        }

        function Header() {
            if ($this->pageNo() == 1) {
                $this->_headerFirstPage();
            } else {
                $this->_headerSubsequent();
            }
        }

        private function _headerFirstPage() {
            $pW = $this->GetPageWidth();
            $pH = $this->GetPageHeight();
            $mL = $this->lMargin;
            $mR = $pW - $this->rMargin;
            $uW = $mR - $mL;

            if ($this->watermarkFile) {
                $wmSize = 130;
                $this->Image($this->watermarkFile, ($pW - $wmSize) / 2, ($pH - $wmSize) / 2, $wmSize, $wmSize);
            }

            $logoSize = 26;
            $hasLogo = !empty($this->logoPath) && file_exists($this->logoPath);

            if ($hasLogo) {
                $this->Image($this->logoPath, ($pW - $logoSize) / 2, 10, $logoSize, $logoSize);
                $this->SetY(10 + $logoSize + 4);
            } else {
                $this->SetY(12);
            }

            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->Cell(0, 7, SCHOOL_NAME, 0, 1, 'C');

            $this->SetFont('Arial', '', 7.5);
            $this->SetTextColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $this->Cell(0, 4, SCHOOL_ADDRESS, 0, 1, 'C');

            $this->Cell(0, 4, 'Tel: ' . SCHOOL_PHONE . ' | Email: ' . SCHOOL_EMAIL, 0, 1, 'C');

            $this->SetFont('Arial', 'I', 7.5);
            $this->SetTextColor($this->gold[0], $this->gold[1], $this->gold[2]);
            $this->Cell(0, 4, '"' . SCHOOL_MOTTO . '"', 0, 1, 'C');

            $this->Ln(3);
            $y = $this->GetY();
            $this->SetDrawColor($this->gold[0], $this->gold[1], $this->gold[2]);
            $this->SetLineWidth(0.7);
            $this->Line($mL, $y, $mR, $y);
            $this->Ln(1);
            $this->SetDrawColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->SetLineWidth(0.3);
            $this->Line($mL, $this->GetY(), $mR, $this->GetY());
            $this->Ln(5);

            $this->SetFont('Arial', 'B', 15);
            $this->SetTextColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->Cell(0, 7, 'REPORT CARD', 0, 1, 'C');
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $this->Cell(0, 5, $GLOBALS['sessionName'] . ' | ' . $GLOBALS['termName'], 0, 1, 'C');
            $this->Ln(4);
        }

        private function _headerSubsequent() {
            $pW = $this->GetPageWidth();
            $pH = $this->GetPageHeight();
            if ($this->watermarkFile) {
                $wmSize = 130;
                $this->Image($this->watermarkFile, ($pW - $wmSize) / 2, ($pH - $wmSize) / 2, $wmSize, $wmSize);
            }
            $this->SetFont('Arial', 'I', 7);
            $this->SetTextColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $this->Cell(0, 5, SCHOOL_NAME . ' - Report Card (continued)', 0, 1, 'C');
            $this->SetDrawColor($this->gold[0], $this->gold[1], $this->gold[2]);
            $this->SetLineWidth(0.5);
            $this->Line($this->lMargin, $this->GetY(), $this->GetPageWidth() - $this->rMargin, $this->GetY());
            $this->Ln(4);
        }

        function Footer() {
            $this->SetY(-16);
            $pW = $this->GetPageWidth();
            $mL = $this->lMargin;
            $mR = $pW - $this->rMargin;
            $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
            $this->SetLineWidth(0.3);
            $this->Line($mL, $this->GetY(), $mR, $this->GetY());
            $this->Ln(2);
            $this->SetFont('Arial', '', 6.5);
            $this->SetTextColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $this->Cell(0, 4, SCHOOL_NAME . ' | Generated: ' . date('jS F, Y') . ' | Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        }

        function StudentInfoSection($student, $sessionName, $termName) {
            $mL = $this->lMargin;
            $pW = $this->GetPageWidth();
            $uW = $pW - $mL - $this->rMargin;
            $photoSize = 30;
            $rowH = 5.5;

            $boxX = $mL;
            $boxY = $this->GetY();
            $boxW = $uW;
            $boxH = $photoSize + 6;

            $this->SetFillColor($this->lightGray[0], $this->lightGray[1], $this->lightGray[2]);
            $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
            $this->Rect($boxX, $boxY, $boxW, $boxH);

            $col1X = $boxX + 5;
            $col2X = $boxX + ($uW - $photoSize - 5) / 2 + 5;
            $photoX = $boxX + $uW - $photoSize - 3;
            $labelW = 30;
            $valueW = $uW / 2 - $labelW - 10;

            $this->SetXY($col1X, $boxY + 1);
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->Cell(0, 4, 'STUDENT INFORMATION', 0, 1, 'L');
            $this->SetX($col1X);

            $fields = [
                ['Name:', $student['first_name'] . ' ' . $student['last_name']],
                ['Class:', $student['class_name'] . ' ' . ($student['section'] ?? '')],
                ['Admission No:', $student['admission_no']],
                ['Gender:', ucfirst($student['gender'] ?? 'N/A')],
            ];

            $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
            for ($i = 0; $i < 2; $i++) {
                $x = ($i === 0) ? $col1X : $col2X;
                for ($j = 0; $j < 2; $j++) {
                    $idx = $i * 2 + $j;
                    $y = $boxY + 6 + ($j * $rowH);
                    $this->SetXY($x, $y);
                    $this->SetFont('Arial', 'B', 7.5);
                    $this->Cell($labelW, $rowH, $fields[$idx][0], 0, 0, 'L');
                    $this->SetFont('Arial', '', 7.5);
                    $this->Cell($valueW, $rowH, $fields[$idx][1], 0, 1, 'L');
                }
            }

            if (!empty($this->passportPath) && file_exists($this->passportPath)) {
                $this->Image($this->passportPath, $photoX, $boxY + 3, $photoSize, $photoSize);
                $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
                $this->Rect($photoX, $boxY + 3, $photoSize, $photoSize);
            }

            $this->SetXY($mL, $boxY + $boxH + 4);
            $this->Ln(2);
        }

        function ScoresTable($results, $settings) {
            $pW = $this->GetPageWidth();
            $mL = $this->lMargin;
            $uW = $pW - $mL - $this->rMargin;

            $colW = [
                'sn' => 7, 'subject' => 40,
                'ca1' => 11, 'ca2' => 11, 'test' => 11, 'test2' => 11,
                'ca_total' => 12, 'exam' => 13, 'total' => 13,
                'grade' => 11, 'pos' => 9, 'remark' => 21
            ];
            $totalCol = array_sum($colW);
            $diff = $uW - $totalCol;
            $colW['remark'] += $diff;

            $headers = ['#', 'Subject', 'Asgn 1', 'Asgn 2', 'Test 1', 'Test 2', 'CA Total', 'Exam', 'Total', 'Grade', 'Pos', 'Remark'];
            $keys = ['sn', 'subject', 'ca1', 'ca2', 'test', 'test2', 'ca_total', 'exam', 'total', 'grade', 'pos', 'remark'];

            $this->SetFont('Arial', 'B', 6.5);
            $this->SetFillColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetDrawColor($this->navy[0], $this->navy[1], $this->navy[2]);
            foreach ($headers as $i => $h) {
                $this->Cell($colW[$keys[$i]], 7, $h, 1, 0, 'C', true);
            }
            $this->Ln();

            $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
            $fill = false;
            $sn = 1;

            foreach ($results as $r) {
                $grade = getGrade(
                    (float)$r['total_score'],
                    $settings['grade_a_min'], $settings['grade_b_min'],
                    $settings['grade_c_min'], $settings['grade_d_min'], $settings['grade_e_min']
                );
                $remark = getGradeRemark($grade);

                if ($fill) {
                    $this->SetFillColor(247, 247, 252);
                } else {
                    $this->SetFillColor(255, 255, 255);
                }

                $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
                $this->SetFont('Arial', '', 6.5);

                $this->Cell($colW['sn'], 6, $sn++, 1, 0, 'C', $fill);
                $this->Cell($colW['subject'], 6, $r['subject_name'], 1, 0, 'L', $fill);
                $this->Cell($colW['ca1'], 6, $r['assignment_score'] ?? '0', 1, 0, 'C', $fill);
                $this->Cell($colW['ca2'], 6, $r['assignment2_score'] ?? '0', 1, 0, 'C', $fill);
                $this->Cell($colW['test'], 6, $r['test_score'] ?? '0', 1, 0, 'C', $fill);
                $this->Cell($colW['test2'], 6, $r['test2_score'] ?? '0', 1, 0, 'C', $fill);
                $this->Cell($colW['ca_total'], 6, $r['ca_total'] ?? '0', 1, 0, 'C', $fill);
                $this->Cell($colW['exam'], 6, $r['exam_score'] ?? '0', 1, 0, 'C', $fill);

                $this->SetFont('Arial', 'B', 7);
                $this->Cell($colW['total'], 6, $r['total_score'] ?? '0', 1, 0, 'C', $fill);

                $gc = match($grade) {
                    'A' => [0, 120, 0],
                    'B' => [0, 80, 160],
                    'C' => [160, 110, 0],
                    'D' => [180, 90, 0],
                    'E' => [190, 50, 0],
                    'F' => [190, 0, 0],
                    default => [80, 80, 80]
                };
                $this->SetTextColor($gc[0], $gc[1], $gc[2]);
                $this->SetFont('Arial', 'B', 7);
                $this->Cell($colW['grade'], 6, $grade, 1, 0, 'C', $fill);
                $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);

                $this->SetFont('Arial', '', 6.5);
                $posText = $r['subject_position'] ? $r['subject_position'] . $this->ordinalSuffix((int)$r['subject_position']) : '-';
                $this->Cell($colW['pos'], 6, $posText, 1, 0, 'C', $fill);
                $this->Cell($colW['remark'], 6, $remark, 1, 0, 'L', $fill);
                $this->Ln();
                $fill = !$fill;
            }
            $this->Ln(3);
        }

        function SummaryBox($summary, $position, $attendance) {
            $pW = $this->GetPageWidth();
            $mL = $this->lMargin;
            $uW = $pW - $mL - $this->rMargin;
            $halfW = $uW / 2;
            $qtrW = $uW / 4;

            $this->SetFillColor($this->gold[0], $this->gold[1], $this->gold[2]);
            $this->SetDrawColor($this->gold[0], $this->gold[1], $this->gold[2]);
            $this->SetTextColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell($uW, 7, 'PERFORMANCE SUMMARY', 1, 1, 'C', true);

            $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
            $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);

            $stats = [
                ['Total Score', (string)$summary['total_marks']],
                ['Average', number_format($summary['average'], 1) . '%'],
                ['Class Position', $position ? $position . $this->ordinalSuffix($position) : '-'],
                ['Grade', $summary['overall_grade'] . ' - ' . getGradeRemark($summary['overall_grade'])],
            ];

            $this->SetFont('Arial', '', 7.5);
            $y0 = $this->GetY();
            for ($i = 0; $i < 4; $i++) {
                $col = $i % 2;
                $row = intdiv($i, 2);
                $x = $mL + ($col * $halfW);
                $y = $y0 + ($row * 11);

                $this->SetFillColor(250, 250, 250);
                $this->Rect($x, $y, $halfW, 11);

                $this->SetXY($x, $y + 0.5);
                $this->SetFont('Arial', 'B', 6.5);
                $this->SetTextColor($this->muted[0], $this->muted[1], $this->muted[2]);
                $this->Cell($halfW, 4, strtoupper($stats[$i][0]), 0, 1, 'C');

                $this->SetX($x);
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor($this->navy[0], $this->navy[1], $this->navy[2]);
                $this->Cell($halfW, 5.5, $stats[$i][1], 0, 1, 'C');
            }

            $this->SetY($y0 + 22);
            $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
            $this->Line($mL + $halfW, $y0, $mL + $halfW, $this->GetY() - 0.5);

            $y2 = $this->GetY();
            $this->SetFillColor(250, 250, 250);
            $this->Rect($mL, $y2, $halfW, 10);
            $this->Rect($mL + $halfW, $y2, $halfW, 10);

            $this->SetXY($mL, $y2 + 0.5);
            $this->SetFont('Arial', 'B', 6.5);
            $this->SetTextColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $this->Cell($halfW, 3.5, 'PASS / FAIL', 0, 1, 'C');
            $this->SetX($mL);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->Cell($halfW, 5.5, $summary['pass_count'] . ' / ' . $summary['fail_count'], 0, 1, 'C');

            $this->SetXY($mL + $halfW, $y2 + 0.5);
            $this->SetFont('Arial', 'B', 6.5);
            $this->SetTextColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $this->Cell($halfW, 3.5, 'ATTENDANCE', 0, 1, 'C');
            $this->SetX($mL + $halfW);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->Cell($halfW, 5.5, $attendance['present'] . '/' . $attendance['total_days'] . ' (' . $attendance['percentage'] . '%)', 0, 1, 'C');

            $this->SetDrawColor($this->gold[0], $this->gold[1], $this->gold[2]);
            $this->SetLineWidth(0.6);
            $y3 = $this->GetY() + 2;
            $this->Line($mL, $y3, $mL + $uW, $y3);
            $this->SetLineWidth(0.2);
            $this->SetY($y3 + 4);
        }

        function PsychomotorSection($psychomotor) {
            $keys = ['creativity', 'sports', 'practical_skills', 'neatness', 'leadership'];
            $hasData = false;
            foreach ($keys as $k) {
                if (!empty($psychomotor[$k])) { $hasData = true; break; }
            }
            if (!$hasData) return;

            $pW = $this->GetPageWidth();
            $mL = $this->lMargin;
            $uW = $pW - $mL - $this->rMargin;

            $this->SetFillColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell($uW, 6, 'PSYCHOMOTOR SKILLS', 1, 1, 'C', true);

            $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
            $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
            $labels = [
                'creativity' => 'Creativity / Innovation',
                'sports' => 'Sports / Physical Dev.',
                'practical_skills' => 'Practical Skills',
                'neatness' => 'Neatness / Hygiene',
                'leadership' => 'Leadership / Initiative'
            ];
            $grades = ['A' => 'Excellent', 'B' => 'Good', 'C' => 'Fair', 'D' => 'Needs Improvement'];

            $i = 0;
            foreach ($labels as $key => $label) {
                $val = $psychomotor[$key] ?? 'B';
                $gLabel = $grades[$val] ?? $val;

                if ($i % 2 === 0) {
                    $this->SetFillColor(250, 250, 250);
                    $this->Rect($mL, $this->GetY(), $uW, 5.5);
                }
                $x = $mL + (($i % 2) * ($uW / 2));
                $this->SetXY($x + 2, $this->GetY());
                $this->SetFont('Arial', '', 7);
                $this->Cell(($uW / 2) * 0.55, 5.5, $label . ':', 0, 0, 'L');
                $this->SetFont('Arial', 'B', 7);
                $this->Cell(($uW / 2) * 0.45, 5.5, $val . ' (' . $gLabel . ')', 0, 1, 'L');
                if ($i % 2 === 0) $this->SetY($this->GetY() - 5.5);
                $i++;
            }
            if ($i % 2 !== 0) $this->Ln();
            $this->Ln(3);
        }

        function AffectiveSection($affective) {
            $keys = ['honesty', 'punctuality', 'respect', 'cooperation', 'responsibility'];
            $hasData = false;
            foreach ($keys as $k) {
                if (!empty($affective[$k])) { $hasData = true; break; }
            }
            if (!$hasData) return;

            $pW = $this->GetPageWidth();
            $mL = $this->lMargin;
            $uW = $pW - $mL - $this->rMargin;

            $this->SetFillColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell($uW, 6, 'CHARACTER / AFFECTIVE ASSESSMENT', 1, 1, 'C', true);

            $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
            $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
            $labels = [
                'honesty' => 'Honesty / Integrity',
                'punctuality' => 'Punctuality / Regularity',
                'respect' => 'Respect / Manners',
                'cooperation' => 'Cooperation / Teamwork',
                'responsibility' => 'Responsibility / Diligence'
            ];
            $grades = ['A' => 'Excellent', 'B' => 'Good', 'C' => 'Fair', 'D' => 'Needs Improvement'];

            $i = 0;
            foreach ($labels as $key => $label) {
                $val = $affective[$key] ?? 'B';
                $gLabel = $grades[$val] ?? $val;

                if ($i % 2 === 0) {
                    $this->SetFillColor(250, 250, 250);
                    $this->Rect($mL, $this->GetY(), $uW, 5.5);
                }
                $x = $mL + (($i % 2) * ($uW / 2));
                $this->SetXY($x + 2, $this->GetY());
                $this->SetFont('Arial', '', 7);
                $this->Cell(($uW / 2) * 0.55, 5.5, $label . ':', 0, 0, 'L');
                $this->SetFont('Arial', 'B', 7);
                $this->Cell(($uW / 2) * 0.45, 5.5, $val . ' (' . $gLabel . ')', 0, 1, 'L');
                if ($i % 2 === 0) $this->SetY($this->GetY() - 5.5);
                $i++;
            }
            if ($i % 2 !== 0) $this->Ln();
            $this->Ln(3);
        }

        function CommentsAndSignatures($comments) {
            $pW = $this->GetPageWidth();
            $mL = $this->lMargin;
            $uW = $pW - $mL - $this->rMargin;
            $halfW = $uW / 2;

            $this->SetFillColor($this->navy[0], $this->navy[1], $this->navy[2]);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell($uW, 6, 'COMMENTS & SIGNATURES', 1, 1, 'C', true);

            $this->SetDrawColor($this->borderGray[0], $this->borderGray[1], $this->borderGray[2]);
            $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);

            $this->SetFillColor(250, 250, 250);
            $this->SetFont('Arial', 'B', 7);
            $this->Cell($halfW, 6.5, '  CLASS TEACHER:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 7);
            $this->SetFillColor(255, 255, 255);
            $tc = !empty($comments['class_teacher_remark']) ? $comments['class_teacher_remark'] : 'No comment.';
            $this->Cell($halfW, 6.5, '  ' . $tc, 1, 1, 'L', false);

            $this->SetFillColor(250, 250, 250);
            $this->SetFont('Arial', 'B', 7);
            $this->Cell($halfW, 6.5, '  PRINCIPAL:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 7);
            $this->SetFillColor(255, 255, 255);
            $pc = !empty($comments['principal_remark']) ? $comments['principal_remark'] : 'No comment.';
            $this->Cell($halfW, 6.5, '  ' . $pc, 1, 1, 'L', false);

            $this->SetFillColor(255, 255, 255);
            $this->Ln(7);

            $sigY = $this->GetY();
            $lineY = $sigY + 7;

            $this->SetDrawColor($this->dark[0], $this->dark[1], $this->dark[2]);
            $this->SetLineWidth(0.4);
            $this->Line($mL + 5, $lineY, $mL + $halfW - 5, $lineY);
            $this->Line($mL + $halfW + 5, $lineY, $mL + $uW - 5, $lineY);

            $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
            $this->SetFont('Arial', 'B', 8);
            $this->SetXY($mL, $sigY);
            $this->Cell($halfW, 6, 'Class Teacher\'s Signature', 0, 0, 'C');
            $this->Cell($halfW, 6, 'Principal\'s Signature', 0, 1, 'C');

            $this->SetFont('Arial', '', 6.5);
            $this->SetTextColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $this->Cell($halfW, 4, 'Date: __________________', 0, 0, 'C');
            $this->Cell($halfW, 4, 'Date: __________________', 0, 1, 'C');
            $this->Ln(2);
        }

        private function ordinalSuffix($num) {
            if ($num >= 11 && $num <= 13) return 'th';
            return match ($num % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
        }
    }

    $logoFile = __DIR__ . '/../../assets/images/logo.jpg';
    $avatarFile = !empty($student['avatar']) ? __DIR__ . '/../../' . $student['avatar'] : '';

    $pdf = new ReportCardPdf($logoFile, $avatarFile);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->StudentInfoSection($student, $sessionName, $termName);
    $pdf->ScoresTable($summary['results'], $settings);
    $pdf->SummaryBox($summary, $position, $attendance);
    $pdf->PsychomotorSection($psychomotor);
    $pdf->AffectiveSection($affective);
    $pdf->CommentsAndSignatures($comments);

    $filename = 'ReportCard_' . $student['admission_no'] . '_' . str_replace(' ', '_', $termName) . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-file-pdf me-2 text-danger"></i>Download Report Card (PDF)</h4>
    <a href="<?= BASE_URL ?>/admin/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if (isset($_GET['error']) && $_GET['error'] === 'no_scores'): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>The selected student has no scores for this term.</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Select Term</option>
                    <?php foreach ($terms as $t): ?>
                    <?php if ($t['session_id'] == $selectedSession): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTerm === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-select">
                    <option value="">Select Student</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedStudent === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name']) ?> (<?= sanitizeInput($s['admission_no']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" name="action" value="download" class="btn btn-danger" <?= empty($selectedStudent) ? 'disabled' : '' ?>>
                    <i class="fas fa-file-pdf me-1"></i>Download Report Card
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($students)): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-users me-2"></i>Quick Download</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Admission No</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($students as $s): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name']) ?></td>
                    <td><?= sanitizeInput($s['admission_no']) ?></td>
                    <td>
                        <a href="?session_id=<?= $selectedSession ?>&term_id=<?= $selectedTerm ?>&class_id=<?= $selectedClass ?>&student_id=<?= $s['id'] ?>&action=download" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-file-pdf me-1"></i>PDF
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
