<?php
declare(strict_types=1);

// Execute actual pure production helpers, never bootstrap or production credentials.
$source = file_get_contents(__DIR__.'/../public/index.php');
$wanted = array_flip(['normalizeLocale','supportedLocales','repairMojibake','plainText','readableText','findJobPosting','importMetaContent','importHtmlMatch','importHiringOrganization','importJobLocation','importJobContact','importCleanTitle','importCompanyFromText','importVisibleCompany','importLooksLikeJobDetail','importJobHtml','jobDisplayText','jobDisplayLanguage','mergeJobDisplayTranslations','localizeJobResults','currentLocale']);
$wanted['multilingualUiEnabled'] = true;
$wanted['importCompanyDetails'] = true;
foreach (['importResolveUrl','importOriginalCandidates','importSameJob','importVisibleContacts','importCompanyWebsite','importCompanyPageDetails','importFromUrl'] as $helper) $wanted[$helper]=true;
$tokens = token_get_all($source);
for ($i=0; $i<count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
    $j=$i+1;
    while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
    if (!is_array($tokens[$j]) || !isset($wanted[$tokens[$j][1]])) continue;
    $name=$tokens[$j][1]; $code=''; $depth=0; $started=false;
    for (; $i<count($tokens); $i++) {
        $token=$tokens[$i]; $code.=is_array($token)?$token[1]:$token;
        if ($token === '{' || (is_array($token) && in_array($token[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true))) { $depth++; $started=true; }
        elseif ($token === '}' && --$depth === 0 && $started) break;
    }
    eval($code); unset($wanted[$name]);
}
function verify(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
verify(!$wanted, 'All production helpers loaded');
$original = "<p>Nous recherchons un conseiller à Bienne.</p><p>Vos tâches : accompagner nos clients.</p><ul><li>Français courant.</li><li>Travail à 80 %.</li></ul>";
$schema = ['@type'=>'JobPosting','title'=>'Conseiller clientèle','hiringOrganization'=>['name'=>'Exemple SA'],'description'=>$original,'jobLocation'=>['address'=>['addressLocality'=>'Bienne']]];
$html='<html><head><meta name="description" content="Find your next great opportunity in Switzerland"><script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE).'</script></head><body><h1>Conseiller clientèle</h1></body></html>';
$draft=importJobHtml($html,'https://example.test/jobs/123');
verify($draft['description'] === readableText($original), 'Full French original retained, including final paragraph');
verify(str_contains($draft['description'],"\n") && str_contains($draft['description'],'Français'), 'Paragraphs and UTF-8 accents retained');
verify(!str_contains($draft['description'],'great opportunity'), 'English metadata never replaces original');
$schema['description']='';
$missing='<html><head><meta name="description" content="English SEO summary"><script type="application/ld+json">'.json_encode($schema).'</script></head><body></body></html>';
try { importJobHtml($missing,'https://example.test/jobs/123'); throw new LogicException('Metadata-only import accepted'); }
catch (RuntimeException $e) { verify(str_contains($e->getMessage(),'Originaltext'), 'Missing original fails closed'); }
$visible=str_replace('</body>', '<div itemprop="description">'.$original.'</div></body>', $missing);
verify(importJobHtml($visible,'https://example.test/jobs/123')['description'] === readableText($original), 'Dedicated visible ad body fallback preserves original');
$jobs=[['url'=>'https://example.test/jobs/123','company'=>'Exemple SA','location'=>'Bienne','title'=>'Sales consultant','description'=>'Advising customers.','match_reason'=>'Relevant experience.','match_percent'=>78,'original_description'=>$draft['description']]];
$translations=[
    'de-CH'=>['Kundenberater','Beratung der Kundschaft.','Passende Berufserfahrung.'],
    'fr-CH'=>['Conseiller clientèle','Conseiller les clients.','Expérience pertinente.'],
    'en-GB'=>['Customer adviser','Advising customers.','Relevant experience.'],
    'pt-BR'=>['Consultor de clientes','Atendimento aos clientes.','Experiência relevante.'],
    'es-MX'=>['Asesor de clientes','Asesorar a los clientes.','Experiencia pertinente.'],
];
foreach ($translations as $locale=>$texts) {
    $row=['id'=>0,'locale'=>$locale,'title'=>$texts[0],'description'=>$texts[1],'match_reason'=>$texts[2],'url'=>'https://wrong.test','match_percent'=>100];
    $result=mergeJobDisplayTranslations($jobs,[$row],$locale);
    verify($result[0]['description'] === $texts[1] && $result[0]['match_reason'] === $texts[2], "$locale: both display fields updated");
    verify($result[0]['url'] === $jobs[0]['url'] && $result[0]['match_percent'] === 78 && $result[0]['original_description'] === $draft['description'], "$locale: originals, source and score untouched");
    verify(localizeJobResults([],1,$result,$locale) === $result, "$locale: valid cached translations require no API call");
}
foreach ([[], [['id'=>1,'locale'=>'de-CH']], [['id'=>0,'locale'=>'en-GB']]] as $bad) {
    try { mergeJobDisplayTranslations($jobs,$bad,'de-CH'); throw new LogicException('Invalid translation accepted'); }
    catch (RuntimeException) { verify(true,'Incomplete, mismatched or wrong-locale translations rejected'); }
}
$_SESSION['locale']='fr-CH';
verify(currentLocale(['preferred_language'=>'en-GB']) === 'fr-CH','Active UI language wins over stale profile default');
verify(jobDisplayText(str_repeat('é',421),420) === str_repeat('é',420),'Character limits do not corrupt UTF-8');
verify(!str_contains($source, '$description = $metaDescription;'),'No metadata-description fallback remains');
// Company address is distinct from the workplace and must not be guessed.
$organization = ['name'=>'Exemple SA','url'=>'https://employer.example','email'=>'mailto:recrutement@example.test','telephone'=>'+41 32 555 01 02','address'=>['streetAddress'=>'Rue Exemple 12','postalCode'=>'2502','addressLocality'=>'Bienne','addressRegion'=>'BE','addressCountry'=>['name'=>'Switzerland']]];
$details=importCompanyDetails(['hiringOrganization'=>$organization,'jobLocation'=>['address'=>['addressLocality'=>'Paris']]]);
verify($details['address_line1'] === 'Rue Exemple 12' && $details['postal_code'] === '2502' && $details['city'] === 'Bienne' && $details['country_code'] === 'CH','Employer address imported, not workplace address');
verify($details['website'] === 'https://employer.example' && $details['email'] === 'recrutement@example.test' && $details['phone'] === '+41 32 555 01 02','Website, email and telephone extracted');
verify(importCompanyDetails(['hiringOrganization'=>['name'=>'Example'],'jobLocation'=>['address'=>['addressLocality'=>'Paris']]]) === [],'Missing employer facts remain missing');
$contact=importJobContact(['applicationContact'=>['name'=>'Anne Dupont','jobTitle'=>'Responsable RH','email'=>'anne@example.test','telephone'=>'+41 32 555 01 03']]);
verify($contact['first_name'] === 'Anne' && $contact['last_name'] === 'Dupont' && $contact['position'] === 'Responsable RH','Named application contact and role extracted');
verify(importJobContact(['applicationContact'=>['name'=>'Privacy support','email'=>'privacy@example.test']]) === [],'Privacy contact not mistaken for recruiter');
