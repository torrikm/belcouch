<?php
/**
 * API для получения отфильтрованных объявлений без перезагрузки страницы
 */

// Запуск сессии для доступа к данным пользователя
session_start();

// Подключение необходимых файлов
require_once '../config/config.php';
require_once '../includes/db.php';

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

// Получаем параметры фильтрации из POST запроса
$property_type_id = isset($_POST['property_type']) ? (int) $_POST['property_type'] : 0;
$min_guests = isset($_POST['min_guests']) ? (int) $_POST['min_guests'] : 0;
$max_guests = isset($_POST['max_guests']) ? (int) $_POST['max_guests'] : 0;
$stay_duration_id = isset($_POST['stay_duration']) ? (int) $_POST['stay_duration'] : 0;
$region_id = isset($_POST['region']) ? (int) $_POST['region'] : 0;
$city = isset($_POST['city']) ? trim($_POST['city']) : '';
$has_amenities = isset($_POST['amenities']) ? $_POST['amenities'] : [];
$has_rules = isset($_POST['rules']) ? $_POST['rules'] : [];

// Текущая страница пагинации
$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
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

// Добавляем фильтрацию по удобствам
if (!empty($has_amenities) && is_array($has_amenities)) {
    $amenity_conditions = [];
    foreach ($has_amenities as $amenity_id) {
        $amenity_id = (int) $amenity_id;
        if ($amenity_id > 0) {
            $amenity_conditions[] = "EXISTS (
                SELECT 1 FROM listing_amenities la 
                WHERE la.listing_id = l.id AND la.amenity_id = ?
            )";
            $params[] = $amenity_id;
            $types .= "i";
        }
    }
    if (!empty($amenity_conditions)) {
        $conditions[] = "(" . implode(" AND ", $amenity_conditions) . ")";
    }
}

// Добавляем фильтрацию по правилам
if (!empty($has_rules) && is_array($has_rules)) {
    $rule_conditions = [];
    foreach ($has_rules as $rule_id) {
        $rule_id = (int) $rule_id;
        if ($rule_id > 0) {
            $rule_conditions[] = "EXISTS (
                SELECT 1 FROM listing_rules lr 
                WHERE lr.listing_id = l.id AND lr.rule_id = ?
            )";
            $params[] = $rule_id;
            $types .= "i";
        }
    }
    if (!empty($rule_conditions)) {
        $conditions[] = "(" . implode(" AND ", $rule_conditions) . ")";
    }
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
    $count_result = $count_stmt->get_result();
    $total_listings = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
}
$total_pages = ceil($total_listings / $per_page);

// Добавляем LIMIT для пагинации
$limit_clause = "LIMIT $offset, $per_page";

// Получаем список объявлений
$sql = "SELECT l.*, pt.name as property_type_name, r.name as region_name, 
        sd.name as stay_duration_name, u.first_name, u.last_name, u.avg_rating as user_rating, u.avatar_image, u.is_verify
       FROM listings l
       JOIN property_types pt ON l.property_type_id = pt.id
       JOIN regions r ON l.region_id = r.id
       LEFT JOIN stay_durations sd ON l.stay_duration_id = sd.id
       JOIN users u ON l.user_id = u.id
       $where_clause
       ORDER BY l.created_at DESC
       $limit_clause";

$listings = [];

if (empty($params)) {
    $result = $db->query($sql);
} else {
    $stmt = $db->prepareAndExecute($sql, $types, $params);
    $result = $stmt->get_result();
}

// Обрабатываем результаты запроса
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

// Подготавливаем HTML для списка объявлений
$html = '';

