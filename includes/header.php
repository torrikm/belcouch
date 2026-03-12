<?php
$root_path = isset($root_path) ? $root_path : '';

?>
<!DOCTYPE html>
<html lang="ru">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php if (isset($_SESSION['user_id'])): ?>
		<meta name="user-logged-in" content="true">
		<?php $wsTs = time(); ?>
		<meta name="chat-ws-url" content="<?php echo htmlspecialchars(defined('CHAT_WS_PUBLIC_URL') ? CHAT_WS_PUBLIC_URL : '', ENT_QUOTES, 'UTF-8'); ?>">
		<meta name="chat-ws-user-id" content="<?php echo (int) $_SESSION['user_id']; ?>">
		<meta name="chat-ws-ts" content="<?php echo $wsTs; ?>">
		<meta name="chat-ws-sig" content="<?php echo htmlspecialchars(hash_hmac('sha256', ((int) $_SESSION['user_id']) . '|' . $wsTs, defined('CHAT_WS_SHARED_SECRET') ? CHAT_WS_SHARED_SECRET : 'chat_secret'), ENT_QUOTES, 'UTF-8'); ?>">
	<?php endif; ?>
	<title><?php echo isset($pageTitle) ? 'BelCouch - ' . $pageTitle : 'BelCouch'; ?></title>
	<link rel="icon" type="image/svg+xml" href="<?php echo $root_path; ?>assets/img/favi.png">

	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/style.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/header-footer.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/profile.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/home.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/modal.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/review.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/about-us.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/housing-modal.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/proposals.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/favorites.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/404.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/custom-select.css">
	<link rel="stylesheet" href="<?php echo $root_path; ?>assets/css/verification.css">
	<?php if (isset($additionalCss) && is_array($additionalCss)): ?>
		<?php foreach ($additionalCss as $css): ?>
			<link rel="stylesheet" href="<?php echo $css; ?>">
		<?php endforeach; ?>
	<?php endif; ?>
</head>

<body>
	<header class="site-header">
		<div class="header-container">
			<a href="<?php echo SITE_URL; ?>" class="logo">
				<img src="<?php echo $root_path; ?>assets/img/icons/logo.svg" alt="BelCouch Logo" class="logo-img">
			</a>

			<div class="mobile-menu-toggle">
				<span class="bar"></span>
				<span class="bar"></span>
				<span class="bar"></span>
			</div>

			<nav class="main-navigation">
				<a href="<?php echo $root_path; ?>proposals" class="nav-item">
					Предложения
				</a>
				<a href="<?php echo $root_path; ?>about" class="nav-item">
					О нас
				</a>
				<?php if (isset($_SESSION['user_id'])): ?>
					<a href="<?php echo $root_path; ?>chat" class="nav-item">
						Чаты
					</a>
					<?php if (class_exists('AdminAccess') && AdminAccess::isAdmin()): ?>
						<a href="<?php echo $root_path; ?>admin" class="nav-item">
							Админка
						</a>
					<?php endif; ?>
					<a href="<?php echo $root_path; ?>profile" class="nav-item">
						Профиль
					</a>
				<?php else: ?>
					<a href="#" data-auth-open="login-modal" class="nav-item">
						Войти
					</a>
				<?php endif; ?>
			</nav>
		</div>
	</header>

	<main>