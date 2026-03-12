<?php
class SubmitVerificationRequestAction
{
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            JsonResponse::send(['success' => false, 'message' => 'Метод не поддерживается'], 405);
        }

        if (!isset($_SESSION['user_id'])) {
            JsonResponse::send(['success' => false, 'message' => 'Пользователь не авторизован'], 401);
        }

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            JsonResponse::send(['success' => false, 'message' => 'Недействительный CSRF токен'], 419);
        }

        try {
            $service = new AdminVerificationService();
            $service->submitRequest((int) $_SESSION['user_id'], $_FILES['document_photo'] ?? []);
            JsonResponse::send(['success' => true, 'message' => 'Заявка на верификацию отправлена']);
        } catch (Exception $exception) {
            JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
