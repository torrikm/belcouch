<?php
// Подключение необходимых файлов
require_once 'config/config.php';
require_once 'includes/db.php';

// Инициализация подключения к БД
$db = new Database();

// Загрузка данных для слайдера
$slider_images = $db->getAll("SELECT id, title FROM slider_images");

// Загрузка данных по регионам и количеству объявлений
$regions_data = [];

// Получаем список всех регионов
$regions_sql = "SELECT id, code, name FROM regions ORDER BY name";
$regions_result = $db->query($regions_sql);

while ($region = $regions_result->fetch_assoc()) {
    // Для каждого региона получаем количество объявлений
    $count_sql = "SELECT COUNT(*) as count FROM listings WHERE region_id = ?";
    $count_stmt = $db->prepareAndExecute($count_sql, "i", [$region['id']]);
    $count_result = $count_stmt->get_result();
    $count_data = $count_result->fetch_assoc();

    // Добавляем данные в массив
    $region['count'] = $count_data['count'];
    $regions_data[$region['id']] = $region;
}

$pageTitle = "Главная";

// Подключение шапки сайта
include 'includes/header.php';
?>

<!-- Первый блок - поиск -->
<div class="container">
    <section class="search-block">
        <h1 class="search-title">НАЙДЁМ, ГДЕ ОСТАНОВИТЬСЯ!</h1>
        <div class="search-form-container">
            <form class="search-form" action="search.php" method="GET">
                <div class="search-input-container">
                    <input type="text" name="location" id="search-input" class="search-input"
                        placeholder="Напишите место, которое хотите посетить" autocomplete="off" required>
                    <div id="search-results" class="search-results"></div>
                </div>
                <button type="submit" class="search-button">
                    <div class="search-icon">
                        <img src="assets/img/icons/search.svg" alt="Поиск">
                    </div>
                </button>
            </form>
        </div>
    </section>
</div>

<!-- Второй блок - слайдер -->
<section class="slider-section">
    <div class="slider-container">
        <div class="slider">
            <?php if (!empty($slider_images)): ?>
                <?php foreach ($slider_images as $index => $slide): ?>
                    <div class="slide" data-index="<?php echo $index; ?>">
                        <!-- Используем отдельный PHP-скрипт для отображения изображения из БД -->
                        <img src="api/get_slider_image.php?id=<?php echo $slide['id']; ?>"
                            alt="<?php echo htmlspecialchars($slide['title']); ?>">
                        <div class="slide-overlay">
                            <h2 class="slide-title"><?php echo htmlspecialchars($slide['title']); ?></h2>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Заглушка для слайдера, если нет изображений -->
                <div class="slide">
                    <div style="background-color: #334891; width: 100%; height: 500px;"></div>
                    <div class="slide-overlay">
                        <h2 class="slide-title">ПУТЕШЕСТВУЙТЕ ПО БЕЛАРУСИ СОВЕРШЕННО БЕСПЛАТНО!</h2>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Стрелки для переключения слайдов удалены -->

        <!-- Пагинация слайдера -->
        <div class="slider-pagination">
            <?php if (!empty($slider_images)): ?>
                <?php foreach ($slider_images as $index => $slide): ?>
                    <span class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>"
                        data-index="<?php echo $index; ?>"></span>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="slider-dot active" data-index="0"></span>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Третий блок - преимущества -->
<div class="container">

    <section class="features-section">
        <div class="features-container">
            <div class="feature-item">
                <div class="feature-icon">
                    <img src="assets/img/icons/geo.svg" alt="География">
                </div>
                <div class="feature-text">
                    Более 1000 вариантов размещения в различных уголках Беларуси
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">
                    <img src="assets/img/icons/chat.svg" alt="Общение">
                </div>
                <div class="feature-text">
                    Общение непосредственно с самим владельцем жилья и туристом
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon">
                    <img src="assets/img/icons/verify.svg" alt="Проверка">
                </div>
                <div class="feature-text">
                    Только пользователи, подтвердившие свою личность
                </div>
            </div>
        </div>
    </section>

    <!-- Секция с популярными направлениями -->
    <section class="regions-section">
        <h2 class="regions-title">ПОПУЛЯРНЫЕ НАПРАВЛЕНИЯ</h2>
        <div class="regions-grid">
            <?php if (!empty($regions_data)): ?>
                <?php foreach ($regions_data as $region_id => $region): ?>
                    <a href="proposals.php?region=<?php echo $region_id; ?>" class="region-card">
                        <div class="region-image"
                            style="background-image: url('assets/img/regions/<?php echo strtolower($region['code']); ?>.png')">
                            <span class="region-badge">
                                <?php echo $region['count']; ?>
                                <?php
                                // Склонение слова "вариант" в зависимости от числа
                                $count = $region['count'];
                                $last_digit = $count % 10;
                                $last_two_digits = $count % 100;

                                if ($last_digit == 1 && $last_two_digits != 11) {
                                    echo "вариант";
                                } elseif (
                                    ($last_digit == 2 || $last_digit == 3 || $last_digit == 4) &&
                                    ($last_two_digits != 12 && $last_two_digits != 13 && $last_two_digits != 14)
                                ) {
                                    echo "варианта";
                                } else {
                                    echo "вариантов";
                                }
                                ?>
                            </span>
                        </div>
                        <div class="region-name"><?php echo $region['name']; ?></div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Если данных нет, показываем заглушки -->
                <div class="region-placeholder">Регионы не найдены</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="survey-section">
        <div class="survey-container">
            <div class="survey-text">
                <div class="survey-left">
                    <h2 class="survey-title">Не знаете какой город хотите посетить?</h2>
                    <p class="survey-description">Пройдите наш опрос в телеграме и узнайте, какое место подходит именно
                        Вам!
                    </p>
                </div>
                <div class="survey-center">
                    <h3 class="survey-subtitle"><a href="https://t.me/BelarusTest_bot" target="_blank">перейдите по
                            ссылке</a></h3>
                    <p class="survey-note">Или наведите камеру на QR-код, чтобы перейти к тесту!</p>
                </div>
            </div>
            <div class="survey-qr">
                <div class="qr-container">
                    <img src="assets/img/qr-code.png" alt="QR-код" class="qr-image">
                </div>
            </div>
        </div>
        <img src="assets/img/icons/arrow.svg" alt="Стрелка" class="survey-arrow">
    </section>
</div>

<?php
// Добавление JavaScript для слайдера
$additionalJs = ['assets/js/slider.js'];

// Подключение подвала сайта
include 'includes/footer.php';
?>

<!-- JavaScript для живого поиска -->
<script src="assets/js/live-search.js"></script>