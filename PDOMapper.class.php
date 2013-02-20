<?php
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

//Because I can't really trust the servers to have a timezone set...
date_default_timezone_set('UTC');
require_once(dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'include_core.php');
core_class('db/DBExceptions.php');

abstract class PDOMapper {

	protected $db;
	protected $fields = array();
	protected $required_fields = array();

	public function __construct(PDO $db) {
		$this->db = $db;
	}

	abstract protected function getModelName();
	abstract protected function getTableName();

	//Utility function to perform all the things
	protected function executeQuery($sql, $args = array()) {
		//Don't prepare unless args are given
		if(empty($args)) {
			return $this->db->query($sql);
		}

		try {
			$query = $this->db->prepare($sql);
			$query->execute($args);

			return $query;
		} catch (PDOException $e) {
			error_log("Erroneous SQL: [$sql]");
			error_log("SQL args: ".json_encode($args));
			throw $e;
		}
	}
	
	public function getFields()
	{
		return $this->fields; 
	}

	//Utility function to transform DateTime into the fromat MySQL wants
	protected function dateToString(DateTime $d) {
		return $d->format('Y-m-d H:i:s');
	}

	protected function getKey() {
		$flip = array_flip($this->fields);
		return isset($flip['key']) ? $flip['key'] : 'id';
	}

	protected function hasRequiredFields($ob) {
		//Make sure that the object has all the fields that are named in required_fields
		$missing = array_diff($this->required_fields,
			array_keys($ob->toArray())
		);

		if(count($missing) > 0) {
			throw new MissingFieldException($this->getModelName(),
				implode(', ', $missing)
			);
		}
		return true;
	}

	protected function verifyField($field, $val) {
		if(!isset($this->fields[$field])) {
			throw new InvalidFieldException($this->getModelName(), $field);
		}

		switch($this->fields[$field]) {
			case 'key':
			case 'int':
				return intval($val);
			case 'string':
				return strval($val);
			case 'float':
				return floatval($val);
			case 'bool':
				return (bool) $val;
			case 'time':
				if(is_int($val) || is_numeric($val)) {
					return new DateTime("@$val");
				}
				if(!$val instanceof DateTime) {
					return new DateTime($val);
				}
				return $val;
		}
	}

	protected function verifyFields($data) {
		$out = array();

		foreach($data as $field=>$val) {
			if(!isset($this->fields[$field])) {
				continue;
			}
			$out[$field] = $this->verifyField($field, $val);
		}

		return $out;
	}

	//CRUD

	public function tableSql() {
		$sql = 'CREATE TABLE `'.$this->getTableName().'` (';

		$field_sql = array();
		$constraint_sql = array();

		foreach($this->fields as $field=>$type) {
			switch($type) {
				case 'key':
					$row = "`$field` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT";
					$constraint_sql[] = "PRIMARY KEY (`$field`)";
					break;
				case 'int':
					$row = "`$field` INT(10)";
					break;
				case 'string':
					$row = "`$field` VARCHAR(255)";
					break;
				case 'bool':
					$row = "`$field` TINYINT(1)";
					break;
				case 'time':
					$row = "`$field` DATETIME";
					break;
			}
			if(in_array($field, $this->required_fields)) {
				$row .= ' NOT NULL';
			} else {
				$row .= ' DEFAULT NULL';
			}
			$field_sql[] = $row;
		}

		$sql .= implode(',', $field_sql);

		if(!empty($constraint_sql)) {
			$sql .= ', '.implode(',', $constraint_sql);
		}
		$sql .= ');';
		return $sql;
	}

	public function saveBatch(array $obs) {
		if(empty($obs)) {
			return 0;
		}

		$newobs = array();
		$table = $this->getTableName();
		$model = $this->getModelName();

		$sql = "REPLACE INTO `$table` ";
		$fields = false;

		$value_fillers = array();
		$args = array();

		foreach($obs as $ob) {
			try {
				$this->hasRequiredFields($ob);
			} catch(MissingFieldException $e) {
				//Maybe throw exception?
				error_log($e->getMessage());
			}

			if(!($ob instanceof $model)) {
				throw new MapperException("Cannot save object of type $classname with the $model_name mapper");
			}
			if($ob->{$this->getKey()} > 0) {
				$this->save($ob);
			} else {
				if(!$fields) {
					//Add the fields to the insert query
					$fields = array();
					foreach($ob as $field=>$val) {
						if($field !== $this->getKey()) {
							$fields[] = $field;
						}
					}
					$sql .= '(`'.implode('`,`',$fields).'`) VALUES ';
				}

				$value_fillers[] = '('.implode(',',array_fill(0,count($fields), '?')).') ';

				foreach($fields as $field) {
					$args[] = $ob->{$field};
				}
			}
		}

		$sql .= implode(',', $value_fillers);

		$q = $this->executeQuery($sql, $args);
		return $q->rowCount();
	}

