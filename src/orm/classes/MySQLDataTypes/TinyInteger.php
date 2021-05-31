<?php

	namespace Nox\ORM\MySQLDataTypes;

	require_once __DIR__ . "/DataType.php";

	class TinyInteger extends DataType{

		public string $mySQLDataTypeName = "tinyint";
		public string $mySQLBoundParameterType = "i";

		public function __construct(
			public int $columnWidth = 1,
		){
			
		}
	}