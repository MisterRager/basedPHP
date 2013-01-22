<?php
namespace basedPHP;
/**	MappedObject.class.php
 * 	class		MappedObject
 * 	author		Alan Rager
 * 	created		Jan 17, 2013
 *
 * 	This is an abstract superclass for the objects
 * 	that get persisted to the DB through PDO
 */

abstract class MappedObject implements Iterator {

	protected $fields = array();
	protected $required_fields = array();
	protected $data;
	protected $updates = array();

	public function __construct(Array $fields = array()) {
		$this->verifyInput($fields);
		$this->data = $fields;
	}

	protected function verifyFields($data, $input = true) {
		$out = array();

		foreach($data as $field=>$val) {

			if(!isset($this->fields[$field])) {
				continue;
			}

			switch($this->fields[$field]) {
				case 'int':
					$out[$field] = intval($val);
					break;
				case 'string':
					$out[$field] = strval($val);
					break;
				case 'float':
					$out[$field] = floatval($val);
					break;
				case 'bool':
					$out[$field] = (bool) $val;
					break;
				case 'time':
					if($input) {
						$out[$field] = new DateTime($val);
					} else {
						$datefield = $val;
						$out[$field] = $val->format('c');
					}
					break;
			}
		}

		return $out;
	}

	protected function verifyInput($data) {
		return $this->verifyFields($data, true);
	}

	protected function verifyOutput($data) {
		return $this->verifyFields($data, false);
	}

	//Magic setter - is called when you try to set object properties
	public function __set($field, $val) {
		if(array_key_exists($field, $this->fields)) {
			//A little finagling to make dealing with time more simple
			if($this->fields[$field] === 'time') {
				if(is_int($val) || is_numeric($val)) {
					//Turn timestamps into DateTime
					$this->updates[$field] = new DateTime("@$val");
				} else if($val instanceof DateTime) {
					//Just assign DateTime objects straight off
					$this->updates[$field] = $val;
				} else {
					//Turn date strings into DateTime
					$this->updates[$field] = new DateTime($val);
				}
			} else {
				//Default behavior - just assign to the update array
				$this->updates[$field] = $val;
			}
		} else {
			throw new InvalidFieldException(get_called_class(), $field);
		}
	}

	//Magic getter - is called when you try to set object properties
	public function __get($field) {
		//Check updated fields, first
		if(array_key_exists($field, $this->updates)) {
			return $this->updates[$field];
		}

		//Then, check the fields set in the constructor
		if(array_key_exists($field, $this->data)) {
			return $this->data[$field];
		}

		//Finally, return a default value of NULL
		if(array_key_exists($field, $this->fields)) {
			return NULL;
		}

		throw new InvalidFieldException(get_called_class(), $field);
	}

	//Magic to check of a property is set
	public function __isset($field) {
		//Check to see if $field is set and if it is in $this->fields
		return (array_key_exists($field, $this->updates) && $this->updates[$field] !== NULL) ||
			(array_key_exists($field, $this->data) && $this->data[$field] !== NULL);
	}

	//Magic to unset object properties
	public function __unset($field) {
		if(!array_key_exists($field, $this->fields)) {
			throw new InvalidFieldException(get_called_class(), $field);
		}

		$this->__set($field, NULL);
	}

	//Batch set fields
	public function importData(array $data) {
		$this->updates = array_merge(
			$this->updates,
			$this->verifyInput($data)
		);
		return $this;
	}

	//Create an array out of the object
	public function toArray() {
		return $this->verifyOutput(
			array_merge($this->data, $this->updates)
		);
	}

	public function getUpdatedFields() {
		return $this->verifyOutput($this->updates);
	}

	//These allow subscribing to certain events in a Model's lifetime
	public function __beforeSave() {
		foreach($this->required_fields as $req) {
			if(!isset($this->{$req})) {
				throw new MissingFieldException(get_called_class(), $req);
			}
		}
	}

	public function __afterSave() {
		$this->data = array_merge($this->data, $this->updates);
	}

	public function __beforeTrash() {
	}

	public function __afterTrash() {
	}

	//Iterator methods - gotta have these to iterate

	public function current() {
		return $this->{$this->key()};
	}

	public function rewind() {
		reset($this->fields);
	}

	public function key() {
		return key($this->fields);
	}

	public function next() {
		next($this->fields);
	}

	public function valid() {
		return is_string($this->key());
	}
}

