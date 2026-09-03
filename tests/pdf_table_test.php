<?php
declare(strict_types=1);
$source=file_get_contents(__DIR__.'/../public/index.php');
foreach (['pdfWrapLines','pdfTableBytes','pdfTextOperand'] as $name) {
    preg_match('/^function '.$name.'\(.*?(?=^function |\z)/ms',$source,$match); eval(trim($match[0]));
}
function tr(string $key): string { return $key; }
function check(bool $value,string $message): void { if (!$value) { throw new RuntimeException($message); } echo "PASS $message\n"; }
$title='Spontanbewerbung Reinigungsmitarbeiter:in mit einem langen vollständigen Titel';
$lines=pdfWrapLines($title,220);
check(implode(' ',$lines)===$title,'Normal words wrap without loss or mid-word breaks');
$long=str_repeat('VeryLongUnbrokenWord',400);
check(implode('',pdfWrapLines($long,100))===$long,'Unbroken values retain every character');
$pdf=pdfTableBytes('Bewerbungen',['Workflowdatum','Job','Firma','Status','Kanal'],[
    ['10.09.2026',$title,'Firma AG','Bereit','Onlineformular'],
    ['11.09.2026',$long.' END-OF-LONG-RECORD','Firma AG','Bewerbungsgespräche','E-Mail'],
]);
check(str_starts_with($pdf,'%PDF-1.4'),'Valid PDF signature');
check(substr_count($pdf,'/Type /Page ')>1,'Oversize row continues on additional pages');
check(str_contains($pdf,'END-OF-LONG-RECORD'),'Last part of oversized value remains in PDF');
check(!str_contains($pdf,'(...)'),'Renderer does not insert ellipses');
preg_match_all('/ ([0-9.]+) Td /',$pdf,$matches);
foreach ($matches[1] as $y) { check((float)$y>=32 && (float)$y<=560,'Text stays inside page height'); }
echo "PDF wrapping and pagination passed.\n";
