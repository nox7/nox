<?php

	namespace Nox\ORM\MySQLDataTypes;

	require_once __DIR__ . "/DataType.php";

	class VariableCharacter extends DataType{

		public string $mySQLDataTypeName = "varchar";
		public string $mySQLBoundParameterType = "s";

		public function __construct(
			public int $columnWidth = 255,
		){
			
		}
	}