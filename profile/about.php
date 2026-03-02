<?php
/**
 * Страница "О себе" - часть профиля пользователя
 * Отображает личную информацию, образование, интересы и отзывы пользователя
 */

session_start();
$page_title = "О пользователе";
require_once '../config/config.php';
require_once '../includes/db.php';

if (!isset($_GET['id']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Создаем экземпляр класса для работы с БД
$db = new Database();

// Получение ID пользователя из URL или использование ID текущего пользователя
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];

// Функция для расчета возраста по дате рождения
function calculateAge($birthdate)
{
    if (empty($birthdate))
        return null;

    $today = new DateTime();
    $birth = new DateTime($birthdate);
    $interval = $today->diff($birth);
    return $interval->y;
}

// Функция для склонения слов в зависимости от числа
function plural_form($number, $forms)
{
    $cases = [2, 0, 1, 1, 1, 2];
    $index = ($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)];
    return $forms[$index];
}

// Получение списка областей из БД
try {
    $regions_sql = "SELECT id, name FROM regions ORDER BY name ASC";
    $regions_result = $db->query($regions_sql);
    $regions = [];

    if ($regions_result) {
        while ($row = $regions_result->fetch_assoc()) {
            $regions[] = $row;
        }
    }
} catch (Exception $e) {
    // Если не удалось получить список областей, продолжаем без него
    $regions = [];
}

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
        header('Location: index.php');
        exit;
    }

    $user = $result->fetch_assoc();
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

// Определяем текущую вкладку (по умолчанию "О себе")
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'about';
if (!in_array($active_tab, ['about', 'housing', 'favorites'])) {
    $active_tab = 'about';
}

// Подключение header
require_once '../includes/header.php';

// Получение звездного рейтинга
$starRating = isset($user['avg_rating']) ? round($user['avg_rating']) : 0;

// Примечание: не подключаем компонент шапки здесь, его подключим в HTML структуре

// Получение рейтингов и отзывов пользователя
try {
    $sql = "SELECT ur.*, u.first_name, u.last_name, u.avatar_image
            FROM user_ratings ur
            JOIN users u ON ur.rater_id = u.id
            WHERE ur.user_id = ?
            ORDER BY ur.created_at DESC
            LIMIT 3";

    $stmt = $db->prepareAndExecute($sql, "i", [$profile_id]);
    $ratingsResult = $stmt->get_result();
    $ratings = [];
    // Проверяем, может ли текущий пользователь оставить отзыв
    $can_review = false;
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $profile_id) {
        // Проверяем, не оставлял ли уже пользователь отзыв
        $check_sql = "SELECT id FROM user_ratings WHERE user_id = ? AND rater_id = ?";
        $check_stmt = $db->prepareAndExecute($check_sql, "ii", [$profile_id, $_SESSION['user_id']]);
        $check_result = $check_stmt->get_result();

        $can_review = ($check_result->num_rows === 0);
    }

    while ($row = $ratingsResult->fetch_assoc()) {
        $ratings[] = $row;
    }
} catch (Exception $e) {
    // Просто продолжаем, если отзывов нет, это не критическая ошибка
    $ratings = [];
}

// Форматирование даты регистрации
$registrationDate = new DateTime($user['registration_date']);
$registrationDateFormatted = $registrationDate->format('d.m.Y');

// Получение звездного рейтинга
$starRating = isset($user['avg_rating']) ? round($user['avg_rating']) : 0;
?>

