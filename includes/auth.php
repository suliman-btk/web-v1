<?php
/**
 * Session bootstrap + authentication / authorisation helpers.
 * Include this near the top of every page.
 */
require_once __DIR__ . '/functions.php';

// Start a session exactly once per request.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** The logged-in user row, or null for guests. */
function current_user(): ?array
{
    static $user = null;
    if ($user !== null) {
        return $user ?: null;
    }
    if (empty($_SESSION['user_id'])) {
        $user = false;
        return null;
    }
    $user = db_one('SELECT * FROM users WHERE user_id = ?', [$_SESSION['user_id']]) ?: false;
    return $user ?: null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && (($_SESSION['role'] ?? '') === 'admin');
}

/**
 * Log a user in: store identity in the session and regenerate the ID
 * to prevent session fixation.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['full_name'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Require any logged-in user; otherwise bounce to login. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

/** Require an admin; customers/guests are blocked. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        set_flash('error', 'You do not have permission to access that page.');
        redirect('index.php');
    }
}
