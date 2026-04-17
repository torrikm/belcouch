<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/render_listing_card.php';

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
		$isFavorite = isset($_SESSION['user_id']) && in_array((int) $listing['id'], $userFavorites, true);
		$html .= renderListingCard($listing, [
			'is_favorite' => $isFavorite,
			'root_path' => '',
		]);
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
