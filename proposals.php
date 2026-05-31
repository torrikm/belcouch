<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/render_listing_card.php';

$proposalsService = new ProposalsPageService();
$pageData = $proposalsService->getPageData();

$filters = $pageData['filters'];
$pagination = $pageData['pagination'];
$listings = $pageData['listings'];
$user_favorites = $pageData['user_favorites'];
$property_types = $pageData['property_types'];
$regions = $pageData['regions'];
$stay_durations = $pageData['stay_durations'];
$amenities = $pageData['amenities'];
$rules = $pageData['rules'];

$property_type_id = $filters['property_type_id'];
$min_guests = $filters['min_guests'];
$max_guests = $filters['max_guests'];
$stay_duration_id = $filters['stay_duration_id'];
$region_id = $filters['region_id'];
$city = $filters['city'];
$has_amenities = $filters['has_amenities'];
$has_rules = $filters['has_rules'];
$sort = $filters['sort'] ?? 'newest';
$page = $pagination['page'];
$total_listings = $pagination['total_listings'];
$total_pages = $pagination['total_pages'];

$title = 'Предложения - BelCouch';
$additionalCss = ['assets/css/proposals.css', 'assets/css/favorites.css'];
$additionalJs = [
	'assets/js/filter.js',
	'assets/js/favorites.js',
	'assets/js/proposals-filters-modal.js',
	'assets/js/listing-card-link.js',
];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
	<h1 class="page-title">Предложения жилья</h1>

	<button class="filters-mobile-button" id="open-filters-modal"><i class="fas fa-filter"></i> Показать
		фильтры</button>

	<div class="proposals-container">
		<div class="listings-content">
			<?php if (empty($listings)): ?>
				<div class="no-listings">
					<p>По вашему запросу ничего не найдено. Попробуйте изменить параметры фильтра.</p>
				</div>
			<?php else: ?>
				<div class="total-count">
					<p>Найдено предложений: <strong><?php echo $total_listings; ?></strong></p>
				</div>

				<div class="listings-list">
					<?php foreach ($listings as $listing): ?>
						<?php
						$isFavorite = isset($_SESSION['user_id']) && in_array((int) $listing['id'], $user_favorites, true);
						echo renderListingCard($listing, [
							'is_favorite' => $isFavorite,
							'root_path' => '',
						]);
						?>
					<?php endforeach; ?>
				</div>

				<?php if ($total_pages > 1): ?>
					<div class="pagination">
						<?php
						$get_params = $_GET;
						unset($get_params['page']);
						$query_string = http_build_query($get_params);
						$base_url = 'proposals.php?' . ($query_string ? $query_string . '&' : '');

						if ($page > 1):
						?>
							<a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" class="page-link">&laquo; Назад</a>
						<?php endif; ?>

						<?php
						$range = 2;
						$start_page = max(1, $page - $range);
						$end_page = min($total_pages, $page + $range);

						if ($start_page > 1):
						?>
							<a href="<?php echo $base_url; ?>page=1" class="page-link">1</a>
							<?php if ($start_page > 2): ?>
								<span class="page-dots">...</span>
							<?php endif; ?>
						<?php endif; ?>

						<?php for ($i = $start_page; $i <= $end_page; $i++): ?>
							<a href="<?php echo $base_url; ?>page=<?php echo $i; ?>"
								class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
								<?php echo $i; ?>
							</a>
						<?php endfor; ?>

						<?php if ($end_page < $total_pages): ?>
							<?php if ($end_page < $total_pages - 1): ?>
								<span class="page-dots">...</span>
							<?php endif; ?>
							<a href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>"
								class="page-link"><?php echo $total_pages; ?></a>
						<?php endif; ?>

						<?php if ($page < $total_pages): ?>
							<a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" class="page-link">Вперед &raquo;</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<div class="filters-sidebar">
			<div class="filters-header">
				<h3>Фильтры</h3>
				<a href="proposals" class="reset-filters">Сбросить</a>
			</div>

			<form id="filters-form" method="post" action="javascript:void(0);">
				<div class="filter-group">
					<h4>Сортировка</h4>
					<select name="sort" class="form-control">
						<option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Сначала новые</option>
						<option value="user_rating_desc" <?php echo $sort === 'user_rating_desc' ? 'selected' : ''; ?>>По рейтингу пользователя</option>
						<option value="listing_rating_desc" <?php echo $sort === 'listing_rating_desc' ? 'selected' : ''; ?>>По рейтингу жилья</option>
					</select>
				</div>

				<div class="filter-group">
					<h4>Тип жилья</h4>
					<select name="property_type" class="form-control">
						<option value="0">Все типы</option>
						<?php foreach ($property_types as $type): ?>
							<option value="<?php echo $type['id']; ?>" <?php echo ($property_type_id == $type['id']) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($type['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="filter-group">
					<h4>Количество мест</h4>
					<?php
					$sidebar_min = $min_guests ? (int) $min_guests : 1;
					$sidebar_max = $max_guests ? (int) $max_guests : 10;
					?>
					<div class="guest-range" data-min="1" data-max="20">
						<input type="hidden" id="min_guests" name="min_guests" value="<?php echo $sidebar_min; ?>">
						<input type="hidden" id="max_guests" name="max_guests" value="<?php echo $sidebar_max; ?>">
						<input type="range" class="guest-range-input guest-range-min" min="1" max="10" step="1"
							value="<?php echo $sidebar_min; ?>" aria-label="Минимум мест">
						<input type="range" class="guest-range-input guest-range-max" min="1" max="10" step="1"
							value="<?php echo $sidebar_max; ?>" aria-label="Максимум мест">
						<div class="range-labels">
							<span>От: <b class="guest-range-min-value"><?php echo $sidebar_min; ?></b></span>
							<span>До: <b class="guest-range-max-value"><?php echo $sidebar_max; ?></b></span>
						</div>
					</div>
				</div>

				<div class="filter-group">
					<h4>Время пребывания</h4>
					<select name="stay_duration" class="form-control">
						<option value="0">Все варианты</option>
						<?php foreach ($stay_durations as $duration): ?>
							<option value="<?php echo $duration['id']; ?>" <?php echo ($stay_duration_id == $duration['id']) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($duration['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="filter-group">
					<h4>Регион</h4>
					<select name="region" class="form-control">
						<option value="0">Все регионы</option>
						<?php foreach ($regions as $region): ?>
							<option value="<?php echo $region['id']; ?>" <?php echo ($region_id == $region['id']) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($region['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="filter-group">
					<h4>Город</h4>
					<input type="text" name="city" class="form-control" placeholder="Введите название города"
						value="<?php echo htmlspecialchars($city); ?>">
				</div>

				<div class="filter-group amenities-filter">
					<h4>Удобства</h4>
					<div class="filter-checkboxes">
						<?php foreach ($amenities as $amenity): ?>
							<div class="checkbox-item">
								<input type="checkbox" id="amenity-<?php echo $amenity['id']; ?>" name="amenities[]"
									value="<?php echo $amenity['id']; ?>" <?php echo (in_array($amenity['id'], $has_amenities)) ? 'checked' : ''; ?>>
								<label
									for="amenity-<?php echo $amenity['id']; ?>"><?php echo htmlspecialchars($amenity['name']); ?></label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="filter-group rules-filter">
					<h4>Правила</h4>
					<div class="filter-checkboxes">
						<?php foreach ($rules as $rule): ?>
							<div class="checkbox-item">
								<input type="checkbox" id="rule-<?php echo $rule['id']; ?>" name="rules[]"
									value="<?php echo $rule['id']; ?>" <?php echo (in_array($rule['id'], $has_rules)) ? 'checked' : ''; ?>>
								<label
									for="rule-<?php echo $rule['id']; ?>"><?php echo htmlspecialchars($rule['name']); ?></label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>


<div id="filters-modal" class="modal-overlay filters-modal" data-modal-width="500px">
	<div class="modal filters-modal-content">
		<div class="modal-header">
			<h3>Фильтры</h3>
			<button type="button" class="modal-close close-modal">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>

		<form id="mobile-filters-form" method="get" action="proposals.php">
			<div class="filter-group">
				<h4>Сортировка</h4>
				<select name="sort" class="form-control">
					<option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Сначала новые</option>
					<option value="user_rating_desc" <?php echo $sort === 'user_rating_desc' ? 'selected' : ''; ?>>По рейтингу пользователя</option>
					<option value="listing_rating_desc" <?php echo $sort === 'listing_rating_desc' ? 'selected' : ''; ?>>По рейтингу жилья</option>
				</select>
			</div>

			<div class="filter-group">
				<h4>Тип жилья</h4>
				<select name="property_type" class="form-control">
					<option value="0">Все типы</option>
					<?php foreach ($property_types as $type): ?>
						<option value="<?php echo $type['id']; ?>" <?php echo ($property_type_id == $type['id']) ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($type['name']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="filter-group">
				<h4>Количество мест</h4>
				<?php
				$mobile_min = $min_guests ? (int) $min_guests : 1;
				$mobile_max = $max_guests ? (int) $max_guests : 20;
				?>
				<div class="guest-range" data-min="1" data-max="20">
					<input type="hidden" id="mobile_min_guests" name="min_guests" value="<?php echo $mobile_min; ?>">
					<input type="hidden" id="mobile_max_guests" name="max_guests" value="<?php echo $mobile_max; ?>">
					<input type="range" class="guest-range-input guest-range-min" min="1" max="20" step="1"
						value="<?php echo $mobile_min; ?>" aria-label="Минимум мест">
					<input type="range" class="guest-range-input guest-range-max" min="1" max="20" step="1"
						value="<?php echo $mobile_max; ?>" aria-label="Максимум мест">
					<div class="range-labels">
						<span>От: <b class="guest-range-min-value"><?php echo $mobile_min; ?></b></span>
						<span>До: <b class="guest-range-max-value"><?php echo $mobile_max; ?></b></span>
					</div>
				</div>
			</div>

			<div class="filter-group">
				<h4>Время пребывания</h4>
				<select name="stay_duration" class="form-control">
					<option value="0">Все варианты</option>
					<?php foreach ($stay_durations as $duration): ?>
						<option value="<?php echo $duration['id']; ?>" <?php echo ($stay_duration_id == $duration['id']) ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($duration['name']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="filter-group">
				<h4>Регион</h4>
				<select name="region" class="form-control">
					<option value="0">Все регионы</option>
					<?php foreach ($regions as $region_item): ?>
						<option value="<?php echo $region_item['id']; ?>" <?php echo ($region_id == $region_item['id']) ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($region_item['name']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="filter-group">
				<h4>Город</h4>
				<input type="text" name="city" class="form-control" placeholder="Введите название города"
					value="<?php echo htmlspecialchars($city); ?>">
			</div>

			<div class="filter-group">
				<h4>Удобства</h4>
				<div class="checkbox-group">
					<?php foreach ($amenities as $amenity): ?>
						<div class="form-check">
							<input type="checkbox" id="mobile_amenity_<?php echo $amenity['id']; ?>" name="amenities[]"
								value="<?php echo $amenity['id']; ?>" <?php echo (in_array($amenity['id'], $has_amenities)) ? 'checked' : ''; ?> class="form-check-input">
							<label for="mobile_amenity_<?php echo $amenity['id']; ?>"
								class="form-check-label"><?php echo htmlspecialchars($amenity['name']); ?></label>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="filter-group">
				<h4>Правила</h4>
				<div class="checkbox-group">
					<?php foreach ($rules as $rule): ?>
						<div class="form-check">
							<input type="checkbox" id="mobile_rule_<?php echo $rule['id']; ?>" name="rules[]"
								value="<?php echo $rule['id']; ?>" <?php echo (in_array($rule['id'], $has_rules)) ? 'checked' : ''; ?> class="form-check-input">
							<label for="mobile_rule_<?php echo $rule['id']; ?>"
								class="form-check-label"><?php echo htmlspecialchars($rule['name']); ?></label>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="filters-modal-footer">
				<a href="proposals" class="filter-reset-btn">Сбросить</a>
				<button type="submit" class="filter-apply-btn">Применить</button>
			</div>
		</form>
	</div>
</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
