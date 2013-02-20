basedPHP
========
Author: Alan Rager  
License: If you make money off this code, I'd like some.  
Jan 22, 2013  

Light PHP DataMapper ORM

basedPHP is a light abstraction layer for handling interaction with a MySQL database in PHP.

Models to be saved to the database shoudld be subclasses of MappedObject. DAO's to save said objects should extend PDOMapper.

Currently, there is no mechanism to generate or migrate the database schema as it's being used on an application that has an already existing schema.o
