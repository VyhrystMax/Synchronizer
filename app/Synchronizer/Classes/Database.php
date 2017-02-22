<?php
/**
 * Created by PhpStorm.
 * User: maks
 * Date: 20.01.17
 * Time: 13:25
 */

namespace Synchronizer\Classes;

use Simplon\Mysql\Mysql;

class Database {

    use Log;

	private $connection;

	private $fields;

    private $table;

    private $ag_table;

	public function __construct(Mysql $connection, $table, $ag_table)
	{
		$this->connection = $connection;
		$this->fields = "id, aw_campaign_id, aw_adgroup_id, aw_textad_id, title, line1, line2, url, display_url, enabled, keywords, event_date, trash, last_modified, last_sync, paused";
		$this->table = $table;
        $this->ag_table = $ag_table;
	}

	public function getData()
	{
		$query = "SELECT $this->fields FROM $this->table WHERE
		last_modified > last_sync AND
		aw_campaign_id > :aw_campaign_id AND
		aw_adgroup_id > :aw_adgroup_id AND
		event_date > :event_date
		ORDER BY aw_adgroup_id DESC";

		$params = [
			'aw_campaign_id' => 0,
			'aw_adgroup_id' => 0,
			'event_date' => time()
		];

		return $this->execute($query, $params);
	}

	public function getAdGroupsForCreate()
    {
        $query = $query = "SELECT $this->fields, event_id FROM $this->table WHERE
		aw_adgroup_id IS NULL AND
		aw_campaign_id > :aw_campaign_id AND
		event_date > :event_date AND event_id > :event_id
		ORDER BY aw_adgroup_id DESC";

        $params = [
            'aw_campaign_id' => 0,
            'event_id' => 0,
            'event_date' => time()
        ];

        return $this->execute($query, $params);
    }

	public function getDataToRemove()
	{
		$query = "SELECT $this->fields FROM $this->table WHERE
		last_modified > last_sync AND aw_campaign_id > :aw_campaign_id
		AND aw_adgroup_id > :aw_adgroup_id AND event_date < :event_date
		ORDER BY aw_adgroup_id DESC";

		$params = [
			'aw_campaign_id' => 0,
			'aw_adgroup_id' => 0,
			'event_date' => time()
		];

		return $this->execute($query, $params);
	}

	private function execute($query, $params)
	{
		$rows = $this->connection->fetchRowMany($query, $params);

		return is_array($rows) && !empty($rows) ? $rows : false;
	}

	public function remove($id)
	{
        $result = $this->connection->delete(
            $this->table,
            ['aw_adgroup_id' => $id],
            'aw_adgroup_id = :aw_adgroup_id'
        );

        if ($result)
            $this->log('AdGroup: ' . $id .
                       ' was successfully removed from database', 'info');
	}

    public function setAdGroupsToDB($data)
    {
        foreach ($data as $event_id => $item) {
            $this->connection->update(
                $this->table,
                ['event_id' => $event_id],
                [
                    'aw_adgroup_id' => $item['adw_group_id'],
                    'last_sync' => '2000-01-01 12:00:00'
                ],
                'event_id = :event_id'
            );

            $this->connection->insert(
                $this->ag_table,
                $item
            );

            $this->log('Ad Group: ' . $item['name'] .
                       ' with id: ' . $item['adw_group_id'] .
                       ' was successfully saved to DB' , 'info');
        }
	}

    public function update($id)
    {
        $last_sync = date('Y-m-d H:i:s');
        $value = ['last_sync' => $last_sync];

        $this->connection->update(
            $this->table,
            ['aw_adgroup_id' => $id],
            $value,
            'aw_adgroup_id = :aw_adgroup_id'
        );
	}

	public function setAdId($dbId, $adId)
    {
	    $this->connection->update(
	        $this->table,
            ['id' => $dbId],
            ['aw_textad_id' => $adId],
            'id = :id'
        );
    }

}