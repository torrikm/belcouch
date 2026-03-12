<?php
require_once __DIR__ . '/../bootstrap.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
	http_response_code(404);
	exit('Изображение не найдено');
}

$id = (int) $_GET['id'];
$listingsApiService = new ListingsApiService();
$image = $listingsApiService->getSliderImage($id);

if ($image !== null) {
	header('Content-Type: image/jpeg');
	header('Cache-Control: max-age=86400');
	echo $image;
	exit;
}

http_response_code(404);
exit('Изображение не найдено');
