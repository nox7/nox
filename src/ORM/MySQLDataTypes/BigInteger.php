<?php

	namespace Nox\ORM\MySQLDataTypes;

	require_once __DIR__ . "/DataType.php";

	class BigInteger extends DataType {

		public string $mySQLDataTypeName = "bigint";
		public string $mySQLBoundParameterType = "i";

		public function __construct(
			public int $columnWidth = 16,
		){

		}
	}