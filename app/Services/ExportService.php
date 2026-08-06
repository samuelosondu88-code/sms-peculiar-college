<?php
namespace App\Services;

/**
 * Exports AI-generated / lesson content as PDF or DOCX.
 *
 * PDF uses the bundled FPDF library (lib/fpdf.php). DOCX writes a minimal,
 * valid Office Open XML package with ZipArchive so Word/LibreOffice open it.
 */
class ExportService
{
    /**
     * @param array<string,string> $sections  section label => content
     */
    public static function toPdf(string $title, array $sections, string $schoolName = ''): void
    {
        require_once __DIR__ . '/../../lib/fpdf.php';

        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, self::cleanTitle($title), 0, 1, 'C');
        if ($schoolName !== '') {
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(120);
            $pdf->Cell(0, 6, $schoolName, 0, 1, 'C');
        }
        $pdf->SetTextColor(0);
        $pdf->Ln(4);

        foreach ($sections as $label => $content) {
            if (trim((string)$content) === '') continue;
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->MultiCell(0, 7, html_entity_decode(self::cleanTitle($label), ENT_QUOTES, 'UTF-8'), 0, 1);
            $pdf->SetFont('Arial', '', 10);

            $text = strip_tags((string)$content);
            foreach (explode("\n", $text) as $line) {
                $line = rtrim($line);
                if (trim($line) === '') {
                    $pdf->Ln(2);
                    continue;
                }
                $bullet = preg_match('/^\s*[-*]\s+/', $line);
                $clean = preg_replace('/^\s*[-*]\s+/', '', $line);
                $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');
                $pdf->MultiCell(0, 5, $bullet ? "\xE2\x80\xA2 " . $clean : $clean, 0, 1);
            }
            $pdf->Ln(4);
        }

        $filename = self::safeFilename($title) . '.pdf';
        $pdf->Output($filename, 'D');
        exit;
    }

    public static function toDocx(string $title, array $sections, string $schoolName = ''): void
    {
        $filename = self::safeFilename($title) . '.docx';
        $tmp = tempnam(sys_get_temp_dir(), 'pic') . '.docx';

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create DOCX archive.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::relsXml());
        $zip->addFromString('word/_rels/document.xml.rels', self::docRelsXml());
        $zip->addFromString('word/document.xml', self::documentXml($title, $sections, $schoolName));
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    private static function documentXml(string $title, array $sections, string $schoolName): string
    {
        $body = '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>';

        $body = self::paragraph(self::xml($schoolName), '9') . self::paragraph(self::xml($title), '18', true);
        foreach ($sections as $label => $content) {
            if (trim((string)$content) === '') continue;
            $body .= self::paragraph(self::xml(html_entity_decode($label, ENT_QUOTES, 'UTF-8')), '13', true);
            foreach (explode("\n", strip_tags((string)$content)) as $line) {
                $line = rtrim($line);
                if (trim($line) === '') continue;
                $bullet = preg_match('/^\s*[-*]\s+/', $line);
                $clean = preg_replace('/^\s*[-*]\s+/', '', $line);
                $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');
                $body .= self::paragraph(self::xml($bullet ? "\xE2\x80\xA2  " . $clean : $clean), '11', false, $bullet);
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body . '</w:body></w:document>';
    }

    private static function paragraph(string $text, string $size, bool $bold = false, bool $bullet = false): string
    {
        $rPr = '<w:rPr><w:sz w:val="' . ($size * 2) . '"/>'
            . ($bold ? '<w:b/>' : '')
            . ($bullet ? '<w:color w:val="595959"/>' : '<w:color w:val="000000"/>')
            . '</w:rPr>';
        $pPr = '<w:pPr><w:spacing w:after="120"/><w:jc w:val="' . ($bold ? 'center' : 'left') . '"/></w:pPr>';
        return '<w:p>' . $pPr . '<w:r>' . $rPr . '<w:t xml:space="preserve">' . $text . '</w:t></w:r></w:p>';
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>';
    }

    private static function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private static function docRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function xml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function safeFilename(string $title): string
    {
        $name = preg_replace('/[^\w\-\s]/u', '', $title);
        $name = preg_replace('/\s+/', '_', trim($name));
        return $name !== '' ? $name : 'lesson-content';
    }

    private static function cleanTitle(string $title): string
    {
        $title = preg_replace('/([a-z])([A-Z])/', '$1 $2', $title);
        return ucwords(str_replace('_', ' ', $title));
    }
}
