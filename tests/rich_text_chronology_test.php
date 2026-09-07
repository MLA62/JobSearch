<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
function repairMojibake(string $value): string { return $value; }
preg_match('/^function e\(.*?(?=^function ensureIndex)/ms', $source, $richFunctions);
if (!$richFunctions) throw new RuntimeException('Rich-text functions missing');
eval(trim($richFunctions[0]));
preg_match('/^function dossierActivityRows\(.*?(?=^function dossierPdfSections)/ms', $source, $timelineFunction);
if (!$timelineFunction) throw new RuntimeException('Timeline function missing');
eval(trim($timelineFunction[0]));

function checkRich(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}

$safe = sanitizeRichText('<p onclick="bad()"><strong>Text</strong><script>alert(1)</script><a href="javascript:bad()">x</a><img src="https://example.test/a.png"><table><tr><td>Zelle</td></tr></table><hr></p>');
checkRich(str_contains($safe, '<strong>Text</strong>') && str_contains($safe, '<table>') && str_contains($safe, '<hr>'), 'Allowed HTML formatting retained');
checkRich(str_contains($safe, 'https://example.test/a.png') && !str_contains($safe, 'onclick') && !str_contains($safe, '<script') && !str_contains($safe, 'javascript:'), 'Unsafe HTML and URLs removed');
checkRich(str_contains(sanitizeRichText("Zeile 1\nZeile 2"), '<br>'), 'Legacy plain text keeps line breaks');
checkRich(in_array('online_notes', richTextFieldNames(), true) && in_array('cover_letter_text', richTextFieldNames(), true), 'Requested long-text fields use editor');
checkRich(str_contains($source, "command('▦',labels.table") && str_contains($source, "command('🖼',labels.image") && str_contains($source, "sourceButton.textContent='HTML'"), 'Mini editor exposes requested tools');
checkRich(str_contains($source, "command('•',labels.bullets") && str_contains($source, "command('1.',labels.numbers") && str_contains($source, "command('→',labels.indent") && str_contains($source, "command('←',labels.outdent") && str_contains($source, "command('Tx',labels.clear"), 'Mini editor exposes list, indent and clear-format tools');
checkRich(str_contains($source, "source.addEventListener('jema:richtext-sync',()=>sync(false))"), 'AI actions can synchronize rich editor values without scheduling stale autosave');
checkRich(str_contains($source, 'const commitSource = (notify = true) =>') && str_contains($source, 'editor.innerHTML = clean') && str_contains($source, "if(shell.classList.contains('is-source')){commitSource();shell.classList.remove('is-source')"), 'HTML source changes are explicitly committed back to the WYSIWYG editor');
checkRich(!str_contains($source, "source.addEventListener('input',()=>{if(shell.classList.contains('is-source'))editor.innerHTML"), 'Source is sanitized once on mode switch instead of while incomplete HTML is typed');
checkRich(str_contains($source, "source.form?.addEventListener('formdata',(event)=>{sync(false);event.formData.set(source.name,source.value);})"), 'Every native or scripted form payload receives the current HTML source value');
checkRich(str_contains($source, "form.querySelectorAll('textarea[data-rich-ready=\"1\"]')") && str_contains($source, "source.dispatchEvent(new Event('jema:richtext-sync'))"), 'Application autosave synchronizes rich text before constructing FormData');
checkRich(str_contains($source, 'richTextHtml($textBody)') && str_contains($source, 'richTextHtml($footer)'), 'Formatted email and footer remain HTML');
checkRich(str_contains($source, 'const labelSets = {') && str_contains($source, "fr:{paragraph:'Paragraphe'") && str_contains($source, "en:{paragraph:'Paragraph'"), 'Mini editor follows the user language');
checkRich(!str_contains($source, "mb_strimwidth((string)\$job['description']") && str_contains($source, "mb_strimwidth(richTextPlain((string)\$job['description'])"), 'Job cards and tables render descriptions as plain text');
checkRich(str_contains($source, "richTextHtml((string)\$application['job_description'])"), 'Application dossier renders sanitized rich text');

$timeline = dossierActivityRows([
    'history'=>[['id'=>2,'changed_at'=>'2026-09-07 14:02:00']],
    'contact_logs'=>[['id'=>3,'occurred_at'=>'2026-09-07 14:00:00']],
    'calendar_events'=>[['id'=>1,'starts_at'=>'2026-09-07 13:40:00']],
]);
checkRich(array_column($timeline, 'at') === ['2026-09-07 13:40:00','2026-09-07 14:00:00','2026-09-07 14:02:00'], 'Mixed activities sorted oldest to newest');
checkRich(!str_contains($source, 'application_status_history WHERE application_id=? ORDER BY changed_at DESC'), 'Status histories are ascending');
checkRich(!str_contains($source, 'contact_id=? ORDER BY CASE status'), 'Contact logs are chronological rather than status-grouped');

echo "Rich-text and chronology contract passed\n";
