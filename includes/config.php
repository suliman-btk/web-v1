<?php
/**
 * Global configuration for TechNest.
 *
 * XAMPP (Windows) defaults are host "localhost", user "root", empty password.
 * We use 127.0.0.1 so the same code also works against a TCP MySQL/MariaDB
 * on Linux during development. On XAMPP either host value works fine.
 */

// ---- Database connection settings ----
define('DB_HOST', 'localhost');
define('DB_PORT', '3308');
define('DB_NAME', 'technest');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default root password is empty
define('DB_CHARSET', 'utf8mb4');

// ---- Application settings ----
// BASE_URL is auto-detected from the folder the app runs in, so it works
// whether the project sits at http://localhost/technest/ or http://localhost:8000/.
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
// Strip a trailing "/admin" so admin pages still resolve assets at the app root.
$script_dir = preg_replace('#/admin$#', '', $script_dir);
$base = rtrim($script_dir, '/');
define('BASE_URL', $base === '' ? '/' : $base . '/');

define('SITE_NAME', 'TechNest');
define('SHIPPING_FREE_THRESHOLD', 200.00);  // free shipping at/above this subtotal
define('SHIPPING_FLAT_FEE', 15.00);
define('LOW_STOCK_THRESHOLD', 5);           // admin low-stock alert cutoff
