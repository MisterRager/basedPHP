<?php
/**	TVShowDao.class.php
 * 	class		TVShowDao
 * 	author		Alan Rager
 * 	created		Jan 17, 2013
 *
 * 	This is a DB Abstraction class to fetch TVShow objects from the database
 */

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'PDOMapper.class.php');
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'TVShow.class.php');

class TVShowDao extends PDOMapper {

	//From parent's abstract methods
	protected function getModelName() {
		return 'TVShow';
	}

	protected function getTableName() {
		return 'tv_show';
	}

	public function fetchPage($page_num = 0, $page_size = 50) {
		$query = $this->db->prepare(
			'SELECT * FROM tv_show LIMIT :off,:rows'
		);
		$query->bindValue(':off', intval($page_num * $page_size));
		$query->bindValue(':rows', intval($page_size));
		$query->execute();

		$out = array();
		while($next = $query->fetch(PDO::FETCH_ASSOC)) {
			$out[] = new TVShow($next);
		}

		return $out;
	}

	public function fetchByConsumer(TVConsumer $con, $page_num = 0, $page_size = 50) {
		$query = $this->db->prepare(
			'SELECT s.* FROM tv_show s '.
			'JOIN tv_consumer_show u ON s.id=u.show_id '.
			'WHERE u.consumer_id=:cid '.
			'LIMIT :off,:rows'
		);
		$query->bindValue(':off', intval($page_num * $page_size));
		$query->bindValue(':rows', intval($page_size));
		$query->bindValue(':cid', intval($con->id));
		$query->execute();

		$out = array();
		while($next = $query->fetch(PDO::FETCH_ASSOC)) {
			$out[] = new TVShow($next);
		}

		return $out;

	}

	public function fetchByLineup(TVLineup $lin, $min_points = 0, $page_num = 0, $page_size = 50) {
		$query = $this->db->prepare(
			'SELECT s.* FROM tv_show s '.
			'JOIN tv_airdate a ON s.id=a.show_id '.
			'JOIN tv_lineup_station l ON a.station_id=l.station_id '.
			'WHERE l.lineup_id=:lin AND s.points_worth>=:pts LIMIT :off,:num'
		);
		$query->bindValue(':off', intval($page_num * $page_size));
		$query->bindValue(':num', intval($page_size));
		$query->bindValue(':lin', intval($lin->id));
		$query->bindValue(':pts', intval($min_points));
		$query->execute();

		$out = array();
		while($next = $query->fetch(PDO::FETCH_ASSOC)) {
			$out[] = new TVShow($next);
		}

		return $out;
	}

	public function fetchTmsShow($tmsid) {
		return $this->queryObject(
			'SELECT * FROM tv_show WHERE tms_show_id=:id LIMIT 1',
			array(':id' => $tmsid)
		);
	}

	public function fetchShowByTitle($name) {
		return $this->queryObject(
			'SELECT * FROM tv_show WHERE title=:name LIMIT 1',
			array(':name' => $name)
		);
	}

	public function searchByTitle($query) {
		return $this->queryObjects(
			'SELECT * FROM tv_show WHERE title LIKE :q LIMIT 200',
			array(':q' => "%$query%")
		);
	}
}
