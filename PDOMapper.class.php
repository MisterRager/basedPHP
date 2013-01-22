<?php
namespace basedPHP;
/**	PDOMapper.class.php
 * 	class		PDOMapper
 * 	author		Alan Rager
 * 	created		Jan 17, 2013
 *
 * 	This is the parent class for the DB Mappers. It is dependent on
 * 	the PDO libraries and will probably be very MySQL specific. It
 * 	takes a PDO object in the constructor with which it will make
 * 	all necessary queries.
 */

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'DBExceptions.php');

abstract class PDOMapper {

	protected $db;

	public function __construct(PDO $db) {
		$this->db = $db;
	}

	abstract protected function getModelName();
	abstract protected function getTableName();

	//Utility function to perform all the things
	protected function executeQuery($sql, $args) {
		$query = $this->db->prepare($sql);
		$query->execute($args);

		return $query;
	}

	//CRUD

	public function save(MappedObject $ob) {
		$ob->__beforeSave();
		$model_name = $this->getModelName();
		if(!is_object($ob) || !($ob instanceof $model_name)) {
			$classname = get_class($ob);
			throw new MapperException("Cannot save object of type $classname with the $model_name mapper");
		}

		$table = $this->getTableName();
		$fields = $ob->getUpdatedFields();

		//Don't ever let the id field be "set"
		if(array_key_exists('id', $fields)) {
			unset($fields['id']);
		}

		$fieldnames = array_keys($fields);

		//If it's an object that has already been saved, id > 0
		if($ob->id > 0) {
			$sql = "UPDATE `$table` SET ";

			$args = array();
			$set_strings = array();

			foreach($fields as $key=>$val) {
				$set_strings[] =  "`$key`=:$key";
				$args[":$key"] = $val;
			}

			$args[':id'] = $ob->id;

			$sql .= implode(',', $set_strings)." WHERE id=:id";
		} else {
			$sql = "INSERT INTO `$table` ";

			//Add the field names to the query
			$sql .= '(`'.implode('`,`', $fieldnames).'`) ';

			//Add the value placeholders into the query
			$sql .= 'VALUES(:'.implode(',:', $fieldnames).') ';

			//Create the arg array
			$args = array();
			foreach($fields as $field=>$val) {
				$args[":$field"] = $val;
			}
		}

		$this->executeQuery($sql, $args);
		$ob->id = $this->db->lastInsertId();

		$ob->__afterSave();
		return $ob->id;
	}

	public function trash(MappedObject $ob) {
		$ob->__beforeTrash();
		$query = $this->executeQuery('DELETE FROM '.$this->getTableName().' WHERE id=:id',
			array(
				':id' => $ob->id
			)
		);
		$ob->__afterTrash();
		$ob->id = -1;
		return $query->rowCount() > 0;
	}

	protected function queryObjects($sql, $args) {
		$st = $this->executeQuery($sql, $args);

		$out = array();
		$model = $this->getModelName();

		while($next = $st->fetch(PDO::FETCH_ASSOC)) {
			$out[$next['id']] = new $model($next);
		}

		return $out;
	}

	protected function queryObject($sql, $args) {
		$st = $this->executeQuery($sql, $args);

		$out = array();
		$model = $this->getModelName();

		$data = $st->fetch(PDO::FETCH_ASSOC);
		if(is_array($data)) {
			return new $model($data);
		}
		return NULL;
	}

	public function fetch($id) {
		return $this->queryObject(
			'SELECT * FROM '.$this->getTableName().' WHERE id=:id',
			array(':id' => $id)
		);
	}

	public function fetchBatch(array $ids) {
		//Make sure empty arrays/non-arrays don't mess up the sql statement
		if(count($ids) === 0 || !is_array($ids)) {
			return array();
		}

		return $this->queryObjects(
			'SELECT * FROM '.$this->getTableName().' WHERE id IN('.
				implode(',', array_fill(0, count($ids), '?')).')',
			array_values($ids)
		);
	}
}

