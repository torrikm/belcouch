<?php
/**
 * Страница "Избранное" - часть профиля пользователя
 * Отображает избранные объявления пользователя
 */

session_start();
$page_title = "Избранное";
require_once '../config/config.php';
require_once '../includes/db.php';

// Создаем экземпляр класса для работы с БД
$db = new Database();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Получение ID пользователя из URL или использование ID текущего пользователя
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];

// Получение данных профиля из БД
try {
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_image, u.is_verify, 
            u.registration_date, u.avg_rating, ud.description, ud.education, ud.occupation, 
            ud.interests, ud.gender, ud.birthdate, ud.region_id, r.name as region_name, ud.city, COUNT(ur.id) as rating_count
           FROM users u
           LEFT JOIN user_details ud ON u.id = ud.user_id
           LEFT JOIN regions r ON ud.region_id = r.id
           LEFT JOIN user_ratings ur ON u.id = ur.user_id
           WHERE u.id = ?
           GROUP BY u.id";

    $stmt = $db->prepareAndExecute($sql, "i", [$profile_id]);
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        header('Location: ../index.php');
        exit;
    }

    $user = $result->fetch_assoc();
} catch (Exception $e) {
    echo "Ошибка при получении данных профиля: " . $e->getMessage();
    exit;
}

// Определение, является ли этот профиль профилем текущего пользователя
$isOwnProfile = ($profile_id == $_SESSION['user_id']);

// Звездный рейтинг
$starRating = isset($user['avg_rating']) ? round($user['avg_rating']) : 0;

// Подключение header
require_once '../includes/header.php';

// Примечание: не подключаем компонент шапки здесь, его подключим в HTML структуре

