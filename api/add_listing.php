<?php
/**
 * API для добавления и редактирования объявлений о жилье
 */

session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Включаем подробный вывод ошибок для отладки
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Создаем папку для логов, если её нет
if (!is_dir('../logs')) {
    mkdir('../logs', 0777, true);
}

// Создаем лог-файл для отладки
$log_file = '../logs/add_listing_debug.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Запуск API\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - FILES: " . print_r($_FILES, true) . "\n", FILE_APPEND);

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Вы должны быть авторизованы для добавления объявлений'
    ]);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Ошибка: Пользователь не авторизован\n", FILE_APPEND);
    exit;
}

// Создаем экземпляр Database
$db = new Database();

// Получение данных из POST-запроса
$user_id = $_SESSION['user_id'];
$listing_id = isset($_POST['listing_id']) && !empty($_POST['listing_id']) ? intval($_POST['listing_id']) : null;
$property_type_id = isset($_POST['property_type_id']) ? intval($_POST['property_type_id']) : 0;
$max_guests = isset($_POST['beds_count']) ? intval($_POST['beds_count']) : 1;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$listing_region_id = isset($_POST['listing_region_id']) ? intval($_POST['listing_region_id']) : 1;
$listing_city = isset($_POST['listing_city']) ? trim($_POST['listing_city']) : 'Минск';
$stay_duration_id = isset($_POST['stay_duration_id']) ? intval($_POST['stay_duration_id']) : 0;

// Получаем данные о регионе пользователя из его профиля
$user_details_sql = "SELECT region_id, city FROM user_details WHERE user_id = ?";
$user_details_stmt = $db->prepareAndExecute($user_details_sql, "i", [$user_id]);
$user_details_result = $user_details_stmt->get_result();
$user_details = $user_details_result->fetch_assoc();

// Устанавливаем значения региона и города
$region_id = isset($_POST['listing_region_id']) && !empty($_POST['listing_region_id']) ? intval($_POST['listing_region_id']) : 1; // Всегда устанавливаем значение 1 по умолчанию

// Дополнительная проверка, чтобы убедиться, что значение не NULL
if ($region_id === null || $region_id <= 0) {
    $region_id = 1; // Гарантируем, что значение будет числовым и положительным
}

$city = isset($_POST['listing_city']) && !empty($_POST['listing_city']) ? trim($_POST['listing_city']) : ($user_details && !empty($user_details['city']) ? $user_details['city'] : 'Минск'); // Если нет города, используем Минск по умолчанию

// Журналируем значения для отладки
file_put_contents($log_file, date('Y-m-d H:i:s') . " - region_id = {$region_id}, city = {$city}\n", FILE_APPEND);

// Проверка обязательных полей
if ($property_type_id <= 0 || empty($title)) {
    echo json_encode([
        'success' => false,
        'message' => 'Пожалуйста, заполните все обязательные поля'
    ]);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Ошибка: Не заполнены обязательные поля\n", FILE_APPEND);
    exit;
}

// Получение правил и удобств
$rules = isset($_POST['rules']) && is_array($_POST['rules']) ? $_POST['rules'] : [];
$amenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ? $_POST['amenities'] : [];

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Обработка данных: listing_id={$listing_id}, property_type_id={$property_type_id}, title={$title}\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Правила: " . print_r($rules, true) . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Удобства: " . print_r($amenities, true) . "\n", FILE_APPEND);