	public function save(MappedObject $ob) {
		$ob->__beforeSave();
		$this->hasRequiredFields($ob);
		$model_name = $this->getModelName();
		if(!($ob instanceof $model_name)) {
			$classname = get_class($ob);
			throw new MapperException("Cannot save object of type $classname with the $model_name mapper");
		}

		$table = $this->getTableName();

		$id = $ob->{$this->getKey()};

		if($id > 0) {
			$fields = $ob->getUpdatedFields();
		} else {
			$fields = $ob->toArray();
		}

		if(count($fields) === 0) {
			return $ob->{$this->getKey()};
		}

		//Don't ever let the id field be "set"
		if(array_key_exists($this->getKey(), $fields)) {
			unset($fields[$this->getKey()]);
		}

		$fieldnames = array_keys($fields);

		//Process things that can't go into the database as-is
		foreach($fieldnames as $field) {
			//Format DateTime objects to fit what MySQL expects
			if($fields[$field] instanceof DateTime) {
				$fields[$field] = $this->dateToString($fields[$field]);
			}
		}

		//If it's an object that has already been saved, id > 0
		if($ob->{$this->getKey()} > 0) {
			$sql = "UPDATE `$table` SET ";

			$args = array();
			$set_strings = array();

			foreach($fields as $key=>$val) {
				$set_strings[] =  "`$key`=:$key";
				$args[":$key"] = $val;
			}

			$args[':id'] = $ob->{$this->getKey()};

			$sql .= implode(',', $set_strings).' WHERE `'.$this->getKey().'`=:id';
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
		$insertid = $this->db->lastInsertId();
		if($insertid > 0) {
			$ob->{$this->getKey()} = $insertid;
		}

		$ob->__afterSave();
		return $ob->{$this->getKey()};
	}

	public function trash(MappedObject $ob) {
		$ob->__beforeTrash();
		$query = $this->executeQuery('DELETE FROM '.$this->getTableName().' WHERE `'.$this->getKey().'`=:id',
			array(
				':id' => $ob->{$this->getKey()}
			)
		);
		$ob->__afterTrash();
		$ob->{$this->getKey()} = -1;
		return $query->rowCount() > 0;
	}

	protected function queryObjects($sql, array $args = array()) {
		$st = $this->executeQuery($sql, $args);

		$out = array();
		$model = $this->getModelName();

		while($next = $st->fetch(PDO::FETCH_ASSOC)) {
			$next = $this->verifyFields($next);
			$out[$next['id']] = new $model($next);
		}

		return $out;
	}

	protected function queryObject($sql, array $args = array()) {
		$st = $this->executeQuery($sql, $args);

		$out = array();
		$model = $this->getModelName();

		$data = $st->fetch(PDO::FETCH_ASSOC);
		if(is_array($data)) {
			$data = $this->verifyFields($data);
			return new $model($data);
		}
		return NULL;
	}

	public function fetch($id) {
		return $this->queryObject(
			'SELECT * FROM `'.$this->getTableName().'` WHERE `'.$this->getKey().'`=:id',
			array(':id' => $id)
		);
	}

	public function fetchBatch(array $ids) {
		//Make sure empty arrays/non-arrays don't mess up the sql statement
		if(count($ids) === 0 || !is_array($ids)) {
			return array();
		}

		return $this->queryObjects(
			'SELECT * FROM `'.$this->getTableName().'` WHERE `'.$this->getKey().'` IN('.
				implode(',', array_fill(0, count($ids), '?')).')',
			array_values($ids)
		);
	}

        public function fetchPage($page_num = 0, $page_size = 50) {
                $query = $this->db->prepare(
                        'SELECT * FROM `'.$this->getTableName().'` LIMIT :off,:rows'
                );
                $query->bindValue(':off', intval($page_num * $page_size));
                $query->bindValue(':rows', intval($page_size));
                $query->execute();

                $out = array();
		$model = $this->getModelName();

                while($next = $query->fetch(PDO::FETCH_ASSOC)) {
                        $out[] = new $model($next);
                }

                return $out;
        }
}

