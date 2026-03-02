/**
 * Скрипт для живого поиска на главной странице
 */
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');
    
    // Минимальное количество символов для начала поиска
    const minSearchLength = 2;
    
    // Таймер для задержки поиска (debounce)
    let searchTimer;
    
    // Обработчик ввода в поле поиска
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimer);
        
        const query = this.value.trim();
        
        // Скрываем результаты, если строка поиска пуста
        if (query.length < minSearchLength) {
            searchResults.classList.remove('active');
            searchResults.innerHTML = '';
            return;
        }
        
        // Устанавливаем задержку в 300 мс перед отправкой запроса
        searchTimer = setTimeout(function() {
            fetchSearchResults(query);
        }, 300);
    });
    
    // Скрываем выпадающий список при клике вне его
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.search-input-container')) {
            searchResults.classList.remove('active');
        }
    });
    
    // Функция для получения результатов поиска с сервера
    function fetchSearchResults(query) {
        $.ajax({
            url: 'api/search_listings.php',
            type: 'GET',
            data: { query: query },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    displaySearchResults(data.results);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка при выполнении поиска:', error);
            }
        });
    }
    
    // Функция для отображения результатов поиска
    function displaySearchResults(results) {
        // Очищаем предыдущие результаты
        searchResults.innerHTML = '';
        
        if (results.length === 0) {
            // Если результатов нет, показываем соответствующее сообщение
            searchResults.innerHTML = '<div class="search-no-results">Ничего не найдено</div>';
            searchResults.classList.add('active');
            return;
        }
        
        // Создаем элементы для каждого результата
        results.forEach(result => {
            const resultItem = document.createElement('div');
            resultItem.className = 'search-result-item';
            
            // При клике на результат перенаправляем на страницу жилья
            resultItem.addEventListener('click', function() {
                window.location.href = `profile/housing.php?id=${result.user_id}`;
            });
            
            // Формируем HTML для элемента результата
            resultItem.innerHTML = `
                <img src="${result.image}" alt="${result.title}" class="search-result-image">
                <div class="search-result-info">
                    <h4 class="search-result-title">${result.title}</h4>
                    <p class="search-result-location">${result.city}</p>
                    <span class="search-result-type">${result.property_type}</span>
                </div>
            `;
            
            searchResults.appendChild(resultItem);
        });
        
        // Показываем блок с результатами
        searchResults.classList.add('active');
    }
});
