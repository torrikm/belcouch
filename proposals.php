<?php
// Подключение необходимых файлов
require_once 'config/config.php';
require_once 'includes/db.php';

// Запуск сессии
session_start();

// Инициализация базы данных
$db = new Database();

// Получаем список избранных объявлений пользователя
$user_favorites = [];
if (isset($_SESSION['user_id'])) {
    $favorites_sql = "SELECT listing_id FROM favorites WHERE user_id = ?";
    $favorites_stmt = $db->prepareAndExecute($favorites_sql, "i", [$_SESSION['user_id']]);
    $favorites_result = $favorites_stmt->get_result();

    while ($row = $favorites_result->fetch_assoc()) {
        $user_favorites[] = $row['listing_id'];
    }
}

// Получаем параметры фильтрации из GET запроса
$property_type_id = isset($_GET['property_type']) ? (int) $_GET['property_type'] : 0;
$min_guests = isset($_GET['min_guests']) ? (int) $_GET['min_guests'] : 0;
$max_guests = isset($_GET['max_guests']) ? (int) $_GET['max_guests'] : 0;
$stay_duration_id = isset($_GET['stay_duration']) ? (int) $_GET['stay_duration'] : 0;
$region_id = isset($_GET['region']) ? (int) $_GET['region'] : 0;
$city = isset($_GET['city']) ? trim($_GET['city']) : '';
// Проверяем, что amenities и rules являются массивами
$has_amenities = isset($_GET['amenities']) && is_array($_GET['amenities']) ? $_GET['amenities'] : [];
$has_rules = isset($_GET['rules']) && is_array($_GET['rules']) ? $_GET['rules'] : [];

// Текущая страница пагинации
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 12; // Количество объявлений на странице
$offset = ($page - 1) * $per_page;

// Формируем условия для SQL запроса
$conditions = [];
$params = [];
$types = '';

if ($property_type_id > 0) {
    $conditions[] = "l.property_type_id = ?";
    $params[] = $property_type_id;
    $types .= "i";
}

if ($min_guests > 0) {
    $conditions[] = "l.max_guests >= ?";
    $params[] = $min_guests;
    $types .= "i";
}

if ($max_guests > 0) {
    $conditions[] = "l.max_guests <= ?";
    $params[] = $max_guests;
    $types .= "i";
}

if ($stay_duration_id > 0) {
    $conditions[] = "l.stay_duration_id = ?";
    $params[] = $stay_duration_id;
    $types .= "i";
}

if ($region_id > 0) {
    $conditions[] = "l.region_id = ?";
    $params[] = $region_id;
    $types .= "i";
}

if (!empty($city)) {
    $conditions[] = "l.city LIKE ?";
    $params[] = "%$city%";
    $types .= "s";
}

// Формируем WHERE часть запроса
$where_clause = '';
if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// Получаем общее количество объявлений
$count_sql = "SELECT COUNT(*) as total FROM listings l $where_clause";
if (empty($params)) {
    $count_result = $db->query($count_sql);
    $total_listings = $count_result->fetch_assoc()['total'];
} else {
    $count_stmt = $db->prepareAndExecute($count_sql, $types, $params);
    $count_stmt->bind_result($total_listings);
    $count_stmt->fetch();
    $count_stmt->close();
}
$total_pages = ceil($total_listings / $per_page);

// Добавляем LIMIT для пагинации
$limit_clause = "LIMIT $offset, $per_page";

// Получаем список объявлений
$sql = "SELECT l.*, pt.name as property_type_name, r.name as region_name, 
        sd.name as stay_duration_name, u.first_name, u.last_name, u.avg_rating as user_rating, u.is_verify, 
        u.avatar_image
       FROM listings l
       JOIN property_types pt ON l.property_type_id = pt.id
       JOIN regions r ON l.region_id = r.id
       LEFT JOIN stay_durations sd ON l.stay_duration_id = sd.id
       JOIN users u ON l.user_id = u.id
       $where_clause
       ORDER BY l.created_at DESC
       $limit_clause";

