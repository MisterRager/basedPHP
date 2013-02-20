<?php
/**	MappedObject.class.php
 * 	class		MappedObject
 * 	author		Alan Rager
 * 	created		Jan 17, 2013
 *
 * 	This is an abstract superclass for the objects
 * 	that get persisted to the DB through PDO
 */

abstract class MappedObject implements Iterator {

	protected $data;
	protected $updates = array();
	protected $fields = array();
	private $keys = array();

	public function __construct(Array $fields = array()) {
		$this->data = $fields;
		$this->fields = array_keys($fields);
	}


	//Magic setter - is called when you try to set object properties
	public function __set($field, $val) {
		if(!isset($this->data[$field]) || $this->data[$field] !== $val) {
			$this->updates[$field] = $val;
			$this->fields = array_keys($this->toArray());
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
		return NULL;
	}

	//Magic to check of a property is set
	public function __isset($field) {
		return array_key_exists($field, $this->updates) ||
			array_key_exists($field, $this->data);
	}

	//Magic to unset object properties
	public function __unset($field) {
		if(array_key_exists($field, $this->updates)) {
			unset($this->updates[$field]);
		} else if(array_key_exists($field, $this->data)) {
			$this->__set($field, NULL);
		}
		$this->keys = array_keys($this->toArray());
	}

	//Batch set fields
	public function importData(array $data) {
		$this->updates = array_merge(
			$this->updates,
			$data
		);
		$this->keys = array_keys($this->toArray());
		return $this;
	}

	//Create an array out of the object
	public function toArray() {
		return array_merge($this->data, $this->updates);
	}

	public function getUpdatedFields() {
		return $this->updates;
	}

	//These allow subscribing to certain events in a Model's lifetime
	public function __beforeSave() {
	}

	public function __afterSave() {
		$this->data = array_merge($this->data, $this->updates);
		$this->updates = array();
	}

	public function __beforeTrash() {
	}

	public function __afterTrash() {
	}

	//Iterator methods - gotta have these to iterate

	private $iterationpointer = 0;

	public function current() {
		return $this->{$this->key()};
	}

	public function rewind() {
		//reset($this->fields);
		$this->iterationpointer = 0;
	}

	public function key() {
		return isset($this->fields[$this->iterationpointer]) ? 
			$this->fields[$this->iterationpointer]:NULL;
	}

	public function next() {
		//next($this->fields);
		$this->iterationpointer++;
	}

	public function valid() {
		return is_string($this->key());
	}
}

