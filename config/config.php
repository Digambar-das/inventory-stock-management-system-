<?php
// Database configuration.
// Local XAMPP defaults are used only when Railway/environment variables are absent.
function envv(string $key, ?string $default = null): ?string {
  $v = getenv($key);
  return ($v === false || $v === '') ? $default : $v;
}

$databaseUrl = envv('MYSQL_URL') ?: envv('DATABASE_URL');
$urlParts = $databaseUrl ? parse_url($databaseUrl) : false;

define('DB_HOST', envv('MYSQLHOST', $urlParts['host'] ?? '127.0.0.1'));
define('DB_PORT', envv('MYSQLPORT', isset($urlParts['port']) ? (string)$urlParts['port'] : '3306'));
define('DB_NAME', envv('MYSQLDATABASE', isset($urlParts['path']) ? ltrim($urlParts['path'], '/') : 'inventory_stock_management'));
define('DB_USER', envv('MYSQLUSER', $urlParts['user'] ?? 'root'));
define('DB_PASS', envv('MYSQLPASSWORD', isset($urlParts['pass']) ? urldecode($urlParts['pass']) : ''));

session_name('inventorypro_session');
session_start();
