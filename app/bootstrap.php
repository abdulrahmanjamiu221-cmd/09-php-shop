<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$realProjectRoot = realpath($projectRoot);
$basePath = '';
if ($documentRoot !== false && $realProjectRoot !== false) {
    $rootPrefix = rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with(strtolower($realProjectRoot . DIRECTORY_SEPARATOR), strtolower($rootPrefix))) {
        $basePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($realProjectRoot, strlen($rootPrefix)));
    }
}
define('BASE_URL', $basePath === '' ? '' : '/' . trim($basePath, '/'));

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL === '' ? '/' : BASE_URL . '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$host = getenv('SHOP_DB_HOST') ?: '127.0.0.1';
$database = getenv('SHOP_DB_NAME') ?: 'swd_shop';
$username = getenv('SHOP_DB_USER') ?: 'root';
$password = getenv('SHOP_DB_PASSWORD') ?: '';
$dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Shop database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The shop database is unavailable. Check the connection settings and import database/schema.sql.');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/admin.php';
