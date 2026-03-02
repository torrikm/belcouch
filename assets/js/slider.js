/**
 * JavaScript для управления слайдером на главной странице
 */
document.addEventListener('DOMContentLoaded', function() {
    // Получаем DOM элементы
    const slider = document.querySelector('.slider');
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.slider-dot');
    
    // Если элементы не найдены, выходим
    if (!slider || slides.length === 0) return;
    
    let currentSlide = 0;
    const slideCount = slides.length;
    
    // Инициализация слайдера
    function initSlider() {
        // Устанавливаем начальное положение
        updateSlider();
        
        // Добавляем обработчики для точек пагинации
        dots.forEach(dot => {
            dot.addEventListener('click', function() {
                const slideIndex = parseInt(this.getAttribute('data-index'));
                goToSlide(slideIndex);
            });
        });
        
        // Автоматическое переключение слайдов
        startAutoSlide();
    }
    
    // Функция для переключения на следующий слайд (используется для автоматического переключения и свайпа)
    function nextSlide() {
        currentSlide = (currentSlide + 1) % slideCount;
        updateSlider();
        resetAutoSlide();
    }
    
    // Переход к определенному слайду
    function goToSlide(index) {
        if (index >= 0 && index < slideCount) {
            currentSlide = index;
            updateSlider();
            resetAutoSlide();
        }
    }
    
    // Обновление отображения слайдера
    function updateSlider() {
        // Обновляем положение слайдера
        const offset = -currentSlide * 100;
        slider.style.transform = `translateX(${offset}%)`;
        
        // Обновляем точки пагинации
        dots.forEach((dot, index) => {
            if (index === currentSlide) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }
    
    // Переменная для хранения интервала автоматического переключения
    let autoSlideInterval;
    
    // Запуск автоматического переключения слайдов
    function startAutoSlide() {
        autoSlideInterval = setInterval(nextSlide, 5000); // Переключение каждые 5 секунд
    }
    
    // Сброс таймера автоматического переключения
    function resetAutoSlide() {
        if (autoSlideInterval) {
            clearInterval(autoSlideInterval);
            startAutoSlide();
        }
    }
    
    // Запускаем слайдер
    initSlider();
    
    // Поддержка свайпа для мобильных устройств
    let touchStartX = 0;
    let touchEndX = 0;
    
    slider.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, false);
    
    slider.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);
    
    function handleSwipe() {
        const swipeThreshold = 50; // Минимальное расстояние для определения свайпа
        
        if (touchEndX < touchStartX - swipeThreshold) {
            // Свайп влево - следующий слайд
            nextSlide();
        }
        
        if (touchEndX > touchStartX + swipeThreshold) {
            // Свайп вправо - предыдущий слайд
            // Восстанавливаем функцию prevSlide, которая была удалена
            currentSlide = (currentSlide - 1 + slideCount) % slideCount;
            updateSlider();
            resetAutoSlide();
        }
    }
});
