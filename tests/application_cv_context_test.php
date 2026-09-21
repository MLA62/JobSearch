<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
$selectorStart = strpos($source, 'function applicationLatestCvRowsByLanguage(');
$selectorEnd = strpos($source, 'function applicationCvSourceRows(', $selectorStart);
if ($selectorStart === false || $selectorEnd === false) throw new RuntimeException('Latest-CV selector not found.');
eval(substr($source, $selectorStart, $selectorEnd - $selectorStart));
$start = strpos($source, 'function applicationCvInputParts(');
$end = strpos($source, 'function applicationWritingContext(', $start);
if ($start === false || $end === false) throw new RuntimeException('CV input builder not found.');
eval(substr($source, $start, $end - $start));

$queryStart = strpos($source, 'function applicationCvSourceRows(');
$queryEnd = strpos($source, 'function applicationCvInputParts(', $queryStart);
$query = substr($source, $queryStart, $queryEnd - $queryStart);
foreach (["d.user_id=?", "d.scope='profile'", 'd.is_current=1', 'd.deleted_at IS NULL', "dt.code='cv'", 'd.updated_at', 'return applicationLatestCvRowsByLanguage($rows)'] as $required) {
    if (!str_contains($query, $required)) throw new RuntimeException('CV source query lacks ' . $required);
}
if (str_contains($query, 'LIMIT 1')) throw new RuntimeException('Only one CV would be available.');
if (!str_contains($source, '$cvRows = applicationCvSourceRows($db, $userId);')) throw new RuntimeException('CVs are not loaded afresh for each AI request.');

$inventory = [
    ['id'=>11, 'language_code'=>'de-CH', 'version'=>8, 'updated_at'=>'2026-09-10 12:00:00'],
    ['id'=>12, 'language_code'=>'en-GB', 'version'=>3, 'updated_at'=>'2026-09-12 12:00:00'],
    ['id'=>13, 'language_code'=>'en-GB', 'version'=>1, 'updated_at'=>'2026-09-14 12:00:00'],
    ['id'=>14, 'language_code'=>'de-CH', 'version'=>1, 'updated_at'=>'2026-09-15 12:00:00'],
    ['id'=>15, 'language_code'=>'fr-CH', 'version'=>2, 'updated_at'=>'2026-09-11 12:00:00'],
    ['id'=>16, 'language_code'=>'EN_gb', 'version'=>1, 'updated_at'=>'2026-09-14 12:00:00'],
];
$selected = applicationLatestCvRowsByLanguage($inventory);
if (array_column($selected, 'id') !== [14, 16, 15]) throw new RuntimeException('Latest CV per metadata language must win by modification date, then ID, not version or title.');
$inventory[] = ['id'=>17, 'language_code'=>'fr-CH', 'version'=>1, 'updated_at'=>'2026-09-16 12:00:00'];
if (array_column(applicationLatestCvRowsByLanguage($inventory), 'id') !== [17, 14, 16]) throw new RuntimeException('A later CV modification is not reflected on a subsequent selection.');
if (applicationLatestCvRowsByLanguage([]) !== []) throw new RuntimeException('Empty CV inventory must remain empty.');

$publicRoot = sys_get_temp_dir() . '/jema-cv-test-' . bin2hex(random_bytes(8));
$documentRoot = $publicRoot . '/storage/documents';
$ownerRoot = $documentRoot . '/7';
$otherRoot = $documentRoot . '/8';
mkdir($ownerRoot, 0700, true);
mkdir($otherRoot, 0700, true);
try {
    file_put_contents($ownerRoot . '/one.pdf', '%PDF-1.4 CV one');
    file_put_contents($ownerRoot . '/two.docx', 'CV two content');
    file_put_contents($otherRoot . '/foreign.pdf', '%PDF-1.4 foreign');
    $rows = [
        ['id'=>11, 'title'=>'CV Deutsch', 'version'=>2, 'language_code'=>'de-CH', 'document_type_code'=>'cv', 'original_filename'=>'CV-D.pdf', 'storage_path'=>'storage/documents/7/one.pdf', 'corrected_text'=>str_repeat('Korrigierte Berufserfahrung. ', 1100)],
        ['id'=>12, 'title'=>'CV English', 'version'=>1, 'language_code'=>'en-GB', 'document_type_code'=>'cv', 'original_filename'=>'CV-E.docx', 'storage_path'=>'storage/documents/7/two.docx', 'corrected_text'=>''],
    ];
    $parts = applicationCvInputParts($rows, 7, $documentRoot);
    if (count($parts) !== 5) throw new RuntimeException('Both full CVs and corrected text must be included.');
    if ($parts[1]['type'] !== 'input_file' || $parts[2]['type'] !== 'input_text' || $parts[4]['type'] !== 'input_file') {
        throw new RuntimeException('CV input sequence is wrong.');
    }
    if (!str_contains($parts[1]['file_data'], base64_encode('%PDF-1.4 CV one')) || !str_contains($parts[4]['file_data'], base64_encode('CV two content'))) {
        throw new RuntimeException('Original CV file bytes were not provided.');
    }
    if (substr_count($parts[2]['text'], 'Korrigierte Berufserfahrung') !== 1100) throw new RuntimeException('Corrected CV text is missing or truncated.');
    if (applicationCvInputParts([], 7, $documentRoot) !== []) throw new RuntimeException('Empty CV inventory should not add file parts.');
    $otherDocument=$rows[0];
    $otherDocument['document_type_code']='certificate';
    try {
        applicationCvInputParts([$otherDocument],7,$documentRoot);
        throw new RuntimeException('A non-CV document was attached to the AI request.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(),'Nur Stammdaten-Lebensläufe')) throw $exception;
    }
    $foreign = $rows[0];
    $foreign['storage_path'] = 'storage/documents/8/foreign.pdf';
    try {
        applicationCvInputParts([$foreign], 7, $documentRoot);
        throw new RuntimeException('Foreign CV was accepted.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'nicht sicher gelesen')) throw $exception;
    }
    if (!str_contains($source, "array_push(\$inputParts, ...applicationCvInputParts(\$cvRows, \$userId, storageRoot()))")) {
        throw new RuntimeException('CV parts are not sent with the application AI request.');
    }
    if (!str_contains($source, "'input'=>[['role'=>'user','content'=>\$inputParts]]")) {
        throw new RuntimeException('Responses input does not include the CV parts.');
    }
    echo "PASS latest current master-data CV per language is selected afresh; full files and corrected text reach AI; foreign files are blocked\n";
} finally {
    unlink($ownerRoot . '/one.pdf');
    unlink($ownerRoot . '/two.docx');
    unlink($otherRoot . '/foreign.pdf');
    rmdir($ownerRoot);
    rmdir($otherRoot);
    rmdir($documentRoot);
    rmdir($publicRoot . '/storage');
    rmdir($publicRoot);
}
