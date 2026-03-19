<?php
declare(strict_types=1);

//  Database 
define('DB_HOST',    'localhost');
define('DB_NAME',    'your_db_name');
define('DB_USER',    'your_db_user');
define('DB_PASS',    'your_db_password');
define('DB_CHARSET', 'utf8mb4');

//  App 
define('APP_NAME',  'GSSC-science official');
define('APP_URL',   'https://yourdomain.com');
define('APP_ENV',   'development');   // change to 'production' on live

//  Session 
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

//  Uploads 
define('UPLOAD_MAX_SIZE',   10 * 1024 * 1024); // 10 MB
define('ALLOWED_FILE_TYPES', ['pdf','doc','docx','ppt','pptx','jpg','jpeg','png','zip']);

//  Email 
define('ADMIN_EMAIL', 'admin@yourdomain.com');
define('MAIL_FROM',   'noreply@yourdomain.com');

//  Error display 
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
    error_reporting(E_ALL);
}
