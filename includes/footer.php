</main>

<?php $root_path = isset($root_path) ? $root_path : ''; ?>


<footer class="site-footer">
	<div class="footer-container">
		<div class="footer-logo">
			<a href="<?php echo SITE_URL; ?>" class="logo">
				<img src="<?php echo $root_path; ?>assets/img/icons/logo.svg" alt="BelCouch Logo" class="logo-img">

			</a>
		</div>

		<div class="footer-copyright">
			&copy; <?php echo date('Y'); ?> BelCouch Белорусский сайт каучсёрфинга
		</div>
	</div>
</footer>

<button
	id="global-scroll-top"
	class="global-scroll-top"
	type="button"
	aria-label="Вернуться наверх"
	title="Наверх"
>
	↑
</button>

<!-- Модальное окно входа -->
<div id="login-modal" class="modal-overlay">
	<div class="modal auth-modal">
		<div class="modal-header">
			<h2 class="modal-title">Вход</h2>
			<button class="modal-close" data-auth-close="login-modal">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<div class="modal-body">
			<div id="login-error" class="error-message" style="display: none; text-align: center; margin-bottom: 15px; color: #d9534f;"></div>
			<form id="login-form">
				<div class="form-group">
					<label for="login-email">E-mail</label>
					<input type="email" id="login-email" name="email" class="form-control" placeholder="E-mail">
					<div id="login-email-error" class="error-message" style="display: none; color: #d9534f; font-size: 14px; margin-top: 5px;"></div>
				</div>
				<div class="form-group">
					<label for="login-password">Пароль</label>
					<input type="password" id="login-password" name="password" class="form-control" placeholder="Пароль">
					<div id="login-password-error" class="error-message" style="display: none; color: #d9534f; font-size: 14px; margin-top: 5px;"></div>
				</div>
				<button type="submit" class="btn-save" style="width: 100%; margin-top: 15px;">Войти</button>
			</form>
			<div class="auth-link-container" style="text-align: center; margin-top: 15px;">
				<span class="auth-text" style="color: #666;">Ещё нет аккаунта?</span>
				<a href="#" data-auth-switch-close="login-modal" data-auth-switch-open="register-modal" class="auth-link" style="color: #1d358b; text-decoration: none; font-weight: 500;">Зарегистрироваться</a>

			</div>
		</div>
	</div>
</div>

<!-- Модальное окно регистрации -->
<div id="register-modal" class="modal-overlay">
	<div class="modal auth-modal">
		<div class="modal-header">
			<h2 class="modal-title">Регистрация</h2>
			<button class="modal-close" data-auth-close="register-modal">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<div class="modal-body">
			<div id="register-error" class="error-message" style="display: none; text-align: center; margin-bottom: 15px; color: #d9534f;"></div>
			<div id="register-success" class="success-message" style="display: none; text-align: center; margin-bottom: 15px; color: #2ecc71;"></div>
			<form id="register-form">
				<div class="form-group">
					<label for="register-email">Почта</label>
					<input type="email" id="register-email" name="email" class="form-control" placeholder="E-mail">
					<div id="register-email-error" class="error-message" style="display: none; color: #d9534f; font-size: 14px; margin-top: 5px;"></div>
				</div>
				<div class="form-group">
					<label for="register-first-name">Имя</label>
					<input type="text" id="register-first-name" name="first_name" class="form-control" placeholder="Имя">
					<div id="register-first-name-error" class="error-message" style="display: none; color: #d9534f; font-size: 14px; margin-top: 5px;"></div>
				</div>
				<div class="form-group">
					<label for="register-password">Пароль</label>
					<input type="password" id="register-password" name="password" class="form-control" placeholder="Пароль">
					<div class="password-requirements" style="font-size: 12px; color: #666; margin-top: 5px;">Пароль должен содержать минимум 8 символов, включая буквы и цифры</div>
					<div id="register-password-error" class="error-message" style="display: none; color: #d9534f; font-size: 14px; margin-top: 5px;"></div>
				</div>
				<div class="form-group">
					<label for="register-confirm-password">Повторите пароль</label>
					<input type="password" id="register-confirm-password" name="confirm_password" class="form-control" placeholder="Повторите пароль">
					<div id="register-confirm-password-error" class="error-message" style="display: none; color: #d9534f; font-size: 14px; margin-top: 5px;"></div>
				</div>
				<button type="submit" class="btn-save" style="width: 100%; margin-top: 15px;">Зарегистрироваться</button>
			</form>
			<div class="auth-link-container" style="text-align: center; margin-top: 15px;">
				<span class="auth-text" style="color: #666;">Уже есть аккаунт?</span>
				<a href="#" data-auth-switch-close="register-modal" data-auth-switch-open="login-modal" class="auth-link" style="color: #1d358b; text-decoration: none; font-weight: 500;">Войти</a>
			</div>
		</div>
	</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="<?php echo $root_path; ?>assets/js/config.js"></script>
<script src="<?php echo $root_path; ?>assets/js/core/app.js"></script>
<script src="<?php echo $root_path; ?>assets/js/core/dom.js"></script>
<script src="<?php echo $root_path; ?>assets/js/core/api.js"></script>
<script src="<?php echo $root_path; ?>assets/js/core/auth.js"></script>
<script src="<?php echo $root_path; ?>assets/js/core/notify.js"></script>
<script src="<?php echo $root_path; ?>assets/js/city-autocomplete.js"></script>
<script src="<?php echo $root_path; ?>assets/js/mobile-menu.js"></script>
<script src="<?php echo $root_path; ?>assets/js/modal.js"></script>
<script src="<?php echo $root_path; ?>assets/js/scroll-top.js"></script>

<script src="<?php echo $root_path; ?>assets/js/auth.js"></script>
<script src="<?php echo $root_path; ?>assets/js/custom-select.js"></script>
<script src="<?php echo $root_path; ?>assets/js/global-presence.js"></script>

<?php if (isset($additionalJs) && is_array($additionalJs)): ?>
	<?php foreach ($additionalJs as $js): ?>
		<script src="<?php echo $js; ?>"></script>
	<?php endforeach; ?>
<?php endif; ?>
</body>

</html>