$listings = [];

if (!empty($params)) {
    $stmt = $db->prepareAndExecute($sql, $types, $params);
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Получаем главное изображение для каждого объявления
        // Изображения хранятся в папках по ID объявления
        $listing_images_dir = "assets/img/listings/{$row['id']}/";
        $document_root = str_replace("\\", "/", $_SERVER['DOCUMENT_ROOT']);

        // Проверяем наличие основного изображения
        if (file_exists($document_root . "/" . $listing_images_dir . "main.jpg")) {
            $row['main_image'] = $listing_images_dir . "main.jpg";
        } else {
            // Если основного изображения нет, попробуем взять первое изображение из папки
            $image_files = glob($document_root . "/" . $listing_images_dir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);
            if (!empty($image_files)) {
                $row['main_image'] = $listing_images_dir . basename($image_files[0]);
            } else {
                $row['main_image'] = "assets/img/placeholder.jpg";
            }
        }

        $listings[] = $row;
    }
} else {
    // Используем простой запрос без фильтров
    $result = $db->query($sql);

    while ($row = $result->fetch_assoc()) {
        // Изображения хранятся в папках по ID объявления
        $listing_images_dir = "assets/img/listings/{$row['id']}/";
        $document_root = str_replace("\\", "/", $_SERVER['DOCUMENT_ROOT']);

        // Проверяем наличие основного изображения
        if (file_exists($document_root . "/" . $listing_images_dir . "main.jpg")) {
            $row['main_image'] = $listing_images_dir . "main.jpg";
        } else {
            // Если основного изображения нет, попробуем взять первое изображение из папки
            $image_files = glob($document_root . "/" . $listing_images_dir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);
            if (!empty($image_files)) {
                $row['main_image'] = $listing_images_dir . basename($image_files[0]);
            } else {
                $row['main_image'] = "assets/img/placeholder.jpg";
            }
        }

        $listings[] = $row;
    }
}

// Получаем типы жилья для фильтра
$property_types_sql = "SELECT * FROM property_types ORDER BY name";
$property_types_result = $db->query($property_types_sql);
$property_types = [];

while ($row = $property_types_result->fetch_assoc()) {
    $property_types[] = $row;
}

// Получаем регионы для фильтра
$regions_sql = "SELECT * FROM regions ORDER BY name";
$regions_result = $db->query($regions_sql);
$regions = [];

while ($row = $regions_result->fetch_assoc()) {
    $regions[] = $row;
}

// Получаем времена пребывания для фильтра
$stay_durations_sql = "SELECT * FROM stay_durations ORDER BY days";
$stay_durations_result = $db->query($stay_durations_sql);
$stay_durations = [];

while ($row = $stay_durations_result->fetch_assoc()) {
    $stay_durations[] = $row;
}

// Получаем удобства для фильтра
$amenities_sql = "SELECT * FROM amenities ORDER BY name";
$amenities_result = $db->query($amenities_sql);
$amenities = [];

while ($row = $amenities_result->fetch_assoc()) {
    $amenities[] = $row;
}

// Получаем правила для фильтра
$rules_sql = "SELECT * FROM rules ORDER BY name";
$rules_result = $db->query($rules_sql);
$rules = [];

while ($row = $rules_result->fetch_assoc()) {
    $rules[] = $row;
}

