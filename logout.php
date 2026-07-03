<?php
/**
 * DEV SURVIVOR - Logout
 * Destroi a sessao e volta para a tela inicial.
 */
require_once __DIR__ . '/includes/auth.php';

$_SESSION = [];

// Remove o cookie de sessao do navegador
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

header('Location: ' . BASE_URL . '/index.php');
exit;
