<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
foreach ([
    'function openAiConnectionCheck(array $config, int $userId): array',
    "'model'=>(string)(\$config['openai_model'] ?? 'gpt-5.6-luna')",
    "'store'=>false",
    "'max_output_tokens'=>16",
    'if (userId() !== realUserId() || !isAdmin($db, userId(), $config))',
    "redirect('/?page=admin_ai')",
    "\$page === 'admin_ai'",
] as $expected) {
    if (!str_contains($source, $expected)) {
        throw new RuntimeException('Missing OpenAI connection safeguard: ' . $expected);
    }
}
if (str_contains($source, "flash('OpenAI-Verbindung erfolgreich: ' . \$apiKey")) {
    throw new RuntimeException('The API key must never be shown in a flash message.');
}
echo "PASS OpenAI connection is server-side, bounded and admin-only\n";