// Подключаем шапку сайта
$title = "Предложения - BelCouch";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h1 class="page-title">Предложения жилья</h1>

    <!-- Кнопка для открытия модального окна с фильтрами на мобильных -->
    <button class="filters-mobile-button" id="open-filters-modal"><i class="fas fa-filter"></i> Показать
        фильтры</button>

    <div class="proposals-container">
        <div class="listings-content">
            <?php if (empty($listings)): ?>
                <div class="no-listings">
                    <p>По вашему запросу ничего не найдено. Попробуйте изменить параметры фильтра.</p>
                </div>
            <?php else: ?>
                <div class="total-count">
                    <p>Найдено предложений: <strong><?php echo $total_listings; ?></strong></p>
                </div>

                <div class="listings-list">
                    <?php foreach ($listings as $listing): ?>
                        <div class="listing-card">
                            <div class="listing-image">
                                <a href="profile/housing.php?id=<?php echo $listing['user_id']; ?>">
                                    <img src="<?php echo $listing['main_image']; ?>"
                                        alt="<?php echo htmlspecialchars($listing['title']); ?>">
                                </a>
                                <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $listing['user_id']): ?>
                                    <button
                                        class="favorite-btn <?php echo isset($_SESSION['user_id']) && in_array($listing['id'], $user_favorites ?? []) ? 'active' : ''; ?>"
                                        data-id="<?php echo $listing['id']; ?>"
                                        title="<?php echo isset($_SESSION['user_id']) && in_array($listing['id'], $user_favorites ?? []) ? 'Удалить из избранного' : 'Добавить в избранное'; ?>">
                                        <?php echo isset($_SESSION['user_id']) && in_array($listing['id'], $user_favorites ?? []) ? '♥' : '♡'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="listing-details">
                                <div class="listing-main-info">
                                    <div class="listing-location">
                                        <h3 class="city-name"><?php echo htmlspecialchars($listing['city']); ?></h3>
                                        <p class="region-name" style="text-align: left; padding: 0;">
                                            <?php echo htmlspecialchars($listing['region_name']); ?>
                                        </p>
                                    </div>

                                    <div class="listing-rating">
                                        <div class="rating-value"><?php echo number_format($listing['avg_rating'] ?: 0, 2); ?>
                                        </div>
                                        <div class="rating-stars">
                                            <?php
                                            $rating = $listing['avg_rating'] ?: 0;
                                            for ($i = 1; $i <= 5; $i++):
                                                ?>
                                                <img src="assets/img/icons/<?php echo ($i <= $rating) ? 'star-filled.svg' : 'star-void.svg'; ?>"
                                                    alt="Рейтинг" class="rating-star">
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="listing-specifications">
                                    <div class="specification-item">
                                        <span class="spec-label">Тип:</span>
                                        <span
                                            class="spec-value"><?php echo htmlspecialchars($listing['property_type_name']); ?></span>
                                    </div>
                                    <div class="specification-item">
                                        <span class="spec-label">Количество спальных мест:</span>
                                        <span class="spec-value"><?php echo $listing['max_guests']; ?></span>
                                    </div>
                                    <div class="specification-item">
                                        <span class="spec-label">Время пребывания:</span>
                                        <span
                                            class="spec-value"><?php echo htmlspecialchars($listing['stay_duration_name'] ?: 'Не указано'); ?></span>
                                    </div>
                                    <div class="specification-item">
                                        <span class="spec-label">Примечание:</span>
                                        <span
                                            class="spec-value"><?php echo !empty($listing['notes']) ? htmlspecialchars(mb_substr($listing['notes'], 0, 30)) . (mb_strlen($listing['notes']) > 30 ? '...' : '') : 'нет'; ?></span>
                                    </div>
                                </div>

                                <div class="listing-host">
                                    <div class="host-photo">
                                        <?php if (isset($listing['avatar_image']) && $listing['avatar_image']): ?>
                                            <img src="api/get_avatar.php?id=<?php echo $listing['user_id']; ?>"
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
                                        <a href="profile.php?id=<?php echo $listing['user_id']; ?>" class="host-name">
                                            <?php echo htmlspecialchars($listing['first_name'] . ' ' . $listing['last_name']); ?>
                                        </a>
                                        <?php if ($listing['user_rating'] > 0): ?>
                                            <div class="host-rating"><?php echo number_format($listing['user_rating'], 2); ?>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <img src="assets/img/icons/<?php echo ($i <= $listing['user_rating']) ? 'star-filled.svg' : 'star-void.svg'; ?>"
                                                        alt="Рейтинг" class="rating-star">
                                                <?php endfor; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="messages.php?user=<?php echo $listing['user_id']; ?>"
                                        class="contact-host-btn">Написать</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        // Создаем массив GET-параметров для пагинации
                        $get_params = $_GET;
                        unset($get_params['page']); // Удаляем параметр page
                        $query_string = http_build_query($get_params);
                        $base_url = 'proposals.php?' . ($query_string ? $query_string . '&' : '');

                        // Предыдущая страница
                        if ($page > 1):
                            ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" class="page-link">&laquo; Назад</a>
                        <?php endif; ?>

                        <?php
                        // Рассчитываем диапазон страниц для отображения
                        $range = 2; // Сколько страниц показывать до и после текущей
                        $start_page = max(1, $page - $range);
                        $end_page = min($total_pages, $page + $range);

                        // Показываем первую страницу и многоточие, если нужно
                        if ($start_page > 1):
                            ?>
                            <a href="<?php echo $base_url; ?>page=1" class="page-link">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php
                        // Показываем все страницы в диапазоне
                        for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $i; ?>"
                                class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php
                        // Показываем последнюю страницу и многоточие, если нужно
                        if ($end_page < $total_pages):
                            if ($end_page < $total_pages - 1):
                                ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>"
                                class="page-link"><?php echo $total_pages; ?></a>
                        <?php endif; ?>

                        <?php
                        // Следующая страница
                        if ($page < $total_pages):
                            ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" class="page-link">Вперед &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="filters-sidebar">
            <div class="filters-header">
                <h3>Фильтры</h3>
                <?php if (!empty($_GET)): ?>
                    <a href="proposals.php" class="reset-filters">Сбросить</a>
                <?php endif; ?>
            </div>

            <form id="filters-form" method="post" action="javascript:void(0);">
                <div class="filter-group">
                    <h4>Тип жилья</h4>
                    <select name="property_type" class="form-control">
                        <option value="0">Все типы</option>
                        <?php foreach ($property_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo ($property_type_id == $type['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <h4>Количество мест</h4>
                    <div class="guest-filter">
                        <div class="form-group">
                            <label for="min_guests">от</label>
                            <input type="number" id="min_guests" name="min_guests" min="1" max="20"
                                value="<?php echo $min_guests ?: ''; ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="max_guests">до</label>
                            <input type="number" id="max_guests" name="max_guests" min="1" max="20"
                                value="<?php echo $max_guests ?: ''; ?>" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="filter-group">
                    <h4>Время пребывания</h4>
                    <select name="stay_duration" class="form-control">
                        <option value="0">Все варианты</option>
                        <?php foreach ($stay_durations as $duration): ?>
                            <option value="<?php echo $duration['id']; ?>" <?php echo ($stay_duration_id == $duration['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($duration['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <h4>Регион</h4>
                    <select name="region" class="form-control">
                        <option value="0">Все регионы</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region['id']; ?>" <?php echo ($region_id == $region['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($region['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <h4>Город</h4>
                    <input type="text" name="city" class="form-control" placeholder="Введите название города"
                        value="<?php echo htmlspecialchars($city); ?>">
                </div>

                <div class="filter-group amenities-filter">
                    <h4>Удобства</h4>
                    <div class="filter-checkboxes">
                        <?php foreach ($amenities as $amenity): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="amenity-<?php echo $amenity['id']; ?>" name="amenities[]"
                                    value="<?php echo $amenity['id']; ?>" <?php echo (in_array($amenity['id'], $has_amenities)) ? 'checked' : ''; ?>>
                                <label
                                    for="amenity-<?php echo $amenity['id']; ?>"><?php echo htmlspecialchars($amenity['name']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="filter-group rules-filter">
                    <h4>Правила</h4>
                    <div class="filter-checkboxes">
                        <?php foreach ($rules as $rule): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="rule-<?php echo $rule['id']; ?>" name="rules[]"
                                    value="<?php echo $rule['id']; ?>" <?php echo (in_array($rule['id'], $has_rules)) ? 'checked' : ''; ?>>
                                <label
                                    for="rule-<?php echo $rule['id']; ?>"><?php echo htmlspecialchars($rule['name']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Подключаем JavaScript для фильтрации -->
<script src="assets/js/filter.js"></script>

<!-- Подключаем JavaScript для работы с избранными объявлениями -->
<script src="assets/js/favorites.js"></script>

<!-- Модальное окно с фильтрами -->
<div id="filters-modal" class="filters-modal">
    <div class="filters-modal-content">
        <span class="close-modal">&times;</span>
        <h3>Фильтры</h3>

        <form id="mobile-filters-form" method="get" action="proposals.php">
            <div class="filter-group">
                <h4>Тип жилья</h4>
                <select name="property_type" class="form-control">
                    <option value="0">Все типы</option>
                    <?php foreach ($property_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo ($property_type_id == $type['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <h4>Количество мест</h4>
                <div class="guest-filter">
                    <div class="form-group">
                        <label for="mobile_min_guests">от</label>
                        <input type="number" id="mobile_min_guests" name="min_guests" min="1" max="20"
                            value="<?php echo $min_guests ?: ''; ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="mobile_max_guests">до</label>
                        <input type="number" id="mobile_max_guests" name="max_guests" min="1" max="20"
                            value="<?php echo $max_guests ?: ''; ?>" class="form-control">
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <h4>Время пребывания</h4>
                <select name="stay_duration" class="form-control">
                    <option value="0">Все варианты</option>
                    <?php foreach ($stay_durations as $duration): ?>
                        <option value="<?php echo $duration['id']; ?>" <?php echo ($stay_duration_id == $duration['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($duration['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <h4>Регион</h4>
                <select name="region" class="form-control">
                    <option value="0">Все регионы</option>
                    <?php foreach ($regions as $region_item): ?>
                        <option value="<?php echo $region_item['id']; ?>" <?php echo ($region_id == $region_item['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($region_item['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <h4>Город</h4>
                <input type="text" name="city" class="form-control" placeholder="Введите название города"
                    value="<?php echo htmlspecialchars($city); ?>">
            </div>

            <div class="filter-group">
                <h4>Удобства</h4>
                <div class="checkbox-group">
                    <?php foreach ($amenities as $amenity): ?>
                        <div class="form-check">
                            <input type="checkbox" id="mobile_amenity_<?php echo $amenity['id']; ?>" name="amenities[]"
                                value="<?php echo $amenity['id']; ?>" <?php echo (in_array($amenity['id'], $has_amenities)) ? 'checked' : ''; ?> class="form-check-input">
                            <label for="mobile_amenity_<?php echo $amenity['id']; ?>"
                                class="form-check-label"><?php echo htmlspecialchars($amenity['name']); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="filter-group">
                <h4>Правила</h4>
                <div class="checkbox-group">
                    <?php foreach ($rules as $rule): ?>
                        <div class="form-check">
                            <input type="checkbox" id="mobile_rule_<?php echo $rule['id']; ?>" name="rules[]"
                                value="<?php echo $rule['id']; ?>" <?php echo (in_array($rule['id'], $has_rules)) ? 'checked' : ''; ?> class="form-check-input">
                            <label for="mobile_rule_<?php echo $rule['id']; ?>"
                                class="form-check-label"><?php echo htmlspecialchars($rule['name']); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="filters-modal-footer">
                <a href="proposals.php" class="filter-reset-btn">Сбросить</a>
                <button type="submit" class="filter-apply-btn">Применить</button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript для модального окна с фильтрами -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Получаем элементы модального окна
        var modal = document.getElementById('filters-modal');
        var btn = document.getElementById('open-filters-modal');
        var span = document.querySelector('.close-modal');

        // При клике на кнопку открываем модальное окно
        btn.onclick = function () {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Блокируем прокрутку основной страницы
        }

        // При клике на крестик закрываем модальное окно
        span.onclick = function () {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Возвращаем прокрутку
        }

        // При клике вне модального окна закрываем его
        window.onclick = function (event) {
            if (event.target == modal) {
                modal.style.display = 'none';
                document.body.style.overflow = ''; // Возвращаем прокрутку
            }
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>