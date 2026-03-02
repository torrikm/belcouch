/**
 * Скрипт для работы с избранными объявлениями
 */

document.addEventListener("DOMContentLoaded", function () {
    // Находим все кнопки "Добавить в избранное"
    setupFavoriteButtons();
    
    // Находим кнопку "Очистить список"
    setupClearFavoritesButton();

    // Функция для настройки обработчиков кнопок избранного
    function setupFavoriteButtons() {
        const favoriteButtons = document.querySelectorAll(".favorite-btn");

        favoriteButtons.forEach((button) => {
            button.addEventListener("click", function (e) {
                e.preventDefault();
                
                // Получаем ID объявления из атрибута data-id
                const listingId = this.getAttribute("data-id");
                
                // Проверяем, авторизован ли пользователь
                if (!isUserLoggedIn()) {
                    showLoginPrompt();
                    return;
                }
                
                // Отправляем запрос на сервер для добавления/удаления из избранного
                toggleFavorite(listingId, this);
            });
        });
    }

    // Функция для проверки авторизации пользователя
    function isUserLoggedIn() {
        // Используем скрытый элемент или мета-тег для проверки авторизации
        // Проверяем наличие кнопки выхода, которая есть только у авторизованных пользователей
        const logoutLink = document.querySelector(".logout-btn");
        if (logoutLink) return true;

        // Метод 2: Проверка наличия meta тега с данными пользователя
        const userLoggedInMeta = document.querySelector('meta[name="user-logged-in"]');
        if (userLoggedInMeta && userLoggedInMeta.getAttribute("content") === "true") {
            return true;
        }

        return false;
    }

    // Функция для отображения предложения авторизоваться
    function showLoginPrompt() {
        alert("Чтобы добавить объявление в избранное, необходимо авторизоваться");
        // Можно также перенаправить на страницу входа
        // window.location.href = "login.php";
    }

    // Функция для добавления/удаления объявления из избранного
    function toggleFavorite(listingId, buttonElement) {
        // Показываем индикатор загрузки
        buttonElement.classList.add("loading");
        
        // Подготавливаем данные для отправки
        const listingIdData = `listing_id=${listingId}`;
        console.log('Sending data:', listingIdData); // Для отладки
        
        // Отправляем AJAX запрос
        // Используем новый API для добавления в избранное
        let apiUrl = '';
        if (window.location.pathname.includes('/profile/')) {
            apiUrl = '../api/add_favorite.php';
        } else if (window.location.pathname.includes('/proposals.php')) {
            apiUrl = 'api/add_favorite.php';
        } else {
            apiUrl = '/api/add_favorite.php'; // Абсолютный путь
        }
        console.log('API URL:', apiUrl); // Для отладки
        
        // Отправляем запрос с использованием jQuery Ajax
        $.ajax({
            url: apiUrl,
            type: "POST",
            data: listingIdData,
            dataType: "json",
            success: function(data) {
                // Убираем индикатор загрузки
                buttonElement.classList.remove("loading");
                
                if (data.success) {
                    // Проверяем, находимся ли мы на странице избранного
                    const isOnFavoritesPage = window.location.pathname.includes('/profile/favorites.php');
                    
                    if (data.action === "added") {
                        // Добавление в избранное
                        buttonElement.classList.add("active");
                        buttonElement.innerHTML = "♥"; // Заполненное сердце
                        buttonElement.title = "Удалить из избранного";
                    } else {
                        // Удаление из избранного
                        buttonElement.classList.remove("active");
                        buttonElement.innerHTML = "♡"; // Пустое сердце
                        buttonElement.title = "Добавить в избранное";
                        
                        // Если мы на странице избранного, удаляем карточку
                        if (isOnFavoritesPage) {
                            const listingCard = buttonElement.closest('.listing-card');
                            if (listingCard) {
                                // Плавно скрываем карточку
                                listingCard.style.transition = 'all 0.3s ease';
                                listingCard.style.opacity = '0';
                                listingCard.style.transform = 'scale(0.9)';
                                
                                // Удаляем после анимации
                                setTimeout(() => {
                                    listingCard.remove();
                                    
                                    // Проверяем, остались ли еще карточки
                                    const remainingListings = document.querySelectorAll('.listing-card');
                                    if (remainingListings.length === 0) {
                                        // Если карточек больше нет, перезагружаем страницу
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, 500);
                                    }
                                }, 300);
                            }
                        }
                    }
                    
                    // Показываем уведомление об успешном действии
                    showNotification(data.message);
                } else {
                    // Показываем уведомление об ошибке
                    showNotification(data.message, "error");
                }
            },
            error: function(xhr, status, error) {
                // Убираем индикатор загрузки
                buttonElement.classList.remove("loading");
                
                // Показываем уведомление об ошибке
                showNotification("Произошла ошибка при обработке запроса", "error");
                console.error("Ошибка при выполнении запроса:", error);
            }
        });
    }

    // Функция для отображения уведомлений
    function showNotification(message, type = "success") {
        // Создаем элемент уведомления
        const notification = document.createElement("div");
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        // Добавляем уведомление на страницу
        document.body.appendChild(notification);
        
        // Показываем уведомление
        setTimeout(() => {
            notification.classList.add("show");
        }, 10);
        
        // Скрываем и удаляем уведомление через 3 секунды
        setTimeout(() => {
            notification.classList.remove("show");
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    // Функция для обработки кнопки "Очистить список"
    function setupClearFavoritesButton() {
        const clearButton = document.getElementById('clear-favorites');
        
        if (clearButton) {
            clearButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (confirm('Вы уверены, что хотите очистить весь список избранного?')) {
                    // Определяем путь к API в зависимости от текущего пути
                    let apiUrl = '';
                    if (window.location.pathname.includes('/profile/')) {
                        apiUrl = '../api/clear_favorites.php';
                    } else if (window.location.pathname.includes('/proposals.php')) {
                        apiUrl = 'api/clear_favorites.php';
                    } else {
                        apiUrl = '/api/clear_favorites.php';
                    }
                    
                    // Отправляем запрос на очистку избранного
                    fetch(apiUrl, {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Показываем уведомление об успехе
                            showNotification(data.message);
                            
                            // Перезагружаем страницу через короткое время
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // Показываем уведомление об ошибке
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка при очистке избранного:', error);
                        showNotification('Произошла ошибка при очистке избранного', 'error');
                    });
                }
            });
        }
    }

    // Обновляем обработчики после AJAX-обновления контента
    document.addEventListener("contentUpdated", function() {
        setupFavoriteButtons();
    });
});
