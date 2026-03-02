<?php
/**
 * Страница управления жильем в профиле пользователя
 * Позволяет просматривать, добавлять, редактировать и удалять объявления о жилье
 */

session_start();
$page_title = "Управление жильем";
require_once '../config/config.php';
require_once '../includes/db.php';

// Создаем экземпляр класса для работы с БД
$db = new Database();

// Проверка авторизации
if (!isset($_SESSION['user_id']) && !isset($_GET['id'])) {
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

    // Получение списка областей из БД для модальных окон
    $regions_sql = "SELECT id, name FROM regions ORDER BY name ASC";
    $regions_result = $db->query($regions_sql);
    $regions = [];
    if ($regions_result) {
        while ($row = $regions_result->fetch_assoc()) {
            $regions[] = $row;
        }
    }

} catch (Exception $e) {
    echo "Ошибка при получении данных профиля: " . $e->getMessage();
    exit;
}

// Определение, является ли этот профиль профилем текущего пользователя
if (!isset($_SESSION['user_id'])) {
    $isOwnProfile = false;
} else {
    $isOwnProfile = ($profile_id == $_SESSION['user_id']);
}

// Звездный рейтинг
$starRating = isset($user['avg_rating']) ? round($user['avg_rating']) : 0;

// Подключение header
require_once '../includes/header.php';

// Примечание: не подключаем компонент шапки здесь, его подключим в HTML структуре

// Проверяем, есть ли у пользователя жилье
try {
    $housing_sql = "SELECT l.*, pt.name as property_type_name 
                  FROM listings l 
                  JOIN property_types pt ON l.property_type_id = pt.id 
                  WHERE l.user_id = ?";
    $housing_stmt = $db->prepareAndExecute($housing_sql, "i", [$profile_id]);
    $housing_result = $housing_stmt->get_result();
    $has_housing = $housing_result->num_rows > 0;
    $listing = null;

    // Получаем данные объявления
    if ($has_housing) {
        $listing = $housing_result->fetch_assoc();
        $listing_id = $listing['id'];

        // Получаем все изображения для объявления
        $images = [];
        $images_dir = "../assets/img/listings/{$listing_id}/";
        $main_image = '';

        // Проверяем, существует ли директория с изображениями
        if (is_dir($images_dir)) {
            // Получаем список файлов в директории
            $files = glob($images_dir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);

            if (!empty($files)) {
                foreach ($files as $file) {
                    $images[] = str_replace("../", "", $file);
                }
                // Первое изображение будет главным по умолчанию
                $main_image = $images[0];
            }
        }
    }

    // Получаем типы жилья для формы добавления
    $property_types_sql = "SELECT * FROM property_types ORDER BY name";
    $property_types_result = $db->query($property_types_sql);
    $property_types = [];
    while ($row = $property_types_result->fetch_assoc()) {
        $property_types[] = $row;
    }

    // Получаем правила и удобства для объявления (если оно есть)
    $rules = [];
    $amenities = [];

    if ($has_housing && $listing) {
        // Получаем время пребывания
        $duration_sql = "SELECT name FROM stay_durations WHERE id = ?";
        $duration_stmt = $db->prepareAndExecute($duration_sql, "i", [$listing['stay_duration_id']]);
        $duration_result = $duration_stmt->get_result();
        $duration_name = $duration_result->num_rows > 0 ? $duration_result->fetch_assoc()['name'] : 'Не указано';

        // Получаем правила
        $rules_sql = "SELECT r.name, r.icon FROM listing_rules lr 
                     JOIN rules r ON lr.rule_id = r.id 
                     WHERE lr.listing_id = ?";
        $rules_stmt = $db->prepareAndExecute($rules_sql, "i", [$listing_id]);
        $rules_result = $rules_stmt->get_result();

        while ($rule = $rules_result->fetch_assoc()) {
            $rules[] = $rule;
        }

        // Получаем удобства
        $amenities_sql = "SELECT a.name, a.icon FROM listing_amenities la 
                        JOIN amenities a ON la.amenity_id = a.id 
                        WHERE la.listing_id = ?";
        $amenities_stmt = $db->prepareAndExecute($amenities_sql, "i", [$listing_id]);
        $amenities_result = $amenities_stmt->get_result();

        while ($amenity = $amenities_result->fetch_assoc()) {
            $amenities[] = $amenity;
        }
    }

} catch (Exception $e) {
    echo "Ошибка при получении данных о жилье: " . $e->getMessage();
    $has_housing = false;
}
?>

