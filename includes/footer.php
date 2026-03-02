</main>

<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-up">
            <div class="footer-logo">
                <a href="../index.php" class="logo">
                    <img src="../assets/img/icons/logo.svg" alt="BelCouch Logo" class="logo-img">
                </a>
            </div>

            <nav class="footer-nav">
                <?php
                // Если нет доступа к $nav_items из header.php, загружаем заново
                if (!isset($nav_items) || empty($nav_items)) {
                    if (!isset($db)) {
                        require_once 'includes/db.php';
                        $db = new Database();
                    }
                    $nav_items = $db->getAll("SELECT * FROM navigation");
                }

                foreach ($nav_items as $item):
                    ?>
                    <a href="<?php echo $item['url']; ?>" class="footer-nav-item"><?php echo $item['title']; ?></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="footer-copyright">
            © <?php echo date('Y'); ?> BelCouch Белорусский сайт каучсёрфинга
        </div>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>


<script src="../assets/js/modal.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Скрипт для мобильной навигации
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const mainNavigation = document.querySelector('.main-navigation');

        if (mobileMenuToggle && mainNavigation) {
            mobileMenuToggle.addEventListener('click', function () {
                mainNavigation.classList.toggle('active');
                // Анимация для кнопки меню
                const bars = this.querySelectorAll('.bar');
                bars.forEach(bar => bar.classList.toggle('active'));
            });
        }
    });
</script>

<?php if (isset($additionalJs) && is_array($additionalJs)): ?>
    <?php foreach ($additionalJs as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>

</html>