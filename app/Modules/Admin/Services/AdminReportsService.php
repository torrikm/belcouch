<?php

class AdminReportsService
{
	private Database $db;

	public function __construct()
	{
		$this->db = new Database();
	}

	public function getStats(): array
	{
		$stats = [
			'total' => 0,
			'active' => 0,
			'resolved' => 0,
		];

		$stmt = $this->db->query("SELECT COUNT(*) as total FROM listing_reports");
		$stats['total'] = (int) $stmt->fetch_assoc()['total'];

		$stmt = $this->db->query("SELECT COUNT(*) as active FROM listing_reports WHERE status = 'active' OR status IS NULL");
		$stats['active'] = (int) $stmt->fetch_assoc()['active'];

		$stats['resolved'] = $stats['total'] - $stats['active'];

		return $stats;
	}

	public function getReports(): array
	{
		$sql = "SELECT lr.*,
				l.title as listing_title,
				l.user_id as listing_owner_id,
				u1.id as reporter_id,
				u1.first_name as reporter_first_name,
				u1.last_name as reporter_last_name,
				u1.email as reporter_email,
				u2.first_name as listing_owner_first_name,
				u2.last_name as listing_owner_last_name,
				u2.email as listing_owner_email
				FROM listing_reports lr
				JOIN listings l ON lr.listing_id = l.id
				JOIN users u1 ON lr.reporter_id = u1.id
				JOIN users u2 ON l.user_id = u2.id
				ORDER BY lr.created_at DESC";

		$result = $this->db->query($sql);

		$reports = [];
		while ($row = $result->fetch_assoc()) {
			$reports[] = $row;
		}

		return $reports;
	}

	public function deleteListing(int $listingId): bool
	{
		$stmt = $this->db->prepareAndExecute('DELETE FROM listings WHERE id = ?', 'i', [$listingId]);
		return $stmt->affected_rows > 0;
	}

	public function resolveReport(int $reportId, string $resolution = 'dismissed'): bool
	{
		$stmt = $this->db->prepareAndExecute(
			"UPDATE listing_reports SET status = 'resolved', resolution = ?, resolved_at = NOW() WHERE id = ?",
			'si',
			[$resolution, $reportId]
		);

		return $stmt->affected_rows > 0;
	}
}