<div class="container">
    <div class="profile-page">
        <!-- Шапка профиля -->
        <div class="profile-header-container">
            <?php require_once '../includes/profile_header.php'; ?>

            <div class="profile-right">
                <div class="profile-nav">
                    <a href="./about.php<?php echo isset($_GET['id']) ? '?id=' . $profile_id : ''; ?>"
                        class="profile-nav-item">Обо мне</a>
                    <a href="./housing.php<?php echo isset($_GET['id']) ? '?id=' . $profile_id : ''; ?>"
                        class="profile-nav-item active">Жилье</a>
                    <?php if ($isOwnProfile): ?>
                        <a href="./favorites.php" class="profile-nav-item">Избранное</a>
                    <?php endif; ?>
                </div>

                <div class="profile-block"
                    style="flex-direction: column; align-items: center; justify-content: center;">
                    <?php if (!$has_housing && $isOwnProfile): ?>
                        <!-- Если жилья нет, и это профиль текущего пользователя -->
                        <div class="no-listings">
                            <h3>У Вас пока нет предложений жилья!</h3>
                            <button id="add-housing-btn" class="btn btn-primary">Добавить</button>
                        </div>
                    <?php elseif (!$has_housing && !$isOwnProfile): ?>
                        <!-- Если жилья нет, и это чужой профиль -->
                        <div class="no-listings">
                            <h3>У пользователя пока нет объявлений о жилье</h3>
                        </div>
                    <?php else: ?>
                        <!-- Если есть жилье -->
                        <?php if ($isOwnProfile): ?>
                            <div class="listings-header">
                                <?php
                                // Подсчитываем количество объявлений пользователя
                                $count_sql = "SELECT COUNT(*) as count FROM listings WHERE user_id = ?";
                                $count_stmt = $db->prepareAndExecute($count_sql, "i", [$_SESSION['user_id']]);
                                $count_result = $count_stmt->get_result();
                                $listing_count = $count_result->fetch_assoc()['count'];

                                // Показываем кнопку только если у пользователя нет жилья или только одно объявление
                                if ($listing_count < 1):
                                    ?>
                                    <button id="add-housing-btn" class="btn btn-primary">Добавить объявление</button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <h2 style="margin-bottom: 5px;">Объявление пользователя</h2>
                        <?php endif; ?>

                        <?php if ($has_housing && $listing): ?>
                            <div class="listing-container">
                                <div class="listing-content-wrapper">
                                    <div class="gallery-container">
                                        <div class="main-image-container">
                                            <?php if (!empty($main_image)): ?>
                                                <img id="main-gallery-image" src="../<?php echo $main_image; ?>"
                                                    alt="<?php echo htmlspecialchars($listing['title'] ?? 'Жилье'); ?>">
                                                <button class="gallery-nav prev">❮</button>
                                                <button class="gallery-nav next">❯</button>
                                            <?php else: ?>
                                                <div class="no-image">Нет фото</div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($images)): ?>
                                            <div class="thumbnails-container">
                                                <?php foreach ($images as $index => $image): ?>
                                                    <div class="thumbnail<?php echo ($index === 0) ? ' active' : ''; ?>"
                                                        data-index="<?php echo $index; ?>" data-src="../<?php echo $image; ?>">
                                                        <img src="../<?php echo $image; ?>" alt="Миниатюра <?php echo $index + 1; ?>">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="listing-info">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <h3 class="listing-title">
                                                <?php echo htmlspecialchars($listing['title'] ?? 'Жилье для ' . $listing['max_guests'] . ' человек'); ?>
                                            </h3>
                                            <?php if (!$isOwnProfile): ?>
                                                <?php
                                                // Получаем список избранного пользователя, если он авторизован
                                                $user_favorites = [];
                                                if (isset($_SESSION['user_id'])) {
                                                    $favorites_sql = "SELECT listing_id FROM favorites WHERE user_id = ?";
                                                    $favorites_stmt = $db->prepareAndExecute($favorites_sql, "i", [$_SESSION['user_id']]);
                                                    $favorites_result = $favorites_stmt->get_result();

                                                    while ($fav = $favorites_result->fetch_assoc()) {
                                                        $user_favorites[] = $fav['listing_id'];
                                                    }
                                                }

                                                $is_favorite = in_array($listing['id'], $user_favorites);
                                                ?>
                                                <button class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>"
                                                    data-id="<?php echo $listing['id']; ?>"
                                                    title="<?php echo $is_favorite ? 'Удалить из избранного' : 'Добавить в избранное'; ?>">
                                                    <?php echo $is_favorite ? '♥' : '♡'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <div class="listing-details" style="padding: 0;">
                                            <div class="detail-item">
                                                <strong>Тип:</strong>
                                                <?php echo htmlspecialchars($listing['property_type_name']); ?>
                                            </div>

                                            <div class="detail-item">
                                                <strong>Количество спальных мест:</strong> <?php echo $listing['max_guests']; ?>
                                            </div>

                                            <div class="detail-item" style="flex-direction: column; gap: 0;">
                                                <strong>Время пребывания:</strong>
                                                <?php echo htmlspecialchars($duration_name); ?>
                                            </div>

                                            <div class="detail-item">
                                                <strong>Примечание:</strong>
                                                <?php if (!empty($listing['notes'])): ?>
                                                    <?php echo htmlspecialchars($listing['notes']); ?>
                                                <?php else: ?>
                                                    нет
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if ($isOwnProfile): ?>
                                            <div class="listing-actions">
                                                <button class="btn-edit-listing"
                                                    data-id="<?php echo $listing['id']; ?>">Редактировать</button>
                                                <button class="btn-delete-listing"
                                                    data-id="<?php echo $listing['id']; ?>">Удалить</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="details">
        <div class="rules-section">
            <h3>Правила</h3>
            <div class="rules-list">
                <?php if (!empty($rules)): ?>
                    <?php foreach ($rules as $rule): ?>
                        <div class="rule-item">
                            <img src="../assets/img/icons/<?php echo $rule['icon']; ?>" alt="<?php echo $rule['name']; ?>">
                            <span><?php echo htmlspecialchars($rule['name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">Правила не указаны</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="amenities-section">
            <h3>Основные удобства</h3>
            <div class="amenities-list">
                <?php if (!empty($amenities)): ?>
                    <?php foreach ($amenities as $amenity): ?>
                        <div class="amenity-item">
                            <img src="../assets/img/icons/<?php echo $amenity['icon']; ?>"
                                alt="<?php echo $amenity['name']; ?>">
                            <span><?php echo htmlspecialchars($amenity['name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items">Удобства не указаны</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Блок с отзывами -->
    <?php if ($has_housing && $listing): ?>
        <div class="listing-reviews-section">
            <?php
            // Получаем отзывы о жилье
            $reviews_sql = "SELECT lr.*, u.first_name, u.last_name, u.avatar_image
                       FROM listing_ratings lr
                       LEFT JOIN users u ON lr.user_id = u.id
                       WHERE lr.listing_id = ?
                       ORDER BY lr.created_at DESC
                       LIMIT 5";
            $reviews_stmt = $db->prepareAndExecute($reviews_sql, "i", [$listing_id]);
            $reviews_result = $reviews_stmt->get_result();
            $has_reviews = $reviews_result->num_rows > 0;

            // Проверяем, может ли текущий пользователь оставить отзыв
            $can_review = false;
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['user_id']) {
                // Проверяем, не оставлял ли уже пользователь отзыв
                $check_sql = "SELECT id FROM listing_ratings WHERE listing_id = ? AND user_id = ?";
                $check_stmt = $db->prepareAndExecute($check_sql, "ii", [$listing_id, $_SESSION['user_id']]);
                $check_result = $check_stmt->get_result();

                $can_review = ($check_result->num_rows === 0);
            }
            ?>

            <!-- Список отзывов -->
            <?php if ($has_reviews): ?>
                <h3 class="reviews-title">Отзывы</h3>
                <div class="listing-reviews-list">
                    <?php while ($review = $reviews_result->fetch_assoc()): ?>
                        <div class="review-item">
                            <div class="review-item-header">
                                <div class="reviewer-info">
                                    <?php if ($review['avatar_image']): ?>
                                        <img src="../api/get_avatar.php?id=<?php echo $review['user_id']; ?>" alt="Аватар"
                                            class="reviewer-avatar">
                                    <?php else: ?>
                                        <div class="reviewer-avatar reviewer-avatar-placeholder">
                                            <?php echo mb_substr($review['first_name'], 0, 1) . mb_substr($review['last_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="reviewer-details">
                                        <div class="reviewer-name">
                                            <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                        </div>
                                        <div class="review-date"><?php echo date('d.m.Y', strtotime($review['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <img src="../assets/img/icons/<?php echo ($i <= $review['rating']) ? 'star-filled.svg' : 'star-void.svg'; ?>"
                                            alt="Звезда" class="review-star-icon">
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="review-content">
                                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Форма для отправки отзыва -->
            <?php if ($can_review): ?>
                <div class="listing-review-block">
                    <div class="review-header">
                        <h3>Написать отзыв</h3>
                        <div class="review-stars">
                            <!-- Звёзды -->
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star" data-value="<?php echo $i; ?>">
                                    <img src="../assets/img/icons/star-void.svg" alt="Звезда" class="star-icon">
                                </span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <form id="listing-review-form" method="post">
                        <input type="hidden" name="listing_id" value="<?php echo (int) $listing_id; ?>">
                        <input type="hidden" name="rating" value="0">
                        <textarea name="comment" rows="5" class="review-textarea"
                            placeholder="Напишите свои впечатления об этом жилье" required></textarea>
                        <button type="submit" class="btn btn-primary">Отправить</button>
                    </form>
                </div>
            <?php elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['user_id']): ?>
                <div class="listing-review-info">
                    <p>Вы уже оставили отзыв на это жилье.</p>
                </div>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <div class="listing-review-info">
                    <p>Чтобы оставить отзыв, <a href="../login.php">войдите</a> в систему.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<!-- Модальное окно для добавления/редактирования объявления -->
<?php if ($isOwnProfile): ?>
    <div id="housing-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 id="housing-modal-title" class="modal-title">Добавить объявление</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="housing-form" method="post" enctype="multipart/form-data" action="../api/add_listing.php">
                    <input type="hidden" id="listing_id" name="listing_id" value="">

                    <div class="form-group">
                        <label for="property_type_id">Тип жилья</label>
                        <select id="property_type_id" name="property_type_id" class="form-control" required>
                            <option value="">Выберите тип жилья</option>
                            <?php foreach ($property_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="beds_count">Количество спальных мест</label>
                        <input type="number" id="beds_count" name="beds_count" class="form-control" min="1" value="1"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="listing_city">Город</label>
                        <input type="text" id="listing_city" name="listing_city" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="listing_region_id">Регион</label>
                        <select id="listing_region_id" name="listing_region_id" class="form-control" required>
                            <option value="">Выберите регион</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="title">Название объявления</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="stay_duration_id">Время пребывания</label>
                        <select id="stay_duration_id" name="stay_duration_id" class="form-control" required>
                            <option value="">Выберите время пребывания</option>
                            <?php
                            $stay_durations_sql = "SELECT * FROM stay_durations ORDER BY days ASC";
                            $stay_durations_result = $db->query($stay_durations_sql);
                            while ($duration = $stay_durations_result->fetch_assoc()):
                                ?>
                                <option value="<?php echo $duration['id']; ?>">
                                    <?php echo htmlspecialchars($duration['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Примечания</label>
                        <textarea id="notes" name="notes" class="form-control" rows="4"></textarea>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="housing_photos">Фотографии жилья</label>
                        <div class="photo-upload-container">
                            <label for="housing_photos" class="photo-upload-btn">
                                <span>Загрузить фото</span>
                            </label>
                            <input type="file" id="housing_photos" name="housing_photos[]" accept="image/*" multiple
                                style="display:none;">
                        </div>
                        <div id="photo-previews" class="photo-previews"></div>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="rules" class="rules-title">Правила проживания</label>
                        <div class="select-with-button">
                            <select id="rules" name="rules_select" class="form-control">
                                <option value="">Выберите правило</option>
                                <?php
                                $rules_sql = "SELECT * FROM rules ORDER BY name";
                                $rules_result = $db->query($rules_sql);
                                while ($rule = $rules_result->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $rule['id']; ?>"><?php echo htmlspecialchars($rule['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="btn-add-item" data-target="rules"
                                aria-label="Добавить правило">+</button>
                        </div>
                        <div class="selected-items-container" id="selected-rules"></div>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="amenities" class="amenities-title">Основные удобства</label>
                        <div class="select-with-button">
                            <select id="amenities" class="form-control">
                                <option value="">Выберите удобство</option>
                                <?php
                                $amenities_sql = "SELECT * FROM amenities ORDER BY name";
                                $amenities_result = $db->query($amenities_sql);
                                while ($amenity = $amenities_result->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $amenity['id']; ?>">
                                        <?php echo htmlspecialchars($amenity['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="btn-add-item" data-target="amenities"
                                aria-label="Добавить удобство">+</button>
                        </div>
                        <div class="selected-items-container" id="selected-amenities"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="housing-form" class="btn-save">Сохранить</button>
                <button type="button" class="btn-cancel">Отмена</button>
            </div>
        </div>
    </div>
<?php endif; ?>
<link rel="stylesheet" href="../assets/css/modal.css">
<link rel="stylesheet" href="../assets/css/housing-modal.css">
<link rel="stylesheet" href="../assets/css/housing.css">
<link rel="stylesheet" href="../assets/css/reviews.css">

<script>
    // Скрипт для галереи изображений
    document.addEventListener('DOMContentLoaded', function () {
        // Получаем элементы галереи
        const mainImage = document.getElementById('main-gallery-image');
        const thumbnails = document.querySelectorAll('.thumbnail');
        const prevButton = document.querySelector('.gallery-nav.prev');
        const nextButton = document.querySelector('.gallery-nav.next');

        if (!mainImage) return;

        let currentIndex = 0;
        const maxIndex = thumbnails.length - 1;

        // Функция для обновления главного изображения
        function updateMainImage(index) {
            if (index < 0) index = maxIndex;
            if (index > maxIndex) index = 0;

            currentIndex = index;

            const src = thumbnails[index].getAttribute('data-src');
            mainImage.src = src;

            // Обновляем активную миниатюру
            thumbnails.forEach(thumb => thumb.classList.remove('active'));
            thumbnails[index].classList.add('active');
        }

        // Обработчики для кнопок навигации
        if (prevButton) {
            prevButton.addEventListener('click', function () {
                updateMainImage(currentIndex - 1);
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                updateMainImage(currentIndex + 1);
            });
        }

        // Обработчики для миниатюр
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function () {
                const index = parseInt(this.getAttribute('data-index'));
                updateMainImage(index);
            });
        });
    });
</script>

<!-- Подключение скриптов -->
<script src="../assets/js/housing_fixed.js"></script>
<script src="../assets/js/listing_review.js"></script>

<?php include '../includes/footer.php'; ?>

<!-- Подключаем JavaScript для отзывов -->
<script src="../assets/js/review.js"></script>

<!-- Подключаем JavaScript для работы с избранным -->
<script src="../assets/js/favorites.js"></script>