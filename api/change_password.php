<?php
/**
 * API для изменения пароля пользователя
 */
session_start();
require_once '../config/config.php';
require_once '../includes/db.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован']);
    exit;
}

// Получаем ID пользователя из сессии
$user_id = $_SESSION['user_id'];

// Проверяем, что все необходимые поля отправлены
if (!isset($_POST['current_password']) || !isset($_POST['new_password']) || !isset($_POST['confirm_password'])) {
    echo json_encode(['success' => false, 'message' => 'Не все обязательные поля заполнены']);
    exit;
}

// Проверяем совпадение паролей
if ($_POST['new_password'] !== $_POST['confirm_password']) {
    echo json_encode(['success' => false, 'message' => 'Новый пароль и подтверждение не совпадают']);
    exit;
}

// Проверяем минимальную длину пароля
if (strlen($_POST['new_password']) < 8) {
    echo json_encode(['success' => false, 'message' => 'Пароль должен содержать минимум 8 символов']);
    exit;
}

try {
    // Инициализируем подключение к БД
    $db = new Database();
    
    // Получаем текущий пароль и соль для проверки
    $sql = "SELECT password_hash, password_salt FROM users WHERE id = ?";
    $stmt = $db->prepareAndExecute($sql, "i", [$user_id]);
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Проверяем текущий пароль
    $current_password = $_POST['current_password'];
    $current_hashed = hash('sha256', $current_password . $user['password_salt']);
    
    if ($current_hashed !== $user['password_hash']) {
        echo json_encode(['success' => false, 'message' => 'Текущий пароль введен неверно']);
        exit;
    }
    
    // Генерируем новую соль для безопасности
    $new_salt = bin2hex(random_bytes(16));
    
    // Хешируем новый пароль с новой солью
    $new_password = $_POST['new_password'];
    $new_hashed = hash('sha256', $new_password . $new_salt);
    
    // Обновляем пароль в базе данных
    $update_sql = "UPDATE users SET password_hash = ?, password_salt = ? WHERE id = ?";
    $db->prepareAndExecute($update_sql, "ssi", [$new_hashed, $new_salt, $user_id]);
    
    // Возвращаем успешный ответ
    echo json_encode(['success' => true, 'message' => 'Пароль успешно изменен']);
    
} catch (Exception $e) {
    // В случае ошибки возвращаем сообщение об ошибке
    echo json_encode(['success' => false, 'message' => 'Ошибка при изменении пароля: ' . $e->getMessage()]);
    exit;
}
?>
