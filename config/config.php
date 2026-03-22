<?php
// Настройки базы данных
define('DB_HOST', 'MySQL-8.4');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'belcouch_db');

// Настройки приложения
define('SITE_NAME', 'BelCouch');
define('SITE_URL', '/');
define('API_URL', '/api');
define('CHAT_WS_PUBLIC_URL', 'ws://127.0.0.1:8081/ws');
define('CHAT_WS_INTERNAL_EMIT_URL', 'http://127.0.0.1:8081/emit');
define('CHAT_WS_SHARED_SECRET', 'belcouch_chat_secret_change_me');
define('ADMIN_SETUP_SECRET', 'belcouch_admin_setup_2026_03_12_change_after_first_use');
define('SUPPORT_EMAIL', 'vikamet004@gmail.com');
define('SUPPORT_FROM_EMAIL', 'vikamet004@gmail.com');
define('SUPPORT_CHAT_ADMIN_EMAIL', 'vikamet004@gmail.com');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'vikamet004@gmail.com');
define('SMTP_PASSWORD', 'jsnioludpmpsqgxg');
define('SMTP_ENCRYPTION', 'ssl');
define('SMTP_AUTH', true);
// define('SITE_URL', 'https://belcouch.by/'); // Раскомментируйте эту строку при переносе на боевой сервер
