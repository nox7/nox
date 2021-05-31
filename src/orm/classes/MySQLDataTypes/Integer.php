<?php

	namespace Nox\ORM\MySQLDataTypes;

	require_once __DIR__ . "/DataType.php";

	class Integer extends DataType{

		public string $mySQLDataTypeName = "int";
		public string $mySQLBoundParameterType = "i";

		public function __construct(
			public int $columnWidth = 11,
		){

		}
	}