<?php

	namespace Nox\ORM\MySQLDataTypes;

	require_once __DIR__ . "/DataType.php";

	class MediumText extends DataType{

		public string $mySQLDataTypeName = "mediumtext";
		public string $mySQLBoundParameterType = "s";

		public function __construct(){

		}
	}