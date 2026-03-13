<?php

class SendMessageAction
{
	public function handle(): void
	{
		if (!isset($_SESSION['user_id'])) {
			JsonResponse::send(['success' => false, 'message' => 'Требуется авторизация'], 401);
		}

		$partnerId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
		$listingId = isset($_POST['listing_id']) && $_POST['listing_id'] !== ''
			? (int) $_POST['listing_id']
			: null;
		$replyToMessageId = isset($_POST['reply_to_message_id']) && $_POST['reply_to_message_id'] !== ''
			? (int) $_POST['reply_to_message_id']
			: null;
		$isSupport = !empty($_POST['support']);
		$message = trim($_POST['message'] ?? '');
		$attachments = [];

		if (isset($_FILES['media'])) {
			$fileNames = $_FILES['media']['name'] ?? [];
			$fileTmpNames = $_FILES['media']['tmp_name'] ?? [];
			$fileErrors = $_FILES['media']['error'] ?? [];
			$fileSizes = $_FILES['media']['size'] ?? [];

			if (!is_array($fileNames)) {
				$fileNames = [$fileNames];
				$fileTmpNames = [$_FILES['media']['tmp_name'] ?? ''];
				$fileErrors = [$_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE];
				$fileSizes = [$_FILES['media']['size'] ?? 0];
			}

			$validFileIndexes = [];
			$totalSize = 0;

			foreach ($fileNames as $index => $_) {
				$errorCode = (int) ($fileErrors[$index] ?? UPLOAD_ERR_NO_FILE);
				if ($errorCode === UPLOAD_ERR_NO_FILE) {
					continue;
				}

				if ($errorCode !== UPLOAD_ERR_OK) {
					JsonResponse::send(['success' => false, 'message' => 'Ошибка при загрузке файла'], 422);
				}

				$validFileIndexes[] = $index;
				$totalSize += (int) ($fileSizes[$index] ?? 0);
			}

			if (count($validFileIndexes) > 5) {
				JsonResponse::send(['success' => false, 'message' => 'Можно прикрепить не более 5 файлов'], 422);
			}

			if ($totalSize > 100 * 1024 * 1024) {
				JsonResponse::send(['success' => false, 'message' => 'Максимальный общий размер вложений — 100 МБ'], 422);
			}

			if (!empty($validFileIndexes)) {
				$uploadDir = __DIR__ . '/../../../../uploads/chat_images/';
				if (!is_dir($uploadDir)) {
					mkdir($uploadDir, 0777, true);
				}

				$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'];

				foreach ($validFileIndexes as $index) {
					$fileName = (string) ($fileNames[$index] ?? '');
					$fileTmpPath = (string) ($fileTmpNames[$index] ?? '');
					$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

					if (!in_array($fileExtension, $allowedExtensions, true)) {
						JsonResponse::send(['success' => false, 'message' => 'Недопустимый формат файла'], 422);
					}

					$newFileName = uniqid('chat_', true) . '.' . $fileExtension;
					$destPath = $uploadDir . $newFileName;

					if (!move_uploaded_file($fileTmpPath, $destPath)) {
						JsonResponse::send(['success' => false, 'message' => 'Ошибка при загрузке файла'], 500);
					}

					$attachments[] = [
						'file_path' => '/uploads/chat_images/' . $newFileName,
						'file_type' => in_array($fileExtension, ['mp4', 'webm', 'mov'], true) ? 'video' : 'image',
					];
				}
			}
		}

		try {
			$currentUserId = (int) $_SESSION['user_id'];
			$service = new ChatService();
			$sentMessage = $service->sendMessage($currentUserId, $partnerId, $message, $listingId, $replyToMessageId, $attachments, $isSupport);

			$notifyUserIds = [$partnerId, $currentUserId];
			if ($isSupport) {
				$db = new Database();
				$rows = $db->getAll("SELECT id FROM users WHERE role = 'admin'");
				$notifyUserIds = array_map(static function (array $row): int {
					return (int) ($row['id'] ?? 0);
				}, $rows);
				$notifyUserIds[] = $partnerId;
				$notifyUserIds[] = $currentUserId;
			}

			ChatRealtimeNotifier::notifyUsers(
				$notifyUserIds,
				[
					'user_id' => $currentUserId,
					'partner_id' => $partnerId,
					'is_support' => $isSupport,
					'message_id' => (int) $sentMessage['id'],
				],
				'chat:message_created'
			);

			JsonResponse::send([
				'success' => true,
				'message_item' => $sentMessage,
			]);
		} catch (InvalidArgumentException $exception) {
			JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
		} catch (Throwable $exception) {
			JsonResponse::send(['success' => false, 'message' => 'Не удалось отправить сообщение'], 500);
		}
	}
}
