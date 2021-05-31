<?php
	require_once __DIR__ . "/exceptions/ObjectMissingModelProperty.php";

	require_once __DIR__ . "/interfaces/ModelInstance.php";
	require_once __DIR__ . "/interfaces/MySQLModelInterface.php";

	require_once __DIR__ . "/classes/MySQLDataTypes/DataType.php";
	require_once __DIR__ . "/classes/MySQLDataTypes/BigInteger.php";
	require_once __DIR__ . "/classes/MySQLDataTypes/Integer.php";
	require_once __DIR__ . "/classes/MySQLDataTypes/Text.php";
	require_once __DIR__ . "/classes/MySQLDataTypes/TinyInteger.php";
	require_once __DIR__ . "/classes/MySQLDataTypes/VariableCharacter.php";

	require_once __DIR__ . "/classes/ColumnDefinition.php";
	require_once __DIR__ . "/classes/ColumnQuery.php";
	require_once __DIR__ . "/classes/MySQLModel.php";
	require_once __DIR__ . "/classes/Pager.php";
	require_once __DIR__ . "/classes/ResultOrder.php";

	require_once __DIR__ . "/Abyss.php";