<?php
/**
 * API для очистки всех избранных объявлений пользователя
 */

session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Вы должны быть авторизованы для управления избранным'
    ]);
    exit;
}

// Создаем экземпляр Database
$db = new Database();
$user_id = $_SESSION['user_id'];

try {
    // Удаляем все избранные объявления пользователя
    $sql = "DELETE FROM favorites WHERE user_id = ?";
    $db->prepareAndExecute($sql, "i", [$user_id]);
    
    // Возвращаем успешный ответ
    echo json_encode([
        'success' => true,
        'message' => 'Список избранного очищен'
    ]);
} catch (Exception $e) {
    // Возвращаем ошибку, если что-то пошло не так
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при очистке списка избранного: ' . $e->getMessage()
    ]);
}
?>
