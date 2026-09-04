<?php

return [
    'app_name' => 'JeMa Jobs',
    'app_url' => 'https://jobs.jema.business',
    'app_version' => '2.1.1',
    'app_key' => 'replace-with-64-random-hex-characters',
    // Nur serverseitig hinterlegen; niemals in Git, Browser-Code oder Logs ausgeben.
    'openai_api_key' => '',
    'openai_model' => 'gpt-5.6-luna',
    // Lokale Kostenschätzung für die Fusszeile; kein OpenAI-Abrechnungssaldo.
    'openai_budget_usd' => 10.00,
    'openai_budget_spent_offset_usd' => 0.00,
    'openai_input_usd_per_million' => 0.20,
    'openai_cached_input_usd_per_million' => 0.02,
    'openai_output_usd_per_million' => 1.20,
    'openai_web_search_usd_per_call' => 0.01,
    'admin_emails' => ['admin@jema.business'],
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'kerubina_JeMaJobs',
    'db_user' => 'kerubina_JeMaJobs',
    'db_password' => 'replace-on-server',
    'mail_from' => 'admin@jobs.jema.business',
    'mail_from_name' => 'JeMa Jobs',
    'smtp_enabled' => false,
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    // Einmalige Betreiber-Konfiguration fuer den OAuth-Client der gesamten Anwendung.
    'google_calendar_client_id' => '',
    'google_calendar_client_secret' => '',
];
