<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
$start = strpos($source, 'function applicationCvInputParts(');
$end = strpos($source, 'function applicationPrompt(', $start);
if ($start === false || $end === false) throw new RuntimeException('CV input builder not found.');
eval(substr($source, $start, $end - $start));

$queryStart = strpos($source, 'function applicationCvSourceRows(');
$queryEnd = strpos($source, 'function applicationCvInputParts(', $queryStart);
$query = substr($source, $queryStart, $queryEnd - $queryStart);
foreach (["d.user_id=?", "d.scope='profile'", 'd.is_current=1', 'd.deleted_at IS NULL', "dt.code='cv'"] as $required) {
    if (!str_contains($query, $required)) throw new RuntimeException('CV source query lacks ' . $required);
}
if (str_contains($query, 'LIMIT 1')) throw new RuntimeException('Only one CV would be available.');

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
        ['id'=>11, 'title'=>'CV Deutsch', 'version'=>2, 'language_code'=>'de-CH', 'original_filename'=>'CV-D.pdf', 'storage_path'=>'storage/documents/7/one.pdf', 'corrected_text'=>str_repeat('Korrigierte Berufserfahrung. ', 1100)],
        ['id'=>12, 'title'=>'CV English', 'version'=>1, 'language_code'=>'en-GB', 'original_filename'=>'CV-E.docx', 'storage_path'=>'storage/documents/7/two.docx', 'corrected_text'=>''],
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
    echo "PASS all current master-data CV files and corrected text reach the AI request; foreign files are blocked\n";
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
