<?php
require_once __DIR__ . '/../../backend/bootstrap.php';
$user = require_admin();

$dataset = clean_string($_GET['dataset'] ?? 'applications', 40);
$format = strtolower(clean_string($_GET['format'] ?? 'csv', 10));

if (!allowed_dataset($dataset)) json_response(['success'=>false,'message'=>'Invalid dataset.'], 422);
if (!can_view_dataset($user, $dataset) || !has_permission($user, 'export_reports')) {
    json_response(['success'=>false,'message'=>'Your account cannot export this report.'], 403);
}

$stmt = db()->prepare('SELECT * FROM submissions WHERE dataset = ? ORDER BY created_at DESC');
$stmt->execute([$dataset]);
$rows = $stmt->fetchAll();

$allKeys = [];
foreach ($rows as $row) {
    $payload = json_decode($row['payload_json'], true) ?: [];
    foreach (array_keys($payload) as $k) $allKeys[$k] = true;
}

$headers = array_merge(['Reference','Dataset','Type','Category','Status','Name','Phone','Email','Community','Ward','Internal Notes','Created At'], array_keys($allKeys));
$dataRows = [];
foreach ($rows as $row) {
    $payload = json_decode($row['payload_json'], true) ?: [];
    $line = [$row['reference'],$row['dataset'],$row['type'],$row['category'],$row['status'],$row['name'],$row['phone'],$row['email'],$row['community'],$row['ward'],$row['internal_notes'],$row['created_at']];
    foreach (array_keys($allKeys) as $k) $line[] = is_array($payload[$k] ?? '') ? json_encode($payload[$k]) : ($payload[$k] ?? '');
    $dataRows[] = $line;
}

audit_log('report_exported', $dataset, null, ['format'=>$format]);

if ($format === 'xlsx') {
    output_xlsx($dataset . '_' . date('Ymd_His') . '.xlsx', $headers, $dataRows);
}

output_csv($dataset . '_' . date('Ymd_His') . '.csv', $headers, $dataRows);

function output_csv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $line) fputcsv($out, $line);
    exit;
}

function xlsx_col(int $index): string {
    $letters = '';
    while ($index >= 0) {
        $letters = chr(($index % 26) + 65) . $letters;
        $index = intdiv($index, 26) - 1;
    }
    return $letters;
}

function xml_escape($value): string {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function output_excel_compatible_xls(string $filename, array $headers, array $rows): void {
    $filename = preg_replace('/\.xlsx$/', '.xls', $filename);
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "<table border=\"1\"><tr>";
    foreach ($headers as $h) echo '<th>' . htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8') . '</th>';
    echo "</tr>";
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

function output_xlsx(string $filename, array $headers, array $rows): void {
    if (!class_exists('ZipArchive')) {
        output_excel_compatible_xls($filename, $headers, $rows);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        output_excel_compatible_xls($filename, $headers, $rows);
    }

    $sheetData = [];
    $allRows = array_merge([$headers], $rows);
    foreach ($allRows as $rIndex => $row) {
        $cells = [];
        foreach ($row as $cIndex => $value) {
            $ref = xlsx_col($cIndex) . ($rIndex + 1);
            $cells[] = '<c r="' . $ref . '" t="inlineStr"><is><t>' . xml_escape($value) . '</t></is></c>';
        }
        $sheetData[] = '<row r="' . ($rIndex + 1) . '">' . implode('', $cells) . '</row>';
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode('', $sheetData) . '</sheetData></worksheet>');
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}
