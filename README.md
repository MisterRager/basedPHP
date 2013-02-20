basedPHP
========
Author: Alan Rager  
License: If you make money off this code, I'd like some.  
Jan 22, 2013  

Light PHP DataMapper ORM

basedPHP is a light abstraction layer for handling interaction with a MySQL database in PHP.

Models to be saved to the database should be subclasses of MappedObject. DAO's to save said objects should extend PDOMapper.

Currently, there is no mechanism to generate or migrate the database schema as it's being used on an application that has an already existing schema, but PDOMapper has a method tableSql() which generates a functional (if shitty) schema for development.

To map a table, create a DAO class that has the fields:

- array $fields: a list of db fields to be mapped. It is a key=>value map of field names onto field types. Valid types are 'key', 'int', 'string', 'float', 'bool', and 'time'. There can only be one key.
- array $required_fields: not actually required that you require fields, but if you do, these must be specified here.