try {
    // Добавление или обновление объявления
    if ($listing_id) {
        // Обновление существующего объявления
        $sql = "UPDATE listings SET 
                property_type_id = ?, 
                max_guests = ?, 
                title = ?, 
                notes = ?, 
                stay_duration_id = ?,
                region_id = ?,
                city = ?,
                updated_at = NOW()
                WHERE id = ? AND user_id = ?";

        $db->prepareAndExecute($sql, "iissiisii", [
            $property_type_id,
            $max_guests,
            $title,
            $notes,
            $stay_duration_id,
            $listing_region_id,
            $listing_city,
            $listing_id,
            $user_id
        ]);

        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Обновление объявления ID: {$listing_id}\n", FILE_APPEND);
    } else {
        // Добавление нового объявления
        $sql = "INSERT INTO listings (user_id, property_type_id, max_guests, title, notes, stay_duration_id, region_id, city, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $db->prepareAndExecute($sql, "iiissiis", [
            $user_id,
            $property_type_id,
            $max_guests,
            $title,
            $notes,
            $stay_duration_id,
            $listing_region_id,
            $listing_city
        ]);

        $listing_id = $db->getLastInsertId();
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Добавление нового объявления, ID: {$listing_id}\n", FILE_APPEND);
    }

    // Если у нас есть ID объявления, добавляем правила и удобства
    if ($listing_id) {
        // Сначала удаляем существующие правила и удобства
        $db->prepareAndExecute("DELETE FROM listing_rules WHERE listing_id = ?", "i", [$listing_id]);
        $db->prepareAndExecute("DELETE FROM listing_amenities WHERE listing_id = ?", "i", [$listing_id]);

        // Добавляем правила
        foreach ($rules as $rule_id) {
            $rule_id = intval($rule_id);
            if ($rule_id > 0) {
                $db->prepareAndExecute(
                    "INSERT INTO listing_rules (listing_id, rule_id) VALUES (?, ?)",
                    "ii",
                    [$listing_id, $rule_id]
                );
            }
        }

        // Добавляем удобства
        foreach ($amenities as $amenity_id) {
            $amenity_id = intval($amenity_id);
            if ($amenity_id > 0) {
                $db->prepareAndExecute(
                    "INSERT INTO listing_amenities (listing_id, amenity_id) VALUES (?, ?)",
                    "ii",
                    [$listing_id, $amenity_id]
                );
            }
        }

        // Удаляем только отмеченные для удаления фото
        $target_dir = "../assets/img/listings/{$listing_id}/";
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Удаление выбранных фото: {$target_dir}\n", FILE_APPEND);

        // Создаем директорию, если ее нет
        if (!is_dir($target_dir)) {
            if (!is_dir("../assets/img/listings")) {
                mkdir("../assets/img/listings", 0777, true);
            }
            mkdir($target_dir, 0777, true);
        }

        // Удаляем только те файлы, которые пришли в deleted_images[]
        if (!empty($_POST['deleted_images']) && is_array($_POST['deleted_images'])) {
            foreach ($_POST['deleted_images'] as $rel_path) {
                $file = '../' . ltrim($rel_path, '/');
                if (file_exists($file) && strpos(realpath($file), realpath($target_dir)) === 0) {
                    unlink($file);
                    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Удалено по запросу: {$file}\n", FILE_APPEND);
                }
            }
        }

        // Обработка фотографий
        if (isset($_FILES['housing_photos']) && !empty($_FILES['housing_photos']['name'][0])) {

            // Обрабатываем каждое загруженное изображение
            $count = count($_FILES['housing_photos']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['housing_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['housing_photos']['tmp_name'][$i];
                    $name = $_FILES['housing_photos']['name'][$i];
                    $extension = pathinfo($name, PATHINFO_EXTENSION);

                    // Генерируем уникальное имя файла
                    $new_name = ($i === 0) ? "main.{$extension}" : "image_{$i}.{$extension}";
                    $target_file = $target_dir . $new_name;

                    // Перемещаем файл в нужную директорию
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Изображение {$i} загружено: {$target_file}\n", FILE_APPEND);
                    } else {
                        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Ошибка при загрузке изображения {$i}\n", FILE_APPEND);
                    }
                } else {
                    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Ошибка {$_FILES['housing_photos']['error'][$i]} при загрузке изображения {$i}\n", FILE_APPEND);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => $listing_id ? 'Объявление успешно обновлено' : 'Объявление успешно добавлено',
            'listing_id' => $listing_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Не удалось получить ID объявления'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при сохранении объявления: ' . $e->getMessage()
    ]);

    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Ошибка: {$e->getMessage()}\n", FILE_APPEND);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Stack trace: {$e->getTraceAsString()}\n", FILE_APPEND);
}
?>