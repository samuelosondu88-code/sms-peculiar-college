<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Report card PDF generation.
 *
 * Preferred engine is mPDF (HTML/CSS), which is enabled when Composer has been
 * installed. When mPDF is unavailable (e.g. on a free shared host without
 * Composer), callers should fall back to the classic FPDF renderer so report
 * card downloads keep working.
 *
 * @see \App\Services\ReportCardService::mpdfAvailable()
 */
final class ReportCardService
{
    /**
     * Whether mPDF is available (i.e. Composer install has been run).
     */
    public static function mpdfAvailable(): bool
    {
        return class_exists(\Mpdf\Mpdf::class);
    }

    /**
     * Build the full HTML document for a report card.
     *
     * @param array $d Assembled data: student, summary, results, position,
     *   attendance, sessionName, termName, psychomotor, affective, comments,
     *   settings, logoPath, passportPath, watermarkFile.
     */
    public static function renderHtml(array $d): string
    {
        $student = $d['student'];
        $summary = $d['summary'];
        $results = $d['results'];
        $position = (int)($d['position'] ?? 0);
        $attendance = $d['attendance'];
        $sessionName = $d['sessionName'];
        $termName = $d['termName'];
        $psychomotor = $d['psychomotor'];
        $affective = $d['affective'];
        $comments = $d['comments'];
        $settings = $d['settings'];

        $logo = self::imgTag(self::toDataUri($d['logoPath'] ?? ''), 'Logo');
        $passport = self::imgTag(self::toDataUri($d['passportPath'] ?? ''), 'Passport');
        $watermark = self::toDataUri($d['watermarkFile'] ?? '');
        $watermarkCss = $watermark ? "background-image:url('{$watermark}');" : '';

        $rows = [];
        $sn = 1;
        foreach ($results as $r) {
            // DB DECIMAL columns come back as strings; cast to float for the
            // strict-typed getGrade() signature (getResultSettings()).
            $grade = \getGrade(
                (float)$r['total_score'],
                (float)($settings['grade_a_min'] ?? 70),
                (float)($settings['grade_b_min'] ?? 60),
                (float)($settings['grade_c_min'] ?? 50),
                (float)($settings['grade_d_min'] ?? 45),
                (float)($settings['grade_e_min'] ?? 40)
            );
            $remark = \getGradeRemark($grade);
            $remark = $remark === 'Fail' ? 'Needs Improvement' : $remark;
            $posText = $r['subject_position'] ? $r['subject_position'] . self::ordinalSuffix((int)$r['subject_position']) : '-';
            $gradeClass = strtolower((string)$grade);
            $rows[] = '<tr>'
                . '<td>' . $sn . '</td>'
                . '<td class="l">' . $r['subject_name'] . '</td>'
                . '<td>' . ($r['assignment_score'] ?? '0') . '</td>'
                . '<td>' . ($r['assignment2_score'] ?? '0') . '</td>'
                . '<td>' . ($r['test_score'] ?? '0') . '</td>'
                . '<td>' . ($r['test2_score'] ?? '0') . '</td>'
                . '<td>' . ($r['ca_total'] ?? '0') . '</td>'
                . '<td>' . ($r['exam_score'] ?? '0') . '</td>'
                . '<td class="b">' . ($r['total_score'] ?? '0') . '</td>'
                . '<td class="g ' . $gradeClass . '">' . $grade . '</td>'
                . '<td>' . $posText . '</td>'
                . '<td class="l">' . $remark . '</td>'
                . '</tr>';
            $sn++;
        }
        $scoresTable = implode("\n", $rows);

        $average = number_format((float)$summary['average'], 1);
        $overallRemark = \getGradeRemark($summary['overall_grade']);
        $positionText = $position > 0 ? $position . self::ordinalSuffix($position) : '-';

        $srcName = ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '');
        $srcClass = ($student['class_name'] ?? '') . ' ' . ($student['section'] ?? '');
        $srcAdmission = $student['admission_no'] ?? '';
        $srcGender = ucfirst($student['gender'] ?? 'N/A');

        $psychHtml = self::renderAssessments('PSYCHOMOTOR SKILLS', [
            'creativity' => 'Creativity / Innovation',
            'sports' => 'Sports / Physical Dev.',
            'practical_skills' => 'Practical Skills',
            'neatness' => 'Neatness / Hygiene',
            'leadership' => 'Leadership / Initiative',
        ], $psychomotor);

        $affectiveHtml = self::renderAssessments('CHARACTER / AFFECTIVE ASSESSMENT', [
            'honesty' => 'Honesty / Integrity',
            'punctuality' => 'Punctuality / Regularity',
            'respect' => 'Respect / Manners',
            'cooperation' => 'Cooperation / Teamwork',
            'responsibility' => 'Responsibility / Diligence',
        ], $affective);

