<?php
declare(strict_types=1);

// Load only pure helpers, without production bootstrap or credentials.
$source = file_get_contents(__DIR__ . '/../public/index.php');
$wanted = array_flip(['sfApplySql', 'sfOrderSql', 'displayDateTime', 'localeForCountry']);
$tokens = token_get_all($source);
for ($i = 0; $i < count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
    $j = $i + 1;
    while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
    if (!is_array($tokens[$j]) || !isset($wanted[$tokens[$j][1]])) { continue; }
    $name = $tokens[$j][1];
    $code = ''; $depth = 0; $started = false;
    for (; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        $code .= is_array($token) ? $token[1] : $token;
        if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++; $started = true;
        } elseif ($token === '}') {
            $depth--;
            if ($started && $depth === 0) { break; }
        }
    }
    eval($code);
    unset($wanted[$name]);
}
if ($wanted) { throw new RuntimeException('Missing helpers'); }
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
    echo "OK: $message\n";
}
$fields = ['created_at' => ['expr' => 'j.created_at', 'filter_expr' => "CONCAT(DATE_FORMAT(j.created_at, '%d.%m.%Y'), ' ', DATE(j.created_at))"]];
foreach (['03.09.2026', '2026-09-03'] as $filter) {
    $types = ''; $values = [];
    $sql = sfApplySql(['filters' => ['created_at' => $filter]], $fields, $types, $values);
    check(str_contains($sql, 'DATE_FORMAT') && $values === ['%' . $filter . '%'], 'Date filter: ' . $filter);
}
foreach (['asc', 'desc'] as $direction) {
    check(sfOrderSql(['sort' => ['field' => 'created_at', 'dir' => $direction]], $fields, 'created_at') === ' ORDER BY j.created_at ' . strtoupper($direction), 'Chronological sorting: ' . $direction);
}
check(displayDateTime('2026-09-03 15:45:00', ['country_code'=>'CH'], false) === '03.09.2026', 'Date without time');
check(displayDateTime(null, null, false) === '', 'No invented date');
check(str_contains($source, "sfHeader('jobs','created_at'"), 'Date table header');
check(substr_count($source, "displayDateTime(\$job['created_at'], \$currentUser, false)") >= 2, 'Date in table and cards');
check(str_contains($source, "displayDateTime(\$r['created_at'], \$currentUser, false),\$r['title']"), 'Localized date in PDF');
check(str_contains($source, "'filter_expr'=>\"CONCAT(DATE_FORMAT(j.created_at"), 'Date field uses localized filter');
echo "All job date tests passed.\n";