if (empty($listings)) {
    $html .= '<div class="no-listings">
                <p>По вашему запросу ничего не найдено. Попробуйте изменить параметры фильтра.</p>
              </div>';
} else {
    // Информация о количестве найденных объявлений
    $html .= '<div class="total-count">
                <p>Найдено предложений: <strong>' . $total_listings . '</strong></p>
              </div>';

    // Начало списка объявлений
    $html .= '<div class="listings-list">';

    // Генерируем HTML для каждого объявления
    foreach ($listings as $listing) {
        $html .= '<div class="listing-card">
                    <div class="listing-image">
                        <a href="profile/housing.php?id=' . $listing['user_id'] . '">
                            <img src="' . $listing['main_image'] . '" alt="' . htmlspecialchars($listing['title']) . '">
                        </a>';

        // Кнопка избранного (только для не своих объявлений)
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $listing['user_id']) {
            // Проверяем, есть ли объявление в избранном
            $is_favorite = isset($_SESSION['user_id']) && in_array($listing['id'], $user_favorites);
            $heart_icon = $is_favorite ? '♥' : '♡'; // Заполненное или пустое сердце
            $favorite_title = $is_favorite ? 'Удалить из избранного' : 'Добавить в избранное';
            $active_class = $is_favorite ? ' active' : '';

            $html .= '<button class="favorite-btn' . $active_class . '" data-id="' . $listing['id'] . '" 
                        title="' . $favorite_title . '">' . $heart_icon . '</button>';
        }

        $html .= '</div>
                <div class="listing-details">
                    <div class="listing-main-info">
                        <div class="listing-location">
                            <h3 class="city-name">' . htmlspecialchars($listing['city']) . '</h3>
                            <p class="region-name">' . htmlspecialchars($listing['region_name']) . '</p>
                        </div>
                        
                        <div class="listing-rating">
                            <div class="rating-value">' . number_format($listing['avg_rating'] ?: 0, 2) . '</div>
                            <div class="rating-stars">';

        // Звезды рейтинга
        $rating = $listing['avg_rating'] ?: 0;
        for ($i = 1; $i <= 5; $i++) {
            $star_type = ($i <= $rating) ? 'star-filled.svg' : 'star-void.svg';
            $html .= '<img src="assets/img/icons/' . $star_type . '" alt="Рейтинг" class="rating-star">';
        }

        $html .= '</div>
                        </div>
                    </div>
                    
                    <div class="listing-specifications">
                        <div class="specification-item">
                            <span class="spec-label">Тип:</span>
                            <span class="spec-value">' . htmlspecialchars($listing['property_type_name']) . '</span>
                        </div>
                        <div class="specification-item">
                            <span class="spec-label">Количество спальных мест:</span>
                            <span class="spec-value">' . $listing['max_guests'] . '</span>
                        </div>
                        <div class="specification-item">
                            <span class="spec-label">Время пребывания:</span>
                            <span class="spec-value">' . htmlspecialchars($listing['stay_duration_name'] ?: 'Не указано') . '</span>
                        </div>
                        <div class="specification-item">
                            <span class="spec-label">Примечание:</span>
                            <span class="spec-value">';

        // Примечание (обрезаем если слишком длинное)
        if (!empty($listing['notes'])) {
            $html .= htmlspecialchars(mb_substr($listing['notes'], 0, 30)) . (mb_strlen($listing['notes']) > 30 ? '...' : '');
        } else {
            $html .= 'нет';
        }

        $html .= '</span>
                        </div>
                    </div>
                    
                    <div class="listing-host">
                        <div class="host-photo">';
        if (isset($listing['avatar_image']) && $listing['avatar_image']) {
            $html .= '<img src="api/get_avatar.php?id=' . $listing['user_id'] . '" alt="Фото пользователя" class="host-avatar">';
        } else {
            $initials = '';
            if (!empty($listing['first_name'])) {
                $initials .= mb_substr($listing['first_name'], 0, 1, 'UTF-8');
            }
            if (!empty($listing['last_name'])) {
                $initials .= mb_substr($listing['last_name'], 0, 1, 'UTF-8');
            }
            $html .= '<div class="host-avatar-placeholder">' . htmlspecialchars($initials ?: 'U') . '</div>';
        }

        // Значок верификации для верифицированных пользователей
        if ($listing['is_verify'] == 1) {
            $html .= '<div class="host-verification">✓</div>';
        }

        $html .= '</div>
                        <div class="host-info">
                            <a href="profile.php?id=' . $listing['user_id'] . '" class="host-name">
                                ' . htmlspecialchars($listing['first_name'] . ' ' . $listing['last_name']) . '
                            </a>';

        // Рейтинг пользователя
        if ($listing['user_rating'] > 0) {
            $html .= '<div class="host-rating">' . number_format($listing['user_rating'], 2) . ' ';

            for ($i = 1; $i <= 5; $i++) {
                $star_type = ($i <= $listing['user_rating']) ? 'star-filled.svg' : 'star-void.svg';
                $html .= '<img src="assets/img/icons/' . $star_type . '" alt="Рейтинг" class="rating-star">';
            }

            $html .= '</div>';
        }

        $html .= '</div>
                        <a href="messages.php?user=' . $listing['user_id'] . '" class="contact-host-btn">Написать</a>
                    </div>
                </div>
            </div>';
    }

    // Закрываем список объявлений
    $html .= '</div>';

    // Добавляем пагинацию
    if ($total_pages > 1) {
        $html .= '<div class="pagination">';

        // Кнопка "Назад"
        if ($page > 1) {
            $html .= '<button class="pagination-btn prev" data-page="' . ($page - 1) . '">Назад</button>';
        }

        // Номера страниц
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);

        for ($i = $start_page; $i <= $end_page; $i++) {
            $active_class = ($i == $page) ? 'active' : '';
            $html .= '<button class="pagination-btn page ' . $active_class . '" data-page="' . $i . '">' . $i . '</button>';
        }

        // Кнопка "Вперед"
        if ($page < $total_pages) {
            $html .= '<button class="pagination-btn next" data-page="' . ($page + 1) . '">Вперед</button>';
        }

        $html .= '</div>';
    }
}

// Возвращаем результат в формате JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html' => $html,
    'total' => $total_listings,
    'page' => $page,
    'total_pages' => $total_pages
]);
