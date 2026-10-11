<?php
// Start session early for CSRF, auth, etc.
require_once __DIR__ . '/session_init.php';
date_default_timezone_set('Africa/Nairobi');


require_once __DIR__ . '/../vendor/autoload.php';
//ROLE_CONSTANTS
if (!defined('ROLE_ADMIN'))
    define('ROLE_ADMIN', 1);
if (!defined('ROLE_MANAGER'))
    define('ROLE_MANAGER', 2);
if (!defined('ROLE_STAFF'))
    define('ROLE_STAFF', 3);
if (!defined('ROLE_HQ_MANAGER'))
    define('ROLE_HQ_MANAGER', 4);
if (!defined('ROLE_SUPER_ADMIN'))
    define('ROLE_SUPER_ADMIN', 5);
if (!defined('ROLE_SUPER_MANAGER'))
    define('ROLE_SUPER_MANAGER', 6);
if (!defined('HQ_REGION_ID'))
    define('HQ_REGION_ID', 9);
// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// License crypto secrets
define('LICENSE_CRYPTO_KEY', base64_decode(str_replace('base64:', '', $_ENV['LICENSE_CRYPTO_KEY'])));
define('LICENSE_HMAC_SECRET', base64_decode(str_replace('base64:', '', $_ENV['LICENSE_HMAC_SECRET'])));

// Constants
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// -------------------------------
// 🔐 License Crypto Functions
// -------------------------------

function encryptData(string $data): string
{
    $key = LICENSE_CRYPTO_KEY;
    $iv = substr(hash('sha256', $key), 0, 16); // 16-byte IV
    $ciphertext = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    $hmac = hash_hmac('sha256', $ciphertext, LICENSE_HMAC_SECRET);
    return base64_encode($ciphertext . '::' . $hmac);
}

function decryptData(string $encryptedData): string|false
{
    $decoded = base64_decode($encryptedData);
    if (!$decoded || !str_contains($decoded, '::'))
        return false;

    [$ciphertext, $hmac] = explode('::', $decoded, 2);
    if (!hash_equals(hash_hmac('sha256', $ciphertext, LICENSE_HMAC_SECRET), $hmac))
        return false;

    $key = LICENSE_CRYPTO_KEY;
    $iv = substr(hash('sha256', $key), 0, 16);
    return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, 0, $iv);
}

function generateLicenseHMAC(string $licenseKey, string $domain, string $expires): string
{
    $payload = strtoupper(str_replace('-', '', $licenseKey)) . "|$domain|$expires";
    return hash_hmac('sha256', $payload, LICENSE_HMAC_SECRET);
}

// -------------------------------
// 🛡️ CSRF Functions
// -------------------------------

function getCSRFToken(): string
{
    if (!isset($_SESSION['csrf_token']) || tokenExpired()) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) &&
        !tokenExpired();
}

function csrfInput(): string
{
    $token = getCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function tokenExpired(): bool
{
    return isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRY);
}
// -------------------------------
// 💾 Database Connection
// -------------------------------

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db = $_ENV['DB_DATABASE'] ?? 'vehicles';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);
