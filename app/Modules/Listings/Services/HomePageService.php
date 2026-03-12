<?php
class HomePageService
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getPageData(): array
    {
        return [
            'slider_images' => $this->db->getAll('SELECT id, title FROM slider_images'),
            'regions_data' => $this->getRegionsData(),
        ];
    }

    private function getRegionsData(): array
    {
        $regions = [];
        $result = $this->db->query('SELECT id, code, name, city FROM regions ORDER BY name');

        while ($region = $result->fetch_assoc()) {
            $countStmt = $this->db->prepareAndExecute('SELECT COUNT(*) as count FROM listings WHERE region_id = ?', 'i', [$region['id']]);
            $countResult = $countStmt->get_result();
            $countData = $countResult->fetch_assoc();
            $region['count'] = (int) $countData['count'];
            $regions[$region['id']] = $region;
        }

        return $regions;
    }
}
