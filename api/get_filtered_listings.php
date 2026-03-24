<?php
require_once __DIR__ . '/../bootstrap.php';

$listingsApiService = new ListingsApiService();
$filteredData = $listingsApiService->getFilteredListingsData($_POST, isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);

$userFavorites = $filteredData['user_favorites'];
$listings = $filteredData['listings'];
$totalListings = $filteredData['total'];
$page = $filteredData['page'];
$totalPages = $filteredData['total_pages'];

$html = '';
if (empty($listings)) {
	$html .= '<div class="no-listings"><p>По вашему запросу ничего не найдено. Попробуйте изменить параметры фильтра.</p></div>';
} else {
	$html .= '<div class="total-count"><p>Найдено предложений: <strong>' . $totalListings . '</strong></p></div>';
	$html .= '<div class="listings-list">';
	foreach ($listings as $listing) {
		$html .= '<div class="listing-card"><div class="listing-image"><a href="profile/housing?id=' . $listing['user_id'] . '"><img src="' . $listing['main_image'] . '" alt="' . htmlspecialchars($listing['title']) . '"></a>';
		if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $listing['user_id']) {
			$isFavorite = isset($_SESSION['user_id']) && in_array((int) $listing['id'], $userFavorites, true);
			$heartIcon = $isFavorite ? '♥' : '♡';
			$favoriteTitle = $isFavorite ? 'Удалить из избранного' : 'Добавить в избранное';
			$activeClass = $isFavorite ? ' active' : '';
			$html .= '<button class="favorite-btn' . $activeClass . '" data-id="' . $listing['id'] . '" title="' . $favoriteTitle . '">' . $heartIcon . '</button>';
		}
		$html .= '</div><div class="listing-details"><div class="listing-main-info"><div class="listing-location"><h3 class="city-name">' . htmlspecialchars($listing['city']) . '</h3><p class="region-name">' . htmlspecialchars($listing['region_name']) . '</p></div><div class="listing-rating"><div class="rating-value">' . number_format($listing['avg_rating'] ?: 0, 2) . '</div><div class="rating-stars">';
		$rating = $listing['avg_rating'] ?: 0;
		for ($i = 1; $i <= 5; $i++) {
			$starType = ($i <= $rating) ? 'star-filled.svg' : 'star-void.svg';
			$html .= '<img src="assets/img/icons/' . $starType . '" alt="Рейтинг" class="rating-star">';
		}
		$html .= '</div></div></div><div class="listing-specifications"><div class="specification-item"><span class="spec-label">Тип:</span><span class="spec-value">' . htmlspecialchars($listing['property_type_name']) . '</span></div><div class="specification-item"><span class="spec-label">Количество спальных мест:</span><span class="spec-value">' . $listing['max_guests'] . '</span></div><div class="specification-item"><span class="spec-label">Время пребывания:</span><span class="spec-value">' . htmlspecialchars($listing['stay_duration_name'] ?: 'Не указано') . '</span></div><div class="specification-item"><span class="spec-label">Примечание:</span><span class="spec-value">';
		if (!empty($listing['notes'])) {
			$html .= htmlspecialchars(mb_substr($listing['notes'], 0, 30)) . (mb_strlen($listing['notes']) > 30 ? '...' : '');
		} else {
			$html .= 'нет';
		}
		$html .= '</span></div></div><div class="listing-host"><div class="host-photo"><a href="profile/about?id=' . $listing['user_id'] . '" class="host-photo-link">';
		if (isset($listing['avatar_image']) && $listing['avatar_image']) {
			$html .= '<img src="' . API_URL . '/users/get_avatar.php?id=' . $listing['user_id'] . '" alt="Фото пользователя" class="host-avatar">';
		} else {
			$initials = '';
			if (!empty($listing['first_name'])) {
				$initials .= mb_substr($listing['first_name'], 0, 1, 'UTF-8');
			}
			if (!empty($listing['last_name'])) {
				$initials .= mb_substr($listing['last_name'], 0, 1, 'UTF-8');
			}
			$html .= '<div class="host-avatar-placeholder">' . htmlspecialchars($initials ?: 'U') . '</div>';
		}
		$html .= '</a>';
		if ($listing['is_verify'] == 1) {
			$html .= '<div class="host-verification">✓</div>';
		}
		$html .= '</div><div class="host-info"><a href="profile/about?id=' . $listing['user_id'] . '" class="host-name">' . htmlspecialchars($listing['first_name'] . ' ' . $listing['last_name']) . '</a>';
		if ($listing['user_rating'] > 0) {
			$html .= '<div class="host-rating">' . number_format($listing['user_rating'], 2) . ' ';
			for ($i = 1; $i <= 5; $i++) {
				$starType = ($i <= $listing['user_rating']) ? 'star-filled.svg' : 'star-void.svg';
				$html .= '<img src="assets/img/icons/' . $starType . '" alt="Рейтинг" class="rating-star">';
			}
			$html .= '</div>';
		}
		if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] !== (int) $listing['user_id']) {
			$html .= '</div><a href="#" class="contact-host-btn">Написать</a></div></div></div>';
		} else {
			$html .= '</div></div></div>';
		}
	}
	$html .= '</div>';
	if ($totalPages > 1) {
		$html .= '<div class="pagination">';
		if ($page > 1) {
			$html .= '<button class="pagination-btn prev" data-page="' . ($page - 1) . '">Назад</button>';
		}
		$startPage = max(1, $page - 2);
		$endPage = min($totalPages, $page + 2);
		for ($i = $startPage; $i <= $endPage; $i++) {
			$activeClass = ($i == $page) ? 'active' : '';
			$html .= '<button class="pagination-btn page ' . $activeClass . '" data-page="' . $i . '">' . $i . '</button>';
		}
		if ($page < $totalPages) {
			$html .= '<button class="pagination-btn next" data-page="' . ($page + 1) . '">Вперед</button>';
		}
		$html .= '</div>';
	}
}

JsonResponse::send([
	'success' => true,
	'html' => $html,
	'total' => $totalListings,
	'page' => $page,
	'total_pages' => $totalPages
]);