        $tcRemark = $comments['class_teacher_remark'] ?? 'No comment.';
        $pcRemark = $comments['principal_remark'] ?? 'No comment.';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body>
    <div class="viewport" style="{$watermarkCss}">
        <div class="brand">
            <div class="logo">{$logo}</div>
            <h1>SCHOOL_NAME_PLACEHOLDER</h1>
            <div class="sub">SCHOOL_ADDRESS_PLACEHOLDER</div>
            <div class="sub">Tel: SCHOOL_PHONE_PLACEHOLDER | Email: SCHOOL_EMAIL_PLACEHOLDER</div>
            <div class="motto">&ldquo;SCHOOL_MOTTO_PLACEHOLDER&rdquo;</div>
        </div>

        <h2 class="card-title">REPORT CARD</h2>
        <div class="card-sub">{$sessionName} | {$termName}</div>

        <table class="info">
            <tr>
                <td class="passport">{$passport}</td>
                <td>
                    <table class="info-inner">
                        <tr><td class="k">Name</td><td>{$srcName}</td></tr>
                        <tr><td class="k">Class</td><td>{$srcClass}</td></tr>
                        <tr><td class="k">Admission No</td><td>{$srcAdmission}</td></tr>
                        <tr><td class="k">Gender</td><td>{$srcGender}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="scores">
            <thead>
                <tr>
                    <th class="sn">#</th><th class="l">Subject</th><th>Asgn 1</th><th>Asgn 2</th>
                    <th>Test 1</th><th>Test 2</th><th>CA Total</th><th>Exam</th><th>Total</th>
                    <th>Grade</th><th>Pos</th><th class="l">Remark</th>
                </tr>
            </thead>
            <tbody>
                {$scoresTable}
            </tbody>
        </table>

        <h3 class="section gold">PERFORMANCE SUMMARY</h3>
        <table class="summary">
            <tr>
                <td><div class="sum-l">Total Score</div><div class="sum-v">{$summary['total_marks']}</div></td>
                <td><div class="sum-l">Average</div><div class="sum-v">{$average}%</div></td>
                <td><div class="sum-l">Class Position</div><div class="sum-v">{$positionText}</div></td>
                <td><div class="sum-l">Grade</div><div class="sum-v">{$summary['overall_grade']} - {$overallRemark}</div></td>
            </tr>
            <tr>
                <td><div class="sum-l">Pass / Fail</div><div class="sum-v">{$summary['pass_count']} / {$summary['fail_count']}</div></td>
                <td><div class="sum-l">Attendance</div><div class="sum-v">{$attendance['present']}/{$attendance['total_days']} ({$attendance['percentage']}%)</div></td>
                <td class="pad"></td>
                <td class="pad"></td>
            </tr>
        </table>

        {$psychHtml}
        {$affectiveHtml}

        <h3 class="section">COMMENTS &amp; SIGNATURES</h3>
        <table class="comments">
            <tr><td class="k">Class Teacher:</td><td>{$tcRemark}</td></tr>
            <tr><td class="k">Principal:</td><td>{$pcRemark}</td></tr>
        </table>
        <table class="sign">
            <tr>
                <td>Class Teacher&rsquo;s Signature</td>
                <td>Principal&rsquo;s Signature</td>
            </tr>
            <tr>
                <td class="date">Date: ______</td>
                <td class="date">Date: ______</td>
            </tr>
        </table>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render the HTML with the real school constants substituted.
     */
    public static function renderHtmlTyped(array $d): string
    {
        $html = self::renderHtml($d);
        return str_replace(
            ['SCHOOL_NAME_PLACEHOLDER', 'SCHOOL_ADDRESS_PLACEHOLDER', 'SCHOOL_PHONE_PLACEHOLDER', 'SCHOOL_EMAIL_PLACEHOLDER', 'SCHOOL_MOTTO_PLACEHOLDER'],
            [\SCHOOL_NAME, \SCHOOL_ADDRESS, \SCHOOL_PHONE, \SCHOOL_EMAIL, \SCHOOL_MOTTO],
            $html
        );
    }

