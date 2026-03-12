<?php
require_once __DIR__ . '/../bootstrap.php';

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
if ($query === '') {
	JsonResponse::send(['success' => true, 'results' => []]);
}

$listingsApiService = new ListingsApiService();
$listings = $listingsApiService->getSearchListings($query);

JsonResponse::send(['success' => true, 'results' => $listings]);
