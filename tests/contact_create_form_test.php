<?php
declare(strict_types=1);
$source = file_get_contents($argv[1] ?? __DIR__ . '/../public/index.php');
preg_match('/^        <div class="<\?= \(\$contactEdit \|\| \$newContact\).*$/m', $source, $match);
if (!$match) throw new RuntimeException('Contact editor template missing');
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function tr($key, ...$args): string { return $key; }
function csrfToken(): string { return 'test-token'; }
function documentLanguageChoices(): array { return ['de-CH'=>'Deutsch']; }
function sfHeader(...$args): string { return '<th>Field</th>'; }
$contactEdit = null;
$newContact = true;
$contactForm = array_fill_keys(['id','company_id','first_name','last_name','position','department','email','phone','mobile','linkedin_url','preferred_language','notes'], '');
$companies = [['id'=>12,'name'=>'Example']];
$contactRows = [];
$contactSf = $contactPreserve = [];
ob_start();
eval('?>' . $match[0]);
$html = ob_get_clean();
foreach (['id="contact-editor"', 'value="create_contact_global"', 'name="first_name"', 'name="last_name"', 'value="12"'] as $expected) {
    if (!str_contains($html, $expected)) throw new RuntimeException('Missing: ' . $expected);
}
if (str_contains($html, 'id="contact-log"')) throw new RuntimeException('Unsaved contacts must not expose activity form');
echo "PASS: empty contact list exposes create form without activity form\n";