<div class="container">
    <div class="profile-page">
        <!-- Шапка профиля -->
        <div class="profile-header-container">
            <?php require_once '../includes/profile_header.php'; ?>

            <!-- Навигация профиля -->
            <div class="profile-right">
                <div class="profile-nav">
                    <a href="./about.php<?php echo isset($_GET['id']) ? '?id=' . $profile_id : ''; ?>"
                        class="profile-nav-item active">Обо мне</a>
                    <a href="./housing.php<?php echo isset($_GET['id']) ? '?id=' . $profile_id : ''; ?>"
                        class="profile-nav-item">Жилье</a>
                    <?php if ($isOwnProfile): ?>
                        <a href="./favorites.php" class="profile-nav-item">Избранное</a>
                    <?php endif; ?>
                </div>

                <!-- Блок "Обо мне" -->
                <div class="profile-block">
                    <div class="profile-section-header">
                        <h2>Обо мне</h2>
                        <?php if ($isOwnProfile): ?>
                            <a href="edit_profile.php#about" class="edit-link">
                                <img src="../assets/img/icons/edit.svg" alt="Редактировать">
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="profile-bio">
                        <?php if (!empty($user['description'])): ?>
                            <?php
                            // Преобразуем описание в параграфы
                            $paragraphs = explode("\n", $user['description']);
                            foreach ($paragraphs as $paragraph):
                                if (trim($paragraph)): // Проверяем, что параграф не пустой
                                    ?>
                                    <p><?php echo htmlspecialchars($paragraph); ?></p>
                                    <?php
                                endif;
                            endforeach;
                            ?>
                        <?php else: ?>
                            <p class="no-bio-text">
                                <?php echo $isOwnProfile ? 'Добавьте информацию о себе, чтобы другие пользователи могли узнать вас лучше.' : 'Пользователь еще не добавил информацию о себе.'; ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="profile-details">
                        <div class="profile-detail-item">
                            <div class="detail-icon education-icon"></div>
                            <div class="detail-text">
                                <?php if (!empty($user['education'])): ?>
                                    <?php echo htmlspecialchars($user['education']); ?>
                                <?php else: ?>
                                    <?php echo $isOwnProfile ? 'Укажите ваше образование' : 'Не указано'; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-detail-item">
                            <div class="detail-icon work-icon"></div>
                            <div class="detail-text">
                                <?php if (!empty($user['occupation'])): ?>
                                    <?php echo htmlspecialchars($user['occupation']); ?>
                                <?php else: ?>
                                    <?php echo $isOwnProfile ? 'Укажите вашу работу' : 'Не указано'; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-detail-item">
                            <div class="detail-icon hobby-icon"></div>
                            <div class="detail-text">
                                <?php if (!empty($user['interests'])): ?>
                                    <?php echo htmlspecialchars($user['interests']); ?>
                                <?php else: ?>
                                    <?php echo $isOwnProfile ? 'Укажите ваши интересы' : 'Не указано'; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Блок с отзывами -->
        <div class="reviews-section">
            <!-- Список отзывов -->
            <?php if (!empty($ratings)): ?>
                <div class="reviews-list">
                    <h3 class="reviews-title">Отзывы</h3>
                    <?php foreach ($ratings as $review): ?>
                        <div class="review-item">
                            <div class="review-item-header">
                                <div class="reviewer-info">
                                    <?php if ($review['avatar_image']): ?>
                                        <img src="../api/get_avatar.php?id=<?php echo $review['rater_id']; ?>" alt="Аватар"
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
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Форма для отправки отзыва -->
            <?php if (!$isOwnProfile && isset($_SESSION['user_id'])): ?>
                <div class="review-block">
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
                    <form id="review-form" method="post">
                        <input type="hidden" name="user_id" value="<?php echo (int) $profile_id; ?>">
                        <input type="hidden" name="rating" value="0">
                        <textarea name="comment" rows="5" class="review-textarea"
                            placeholder="Напишите свои впечатления об этом владельце" required></textarea>
                        <button type="submit" class="btn btn-primary">Отправить</button>
                    </form>
                </div>
            <?php elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $profile_id && !$can_review): ?>
                <div class="profile-review-info">
                    <p>Вы уже оставили отзыв на этого владельца.</p>
                </div>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <div class="profile-review-info">
                    <p>Чтобы оставить отзыв, <a href="../login.php">войдите</a> в систему.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>

<!-- Подключаем JavaScript для отзывов -->
<script src="../assets/js/review.js"></script>