<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__.'/../public/index.php');
foreach (['regionChoices', 'countryForRegion'] as $name) {
    if (!preg_match('/^function '.$name.'\(.*?(?=^function )/ms', $source, $match)) {
        throw new RuntimeException('Missing function: '.$name);
    }
    eval(trim($match[0]));
}
foreach (['Bern Stadt', 'Region Biel', 'Region Solothurn', 'Seeland', 'Mittelland'] as $region) {
    if (countryForRegion('CH|'.$region) !== ['CH', $region]) {
        throw new RuntimeException('Invalid Swiss region: '.$region);
    }
}
if (countryForRegion('DE|Bern Stadt') !== [null, null]) {
    throw new RuntimeException('Region accepted for wrong country');
}
$regions = regionChoices()['CH'];
if (count($regions) !== count(array_unique($regions))) {
    throw new RuntimeException('Duplicate Swiss region');
}
echo "PASS Swiss region choices and validation\n";
