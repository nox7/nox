<?php

	namespace Nox\ORM;

	class ColumnDefinition{
		public function __construct(
			public string $name,
			public string $classPropertyName,
			public MySQLDataTypes\DataType $dataType,
			public mixed $defaultValue = null,
			public bool $autoIncrement = false,
			public bool $isPrimary = false,
			public bool $isUnique = false,
			public bool $isNull = true,
		){

		}
	}