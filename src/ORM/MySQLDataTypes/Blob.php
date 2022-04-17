<?php

	namespace Nox\ORM\MySQLDataTypes;

	require_once __DIR__ . "/DataType.php";

	class Blob extends DataType{

		public string $mySQLDataTypeName = "blob";
		public string $mySQLBoundParameterType = "b";
	}