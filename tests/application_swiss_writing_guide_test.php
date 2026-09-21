<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../public/index.php');
if (!is_string($source)) throw new RuntimeException('Application source unavailable.');
$start=strpos($source,'function applicationWritingRelationship(');
$end=strpos($source,'function applicationWritingContext(',$start);
if ($start===false || $end===false) throw new RuntimeException('Writing guide helpers missing.');
eval(substr($source,$start,$end-$start));

$job=['company_id'=>10,'company_name'=>'ROCKEN','company_is_intermediary'=>1,'intermediary_company_id'=>0];
$recipient=['recipient_company_id'=>10,'company_name'=>'ROCKEN','recipient_is_intermediary'=>1];
$role=applicationWritingRelationship($job,$recipient);
if ($role['recipient_role']!=='Vermittler' || $role['end_client_known'] || $role['employer_name']!=='') {
    throw new RuntimeException('Recruiter with unknown client was treated as employer.');
}
$job=['company_id'=>20,'company_name'=>'Bardusch AG','company_is_intermediary'=>0,'intermediary_company_id'=>10];
$role=applicationWritingRelationship($job,$recipient);
if (!$role['end_client_known'] || $role['end_client_name']!=='Bardusch AG') {
    throw new RuntimeException('Verified client of intermediary was not identified.');
}
$direct=applicationWritingRelationship($job,['recipient_company_id'=>20,'company_name'=>'Bardusch AG','recipient_is_intermediary'=>0]);
if ($direct['recipient_role']!=='direkter Arbeitgeber' || $direct['end_client_known']) {
    throw new RuntimeException('Direct employer was misclassified.');
}
$guide=applicationSwissWritingGuide();
foreach (['one page','job-specific','YOU','recipient/address','Swiss German','user edit instruction'] as $rule) {
    if (!str_contains($guide,$rule)) throw new RuntimeException('Swiss guide rule missing: '.$rule);
}
if (!str_contains($source,"'instructions'=>\$writingRules.")) throw new RuntimeException('Guide is not passed to the API.');
if (!str_contains($source,'if the end client is not verified')) throw new RuntimeException('Unknown client role is not communicated to AI.');
if (!str_contains($source,'$recipientIssues=applicationRecipientPerspectiveReview(') || !str_contains($source,"'name'=>'recipient_review','strict'=>true")) {
    throw new RuntimeException('Independent simulated recipient review is not wired to the final draft.');
}
if (!str_contains($source,'$baseInputParts,$texts,$editTargets') || !str_contains($source,'if ($attempt===1 && aiWorkCanSpend()) {')) {
    throw new RuntimeException('Recipient review must use current sources and at most one targeted retry.');
}
echo "PASS Swiss writing guide and recipient/employer role contract\n";
