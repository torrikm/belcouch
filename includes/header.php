<?php
// Проверяем, запущена ли сессия
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключение к БД для загрузки навигации
require_once 'db.php';
$db = new Database();

// Загрузка навигации из БД
$nav_items = $db->getAll("SELECT * FROM navigation");
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (isset($_SESSION['user_id'])): ?>
        <meta name="user-logged-in" content="true">
    <?php endif; ?>
    <title><?php echo isset($pageTitle) ? 'BelCouch - ' . $pageTitle : 'BelCouch'; ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/favi.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Island+Moments&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/header-footer.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
    <link rel="stylesheet" href="../assets/css/home.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/review.css">
    <link rel="stylesheet" href="../assets/css/about-us.css">
    <link rel="stylesheet" href="../assets/css/housing-modal.css">
    <link rel="stylesheet" href="../assets/css/proposals.css">
    <link rel="stylesheet" href="../assets/css/favorites.css">
    <link rel="stylesheet" href="../assets/css/404.css">
</head>

<body>
    <header class="site-header">
        <div class="header-container">
            <a href="../index.php" class="logo">
                <img src="../assets/img/icons/logo.svg" alt="BelCouch Logo" class="logo-img">
            </a>

            <div class="mobile-menu-toggle">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>

            <nav class="main-navigation">
                <?php foreach ($nav_items as $item): ?>
                    <?php
                    // Определяем иконку для пункта меню
                    $icon_file = $item['code'];
                    ?>
                    <a href="../<?php echo $item['url']; ?>" class="nav-item">
                        <?php if ($icon_file): ?>
                            <img src="../assets/img/icons/<?php echo $icon_file; ?>.svg" alt="" class="nav-icon">
                        <?php endif; ?>
                        <?php echo $item['title']; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main>