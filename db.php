<?php
/**
 * Database Connection & Self-Bootstrapping Helper
 */

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'fmhy_db';
$db_charset = 'utf8mb4';

try {
    // 1. Connect to MySQL server without specifying the database first
    $dsn = "mysql:host=$db_host;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // 2. Ensure database exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Switch to our database
    $pdo->exec("USE `$db_name`");
} catch (PDOException $e) {
    // Custom error page for connection failure
    header('HTTP/1.1 500 Internal Server Error');
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <title>Database Connection Error</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: #1e293b; padding: 2.5rem; border-radius: 1rem; border: 1px solid #334155; max-width: 500px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
            h1 { color: #f43f5e; margin-top: 0; font-size: 1.75rem; }
            p { line-height: 1.6; color: #94a3b8; }
            code { background: #0f172a; padding: 0.2rem 0.4rem; border-radius: 0.25rem; font-family: monospace; color: #e2e8f0; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class='card'>
            <h1>Database Connection Failed</h1>
            <p>Could not connect to the local MySQL server. Please ensure that XAMPP's MySQL/MariaDB service is running on host <code>$db_host</code> and port <code>3306</code>.</p>
            <p>Error details: <code>" . htmlspecialchars($e->getMessage()) . "</code></p>
        </div>
    </body>
    </html>";
    exit;
}
