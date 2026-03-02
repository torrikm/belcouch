<?php
/**
 * API для удаления объявления
 */

// Отключаем вывод ошибок, чтобы не нарушить формат JSON
error_reporting(0);

// Устанавливаем заголовок для JSON
header('Content-Type: application/json');

session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']);
    exit;
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Получение ID объявления
$listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;

// Валидация ID
if ($listing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID объявления']);
    exit;
}

// Создаем экземпляр класса для работы с БД
$db = new Database();

try {
    // Проверяем, существует ли объявление и принадлежит ли оно пользователю
    $check_sql = "SELECT id FROM listings WHERE id = ? AND user_id = ?";
    $check_stmt = $db->prepareAndExecute($check_sql, "ii", [$listing_id, $_SESSION['user_id']]);
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Объявление не найдено или у вас нет прав на его удаление']);
        exit;
    }
    
    // Начинаем транзакцию
    $db->query("START TRANSACTION");
    
    // Удаление физических файлов изображений из папки
    $images_directory = "../assets/img/listings/{$listing_id}";
    if (is_dir($images_directory)) {
        // Получаем список всех файлов в папке
        $files = glob($images_directory . "/*");
        
        // Удаляем каждый файл
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Удаляем саму папку
        rmdir($images_directory);
    }
    
    // Удаляем само объявление
    $delete_sql = "DELETE FROM listings WHERE id = ?";
    $delete_stmt = $db->prepareAndExecute($delete_sql, "i", [$listing_id]);
    
    // Проверяем результат удаления
    if ($delete_stmt->affected_rows > 0) {
        // Фиксируем транзакцию
        $db->query("COMMIT");
        echo json_encode(['success' => true, 'message' => 'Объявление успешно удалено']);
    } else {
        // Отменяем транзакцию
        $db->query("ROLLBACK");
        echo json_encode(['success' => false, 'message' => 'Не удалось удалить объявление']);
    }
} catch (Exception $e) {
    // Отменяем транзакцию в случае ошибки
    $db->query("ROLLBACK");
    echo json_encode(['success' => false, 'message' => 'Ошибка при удалении объявления: ' . $e->getMessage()]);
}
?>