    /**
     * Stream a downloadable PDF using mPDF. Caller must first check
     * mpdfAvailable().
     */
    public static function stream(array $d, string $filename): void
    {
        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 18,
            'default_font' => 'dejavusans',
        ]);
        $mpdf->WriteHTML(self::reportCss() . self::renderHtmlTyped($d));
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private static function renderAssessments(string $title, array $labels, array $data): string
    {
        $has = false;
        foreach (array_keys($labels) as $k) {
            if (!empty($data[$k])) {
                $has = true;
                break;
            }
        }
        if (!$has) {
            return '';
        }

        $grades = ['A' => 'Excellent', 'B' => 'Good', 'C' => 'Fair', 'D' => 'Needs Improvement'];
        $cells = '';
        $i = 0;
        foreach ($labels as $key => $label) {
            $val = $data[$key] ?? 'B';
            $gLabel = $grades[$val] ?? $val;
            $cells .= '<div class="assess"><span>' . $label . ':</span> <b>' . $val . ' (' . $gLabel . ')</b></div>';
            $i++;
            if ($i % 2 === 0) {
                $cells .= '</div><div class="assess-row">';
            }
        }
        return '<h3 class="section">' . $title . '</h3><div class="assess-row">' . $cells . '</div>';
    }

    private static function toDataUri(string $path): string
    {
        if (!$path || !is_file($path)) {
            return '';
        }
        try {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function imgTag(string $dataUri, string $alt): string
    {
        return $dataUri ? "<img src=\"{$dataUri}\" alt=\"{$alt}\">" : '';
    }

    private static function ordinalSuffix(int $num): string
    {
        if ($num >= 11 && $num <= 13) {
            return 'th';
        }
        return match ($num % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    private static function reportCss(): string
    {
        return <<<CSS
        <style>
        body { font-family: 'dejavusans', Arial, Helvetica, sans-serif; color: #333; }
        .viewport { background-position: center; background-size: 130mm 130mm; background-repeat: no-repeat; }
        .brand { text-align: center; }
        .brand .logo img { width: 26mm; height: 26mm; }
        .brand h1 { color: #0b1f3a; margin: 2mm 0 0 0; font-size: 18pt; }
        .brand .sub { font-size: 8pt; color: #787878; line-height: 1.4; }
        .brand .motto { color: #d4af37; font-style: italic; font-size: 8pt; margin-top: 1mm; }
        .card-title { text-align: center; color: #0b1f3a; font-size: 15pt; margin: 3mm 0 1mm 0; }
        .card-sub { text-align: center; color: #787878; font-size: 8pt; margin-bottom: 3mm; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 4mm; background: #f5f5f5; }
        table.info .passport { width: 34mm; }
        table.info .passport img { width: 30mm; height: 30mm; }
        table.info-inner { border-collapse: collapse; width: 100%; }
        table.info-inner td { font-size: 8.5pt; padding: 0.6mm 2mm; }
        table.info-inner .k { font-weight: bold; color: #0b1f3a; width: 32mm; }
        table.scores { width: 100%; border-collapse: collapse; }
        table.scores th { background: #0b1f3a; color: #fff; font-size: 6.5pt; padding: 1mm; border: 0.2mm solid #0b1f3a; }
        table.scores td { border: 0.2mm solid #d2d2d2; font-size: 6.5pt; text-align: center; padding: 0.8mm; }
        table.scores .l { text-align: left; }
        table.scores .b { font-weight: bold; }
        .g.a { color: #007800; } .g.b { color: #0050a0; } .g.c { color: #a06e00; }
        .g.d { color: #b45a00; } .g.e { color: #be3200; } .g.f { color: #be0000; }
        table.scores tr:nth-child(even) td { background: #f7f7fc; }
        h3.section { background: #0b1f3a; color: #fff; font-size: 8pt; padding: 1.5mm 2mm; margin: 3mm 0 1mm 0; }
        h3.section.gold { background: #d4af37; color: #0b1f3a; }
        table.summary { width: 100%; border-collapse: collapse; }
        table.summary td { border: 0.2mm solid #d2d2d2; width: 25%; text-align: center; background: #fafafa; padding: 1mm; }
        table.summary td.pad { border: none; background: none; }
        .sum-l { font-size: 6.5pt; color: #787878; font-weight: bold; }
        .sum-v { font-size: 10pt; color: #0b1f3a; font-weight: bold; }
        .assess-row { margin-top: 1mm; }
        .assess { display: inline-block; width: 49%; font-size: 7.5pt; padding: 0.6mm 1mm; }
        table.comments { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        table.comments td { border: 0.2mm solid #d2d2d2; font-size: 7.5pt; padding: 1.2mm; background: #fafafa; }
        table.comments .k { font-weight: bold; width: 30mm; }
        table.sign { width: 100%; margin-top: 6mm; }
        table.sign td { width: 50%; text-align: center; font-size: 7.5pt; padding-top: 8mm; }
        table.sign .date { font-size: 6.5pt; color: #777; padding-top: 1mm; }
        </style>
        CSS;
    }
}