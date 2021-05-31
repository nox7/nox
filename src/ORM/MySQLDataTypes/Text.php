<?php

	namespace Nox\ORM\MySQLDataTypes;

	require_once __DIR__ . "/DataType.php";

	class Text extends DataType{

		public string $mySQLDataTypeName = "text";
		public string $mySQLBoundParameterType = "s";

		public function __construct(){

		}
	}