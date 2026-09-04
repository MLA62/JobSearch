<?php

return [
    'app_name' => 'JeMa Jobs',
    'app_url' => 'https://jobs.jema.business',
    'app_version' => '2.0.0',
    'app_key' => 'replace-with-64-random-hex-characters',
    // Nur serverseitig hinterlegen; niemals in Git, Browser-Code oder Logs ausgeben.
    'openai_api_key' => '',
    'openai_model' => 'gpt-5.6-luna',
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
