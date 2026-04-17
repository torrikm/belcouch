<?php
require_once __DIR__ . '/../../bootstrap.php';

class GetReportReasonsAction
{
	public function handle(): void
	{
		$db = new Database();
		$stmt = $db->prepareAndExecute(
			'SELECT code, label FROM listing_report_reasons WHERE is_active = 1 ORDER BY sort_order ASC',
			'',
			[]
		);
		$result = $stmt->get_result();
		$reasons = [];

		while ($row = $result->fetch_assoc()) {
			$reasons[] = [
				'code' => $row['code'],
				'label' => $row['label']
			];
		}

		JsonResponse::send([
			'success' => true,
			'reasons' => $reasons
		]);
	}
}

$action = new GetReportReasonsAction();
$action->handle();
