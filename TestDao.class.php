<?php
class TestDao extends PDOMapper {
        protected $fields = array(
		'id' => 'key',
		'str_field' => 'string',
		'int_field' => 'int',
		'bool_field' => 'bool',
		'date_field' => 'time'
        );

	protected function getModelName() {
		return 'TestModel';
	}

	protected function getTableName() {
		return 'test';
	}
}
