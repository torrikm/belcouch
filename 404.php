<?php
require_once __DIR__ . '/bootstrap.php';

http_response_code(404);

include 'includes/header.php';
?>
<div class="container">
	<section class="container-404">
		<h1>404</h1>
		<p>Извините, страница не найдена.<br>Возможно, она была удалена или перемещена.</p>
		<a href="/">На главную</a>
	</section>
</div>

<?php include 'includes/footer.php'; ?>