<?php
declare(strict_types=1);

// Buffer ALL output so stray PHP warnings/notices never corrupt JSON
ob_start();

// Force errors to log only, never display in API responses
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Global exception handler  always return valid JSON
set_exception_handler(function(\Throwable $e) {
    ob_clean(); // discard any buffered output
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => 'Server error: ' . $e->getMessage(),
        'code'    => 'EXCEPTION'
    ]);
    exit;
});

// Global error handler  catch warnings/notices that would corrupt JSON
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    // Only throw for serious errors
    if ($errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    // Log warnings/notices but continue
    error_log("PHP[$errno] $errstr in $errfile:$errline");
    return true; // suppress default PHP output
});

// Register shutdown to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Fatal server error',
            'code'    => 'FATAL'
        ]);
    }
});

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';