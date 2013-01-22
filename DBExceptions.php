<?php
namespace basedPHP;

//A struct to hold all the exception codes

class ExceptionCode {
	//MapperException
	const INVALID_ID = 1001;
	//ModelException
	const MISSING_FIELD = 2001;
	const INVALID_FIELD = 2002;
	const FIELD_FORMAT = 2003;
	const DUPLICATE_RECORD= 2004;
}

class MapperException extends Exception {
}

class InvalidIdException extends MapperException {
	public function __construct($class_name, $id) {
		parent::__construct("The id [$id] does not corespond to a $class_name record", ExceptionCode::INVALID_ID);
	}
}

class ModelException extends Exception {
}

class MissingFieldException extends ModelException {
        public function __construct($class_name, $field_name) {
                parent::__construct("Required field '$field_name' mising from class '$class_name'", ExceptionCode::MISSING_FIELD);
        }
}

class InvalidFieldException extends ModelException {
        public function __construct($class_name, $field_name) {
                parent::__construct("Field '$field_name' does not exist in class '$class_name'", ExceptionCode::INVALID_FIELD);
        }
}

class FieldFormatException extends ModelException {
        public function __construct($class_name, $field_name, $accepted_format) {
                parent::__construct("Field '$field_name' in class '$class_name' must be given as $accepted_format", ExceptionCode::FIELD_FORMAT);
        }
}

class DuplicateRecordException extends Exception {
	public function __construct($class_name) {
		parent::__construct("Cannot create duplicate copy of $class_name record", ExceptionCode::DUPLICATE_RECORD);
	}
}
