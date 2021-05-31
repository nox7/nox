<?php

	namespace Nox\ORM\MySQLDataTypes;

	require_once __DIR__ . "/DataType.php";

	class FloatValue extends DataType{

		public string $mySQLDataTypeName = "float";
		public string $mySQLBoundParameterType = "d";

		public function __construct(
			public int $columnWidth = 11,
		){

		}
	}