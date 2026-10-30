<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();

auditLog('logout', $_SESSION['user_id'] ?? null, 'Logout berhasil.');
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

setFlash('info', 'Anda telah berhasil keluar.');
redirect('auth/login.php');