// Получение избранных объявлений пользователя
try {
    $favorites_sql = "SELECT l.*, l.id as listing_id, pt.name as property_type_name, r.name as region_name, 
                      sd.name as stay_duration_name, u.first_name, u.last_name, u.avg_rating as user_rating, u.is_verify, 
                      u.avatar_image
                     FROM favorites f 
                     JOIN listings l ON f.listing_id = l.id 
                     JOIN property_types pt ON l.property_type_id = pt.id 
                     JOIN regions r ON l.region_id = r.id
                     LEFT JOIN stay_durations sd ON l.stay_duration_id = sd.id
                     JOIN users u ON l.user_id = u.id 
                     WHERE f.user_id = ?";
    $favorites_stmt = $db->prepareAndExecute($favorites_sql, "i", [$profile_id]);
    $favorites_result = $favorites_stmt->get_result();
    $has_favorites = $favorites_result->num_rows > 0;
    
    // Преобразуем результат запроса в массив
    $favorites = [];
    if ($has_favorites) {
        while ($row = $favorites_result->fetch_assoc()) {
            // Формируем путь к главному изображению
            $listing_id = $row['listing_id'];
            $main_image = "../assets/img/listings/{$listing_id}/main.jpg";
            $document_root = str_replace("\\", "/", $_SERVER['DOCUMENT_ROOT']) . "/";
            
            // Проверяем существование файла
            if (!file_exists($document_root . $main_image)) {
                $image_files = glob($document_root . "assets/img/listings/{$listing_id}/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
                if (!empty($image_files)) {
                    $main_image = "../assets/img/listings/{$listing_id}/" . basename($image_files[0]);
                } else {
                    $main_image = "../assets/img/no-image.jpg";
                }
            }
            
            $row['main_image'] = $main_image;
            $favorites[] = $row;
        }
    }
} catch (Exception $e) {
    echo "Ошибка при получении избранных объявлений: " . $e->getMessage();
    $has_favorites = false;
    $favorites = [];
}
?>

<div class="container">
    <div class="profile-page">
        <!-- Шапка профиля -->
        <div class="profile-header-container">
            <?php require_once '../includes/profile_header.php'; ?>

            <div class="profile-right">
                <div class="profile-nav">
                    <a href="./about.php<?php echo $profile_id != $_SESSION['user_id'] ? '?id=' . $profile_id : ''; ?>"
                        class="profile-nav-item">Обо мне</a>
                    <a href="./housing.php<?php echo $profile_id != $_SESSION['user_id'] ? '?id=' . $profile_id : ''; ?>"
                        class="profile-nav-item">Жилье</a>
                    <?php if ($isOwnProfile): ?>
                        <a href="./favorites.php" class="profile-nav-item active">Избранное</a>
                    <?php endif; ?>
                </div>

                <div class="profile-block">
                    <?php if (!$has_favorites): ?>
                        <!-- Если избранных объявлений нет -->
                        <div class="no-favorites">
                            <?php if ($isOwnProfile): ?>
                                <h3>У вас пока нет избранных объявлений</h3>
                                <a href="../proposals.php" class="btn btn-primary">Найти жилье</a>
                            <?php else: ?>
                                <h3>У пользователя пока нет избранных объявлений</h3>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Если есть избранные объявления -->
                        <!-- Заголовок избранного с кнопкой очистки -->
                        <div class="favorites-header">
                            <h2><?php echo $isOwnProfile ? 'Мои избранные объявления' : 'Избранные объявления пользователя'; ?>
                            </h2>
                            <?php if ($isOwnProfile): ?>
                                <button id="clear-favorites" class="btn-clear">Очистить список</button>
                            <?php endif; ?>
                        </div>

                        <div class="listings-list">
                            <?php foreach ($favorites as $listing): ?>
                                <div class="listing-card">
                                    <div class="listing-image">
                                        <a href="housing.php?id=<?php echo $listing['user_id']; ?>">
                                            <img src="<?php echo $listing['main_image']; ?>"
                                                alt="<?php echo htmlspecialchars($listing['title']); ?>">
                                        </a>
                                        <button class="favorite-btn active" data-id="<?php echo $listing['listing_id']; ?>"
                                            title="Удалить из избранного">♥</button>
                                    </div>
                                    <div class="listing-details">
                                        <div class="listing-main-info">
                                            <div class="listing-location">
                                                <h3 class="city-name"><?php echo htmlspecialchars($listing['city']); ?></h3>
                                                <p class="region-name"><?php echo htmlspecialchars($listing['region_name']); ?></p>
                                            </div>

                                            <div class="listing-rating">
                                                <div class="rating-value">
                                                    <?php echo number_format($listing['avg_rating'] ?: 0, 2); ?>
                                                </div>
                                                <div class="rating-stars">
                                                    <?php
                                                    $rating = $listing['avg_rating'] ?: 0;
                                                    for ($i = 1; $i <= 5; $i++): ?>
                                                        <img src="../assets/img/icons/<?php echo ($i <= $rating) ? 'star-filled.svg' : 'star-void.svg'; ?>"
                                                             alt="Рейтинг" class="rating-star">
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="listing-specifications">
                                            <div class="specification-item">
                                                <span class="spec-label">Тип:</span>
                                                <span class="spec-value"><?php echo htmlspecialchars($listing['property_type_name']); ?></span>
                                            </div>
                                            <div class="specification-item">
                                                <span class="spec-label">Количество спальных мест:</span>
                                                <span class="spec-value"><?php echo $listing['max_guests']; ?></span>
                                            </div>
                                            <div class="specification-item">
                                                <span class="spec-label">Время пребывания:</span>
                                                <span class="spec-value"><?php echo htmlspecialchars($listing['stay_duration_name'] ?: 'Не указано'); ?></span>
                                            </div>
                                            <div class="specification-item">
                                                <span class="spec-label">Примечание:</span>
                                                <span class="spec-value"><?php echo !empty($listing['notes']) ? htmlspecialchars(mb_substr($listing['notes'], 0, 30)) . (mb_strlen($listing['notes']) > 30 ? '...' : '') : 'нет'; ?></span>
                                            </div>
                                        </div>

                                        <div class="listing-host">
                                            <div class="host-photo">
                                                <?php if (isset($listing['avatar_image']) && $listing['avatar_image']): ?>
                                                    <img src="../api/get_avatar.php?id=<?php echo $listing['user_id']; ?>"
                                                        alt="Фото пользователя" class="host-avatar">
                                                <?php else: ?>
                                                    <div class="host-avatar-placeholder">
                                                        <?php
                                                        $initials = '';
                                                        if (!empty($listing['first_name'])) {
                                                            $initials .= mb_substr($listing['first_name'], 0, 1, 'UTF-8');
                                                        }
                                                        if (!empty($listing['last_name'])) {
                                                            $initials .= mb_substr($listing['last_name'], 0, 1, 'UTF-8');
                                                        }
                                                        echo htmlspecialchars($initials ?: 'U');
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (isset($listing['is_verify']) && $listing['is_verify']): ?>
                                                    <div class="host-verification">✓</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="host-info">
                                                <a href="housing.php?id=<?php echo $listing['user_id']; ?>" class="host-name">
                                                    <?php echo htmlspecialchars($listing['first_name'] . ' ' . $listing['last_name']); ?>
                                                </a>
                                                <?php if ($listing['user_rating'] > 0): ?>
                                                    <div class="host-rating">
                                                        <?php echo number_format($listing['user_rating'], 2); ?>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <img src="../assets/img/icons/<?php echo ($i <= $listing['user_rating']) ? 'star-filled.svg' : 'star-void.svg'; ?>"
                                                                alt="Рейтинг" class="rating-star">
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <a href="../messages.php?user=<?php echo $listing['user_id']; ?>"
                                                class="contact-host-btn">Написать</a>
                                        </div>
                                    </div>
                                    <a href="housing.php?id=<?php echo $listing['user_id']; ?>" class="listing-link"></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div> <!-- Закрытие .profile-content-wrapper -->
    </div> <!-- Закрытие .profile-page -->
</div> <!-- Закрытие .container -->

<!-- Подключение скриптов -->
<!-- Подключаем jQuery -->
<script src="../assets/js/jquery.min.js"></script>

<!-- Подключаем скрипт для работы с избранным -->
<script src="../assets/js/favorites.js"></script>

<?php include '../includes/footer.php'; ?>