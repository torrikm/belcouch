<?php

require_once __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
	$db = new Database();
	$db->query('UPDATE users SET is_online = 0 WHERE id = ' . (int) $_SESSION['user_id']);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(
		session_name(),
		'',
		time() - 42000,
		$params['path'],
		$params['domain'],
		$params['secure'],
		$params['httponly']
	);
}

session_destroy();

header('Location: index.php');
exit;
