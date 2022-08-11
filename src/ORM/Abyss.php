<?php

	namespace Nox\ORM;

	use Exception;
	use mysqli;
	use mysqli_sql_exception;
	use Nox\ORM\Exceptions\MissingDatabaseCredentials;
	use Nox\ORM\Exceptions\NoPrimaryKey;
	use Nox\ORM\Exceptions\ObjectMissingModelProperty;
	use Nox\ORM\Interfaces\ModelInstance;
	use Nox\ORM\Interfaces\MySQLModelInterface;
	use Nox\ORM\MySQLDataTypes\Blob;
	use Nox\ORM\MySQLDataTypes\DataType;
	use Nox\ORM\MySQLDataTypes\MediumText;
	use Nox\ORM\MySQLDataTypes\Text;
	use ReflectionClass;
	use ReflectionException;

	require_once __DIR__ . "/Exceptions/ObjectMissingModelProperty.php";
	require_once __DIR__ . "/Exceptions/NoPrimaryKey.php";

	class Abyss{

		/**
		 * List of classes that cannot have DEFAULT values
		 */
		public const NO_DEFAULT_DATA_TYPE_CLASS_NAMES = [
			Text::class,
			Blob::class,
			MediumText::class,
		];

		/**
		 * The encoding to set the NAMES to
		 */
		public static string $characterEncoding = "utf8mb4";

		/**
		 * The default collation of the connection
		 */
		public static string $collation = "utf8mb4_unicode_ci";

		/** @var DatabaseCredentials[] */
		private static array $databaseCredentials = [];

		/** @var mysqli[] */
		private static array $connections = [];

		/**
		 * The current MySQLi resource being used
		 */
		private mysqli $currentConnection;

		/**
		 * Adds the credentials to Abyss and creates a MySQLi connection to the database, then stores it
		 * in a static cache or later use. Additionally, runs a SET NAMES %s COLLATE %s query to set
		 * the NAMES and COLLATE to the correlating static settings of Abyss.
		 * @param DatabaseCredentials $credentials
		 * @return void
		 * @throws mysqli_sql_exception
		 */
		public static function addCredentials(DatabaseCredentials $credentials){
			if (self::getCredentials($credentials->database) === null) {
				mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Set MySQLi to throw exceptions

				self::$databaseCredentials[$credentials->database] = $credentials;

				$mysqliConnection = new mysqli(
					hostname: $credentials->host,
					username: $credentials->username,
					password: $credentials->password,
					database: $credentials->database,
					port: $credentials->port,
				);

				// Set the NAMES of the connection
				$mysqliConnection->query(
					sprintf(
						"SET NAMES %s COLLATE %s",
						self::$characterEncoding,
						self::$collation,
					),
				);

				self::$connections[$credentials->database] = $mysqliConnection;
			}
		}

		public static function getCredentials(string $databaseName): DatabaseCredentials | null{
			if (array_key_exists($databaseName, self::$databaseCredentials)) {
				return self::$databaseCredentials[$databaseName];
			}else{
				return null;
			}
		}

		public function __construct(){}

		/**
		 * @throws MissingDatabaseCredentials
		 * @throws mysqli_sql_exception
		 * @throws Exception
		 */
		public function getConnectionToDatabase(string $databaseName): mysqli{
			if (array_key_exists($databaseName, self::$connections)){
				return self::$connections[$databaseName];
			}else{
				// No existing connection, create one if the credentials are available
				$credentials = self::getCredentials($databaseName);
				if ($credentials === null){
					throw new MissingDatabaseCredentials("Missing DatabaseCredentials object for database {$databaseName}. Call Abyss::addCredentials() with an instance of DatabaseCredentials to add the missing credentials.");
				}else{
					// Just missing the connection? Should never happen
					throw new Exception("Abyss has the credentials for {$databaseName} but no connection. Please make sure the credentials were added using Abyss::addCredentials().");
				}
			}
		}

		/**
		 * Instantiates a class that follows a model with possible prefilled values.
		 * @throws ReflectionException|ObjectMissingModelProperty
		 */
		public function instanceFromModel(MySQLModelInterface $model, array $columnValues = []): mixed{
			$className = $model->getInstanceName();
			$instance = new $className();
			$instanceReflection = new ReflectionClass($instance);
			$instanceProperties = $instanceReflection->getProperties();
			$instancePropertyNames = [];
			foreach($instanceProperties as $reflectionProperty){
				$instancePropertyNames[] = $reflectionProperty->name;
			}

			/**
			 * @var ColumnDefinition $sqlColumnDefinition
			 */
			foreach($model->getColumns() as $sqlColumnDefinition) {
				$columnName = $sqlColumnDefinition->name;
				$propertyNameInClass = $sqlColumnDefinition->classPropertyName;
				// Check if a property exists for this column
				if (in_array($propertyNameInClass, $instancePropertyNames)) {

					$toValue = $sqlColumnDefinition->defaultValue;
					if (array_key_exists($columnName, $columnValues)) {
						$toValue = $columnValues[$columnName];
					}

					$instance->{$propertyNameInClass} = $toValue;
				} else {
					throw new ObjectMissingModelProperty("Missing property definition in class $className for column $columnName. Property name expected " . $sqlColumnDefinition->classPropertyName);
				}
			}

			return $instance;
		}

		/**
		 * Prefills the properties of a model class with the column definition defaults for that class' model
		 * @throws ObjectMissingModelProperty
		 */
		public function prefillPropertiesWithColumnDefaults(ModelInstance $modelClass): void{
			$model = $modelClass::getModel();
			$instanceReflection = new ReflectionClass($modelClass);
			$instanceProperties = $instanceReflection->getProperties();
			$instancePropertyNames = [];
			foreach($instanceProperties as $reflectionProperty){
				$instancePropertyNames[] = $reflectionProperty->name;
			}

			/**
			 * @var ColumnDefinition $sqlColumnDefinition
			 */
			foreach($model->getColumns() as $sqlColumnDefinition) {
				$columnName = $sqlColumnDefinition->name;
				$propertyNameInClass = $sqlColumnDefinition->classPropertyName;
				// Check if a property exists for this column
				if (in_array($propertyNameInClass, $instancePropertyNames)) {
					$modelClass->{$propertyNameInClass} = $sqlColumnDefinition->defaultValue;
				} else {
					throw new ObjectMissingModelProperty("Missing property definition in class $className for column $columnName. Property name expected " . $sqlColumnDefinition->classPropertyName);
				}
			}
		}

		/**
		 * Returns a class instances with data from the MySQL database.
		 * Identified by a primary key.
		 */
		public function fetchInstanceByModelPrimaryKey(MySQLModelInterface $model, mixed $keyValue): mixed{
			$primaryKeyName = "";
			$primaryKeyBindFlagType = "";

			/** @var ColumnDefinition $columnDefinition */
			foreach($model->getColumns() as $columnDefinition){
				if ($columnDefinition->isPrimary){
					$primaryKeyName = $columnDefinition->name;
					$primaryKeyBindFlagType = $columnDefinition->dataType->mySQLBoundParameterType;
				}
			}

			$statement = $this->getConnectionToDatabase($model->getDatabaseName())->prepare(
				sprintf("SELECT * FROM `%s` WHERE `%s` = ?", $model->getName(), $primaryKeyName),
			);
			$statement->bind_param($primaryKeyBindFlagType, $keyValue);
			$statement->execute();
			$result = $statement->get_result();
			if ($result->num_rows > 0){
				$row = $result->fetch_assoc();
				return $this->instanceFromModel($model, $row);
			}

			return null;
		}

		/**
		 * Builds a WHERE clause from a ColumnQuery
		 * @return array{string, string}
		 */
		public function buildWhereClause(MySQLModelInterface $model, ColumnQuery $columnQuery): array
		{
			$whereClause = "WHERE ";
			$preparedBindDataTypes = "";
			$boundValues = [];

			/** @var array $clause */
			foreach ($columnQuery->whereClauses as $clause) {
				$clauseType = $clause['clauseType'];

				if ($clauseType === "conditionGroup") {
					$groupPosition = $clause['conditionGroupPosition'];
					if ($groupPosition === "start") {
						$whereClause .= "(";
					} elseif ($groupPosition === "end") {
						$whereClause .= ")";
					}
				} elseif ($clauseType === "joinCondition") {
					$whereClause .= sprintf(" %s ", $clause['clauseJoinWord']);
				} elseif ($clauseType === "where") {
					$columnName = $clause['column'];
					$columnNameFormatted = "";

					// Determine if the columnName should have backticks
					if (str_starts_with(strtolower($columnName), "coalesce(")){
						$columnNameFormatted = sprintf("%s", $columnName);
						$preparedBindDataTypes .= "s"; // Loop below won't find this column name
					}elseif (str_starts_with(strtolower($columnName), "concat(")){
						$columnNameFormatted = sprintf("%s", $columnName);
						$preparedBindDataTypes .= "s"; // Loop below won't find this column name
					}else{
						$columnNameFormatted = sprintf("`%s`", $columnName);
					}

					$isRaw = $clause['raw'];
					$condition = trim(strtolower($clause['condition']));
					$value = $clause['value'];

					if ($isRaw){
						$whereClause .= sprintf("`%s` %s %s", $columnNameFormatted, $condition, $value);
					}else {
						if (
							$condition !== "is" &&
							$condition !== "is not" &&
							$condition !== "in" &&
							$condition !== "not in"
						) {

							// Find the data type flag for this column name
							/** @var ColumnDefinition $columnDefinition */
							foreach ($model->getColumns() as $columnDefinition) {
								if ($columnDefinition->name === $columnName) {
									$preparedBindDataTypes .= $columnDefinition->dataType->mySQLBoundParameterType;
									break;
								}
							}

							$boundValues[] = $value;
							$whereClause .= sprintf("%s %s ?", $columnNameFormatted, $condition);
						} else {
							// IS, IS NOT, IN, and NOT IN
							$whereClause .= sprintf("%s %s %s", $columnNameFormatted, $condition, $value);
						}
					}
				}
			}

			return [
				$whereClause,
				$preparedBindDataTypes,
				$boundValues,
			];
		}

		/**
		 * Returns class instances that match the keyValueColumns pairs.
		 * Identified by a primary key.
		 */
		public function fetchInstances(
			MySQLModelInterface $model,
			ColumnQuery $columnQuery = null,
			ResultOrder $resultOrder = null,
			Pager $pager = null,
		): array{

			$instancesFetched = [];
			$tableName = $model->getName();
			$whereClause = "";
			$orderClause = "";
			$limitClause = "";

			// Build the WHERE clause
			$preparedBindDataTypes = "";
			$boundValues = [];
			if ($columnQuery !== null && !empty($columnQuery->whereClauses)) {
				list($whereClause, $preparedBindDataTypes, $boundValues) = $this->buildWhereClause($model, $columnQuery);
			}

			// Build the ORDER BY clause
			if ($resultOrder !== null){
				$orderClause = "ORDER BY ";
				foreach($resultOrder->orderClauses as $clause){
					$orderClause .= $clause . ", ";
				}
				$orderClause = rtrim($orderClause, ", ");
			}

			// Build the LIMIT, OFFSET clause
			if ($pager !== null){
				$limitClause = sprintf("LIMIT %d OFFSET %d", $pager->limit, ($pager->pageNumber - 1) * $pager->limit);
			}

			// Build the full query
			$query = sprintf(
				"SELECT * FROM `%s`\n%s\n%s\n%s",
				$tableName, $whereClause, $orderClause, $limitClause
			);

			$statement = $this->getConnectionToDatabase($model->getDatabaseName())->prepare($query);
			if (!empty($boundValues)) {
				$statement->bind_param($preparedBindDataTypes, ...$boundValues);;
			}
			$statement->execute();
			$result = $statement->get_result();
			$rows = $result->fetch_all(MYSQLI_ASSOC);
			foreach($rows as $row){
				$instancesFetched[] = $this->instanceFromModel($model, $row);
			}

			return $instancesFetched;
		}

		/**
		 * Builds a save query for a ModelInstance
		 */
		public function buildSaveQuery(
			ModelInstance $classInstance,
			bool $usePreparedStatement = true,
		): array {
			$model = $classInstance->getModel();
			$tableName = $model->getName();
			$primaryKeyName = $this->getPrimaryKey($model);
			$columnNameList = [];
			$columnValues = [];
			$rawQueryColumnValues = [];
			$preparedStatementTypeFlags = "";

			/** @var ColumnDefinition $columnDefinition */
			foreach($model->getColumns() as $columnDefinition){
				$mysqlBoundParameterFlagType = $columnDefinition->dataType->mySQLBoundParameterType;
				$columnValue = $classInstance->{$columnDefinition->classPropertyName};
				$columnNameList[] = $columnDefinition->name;
				$columnValues[] = $columnValue;

				$preparedStatementTypeFlags .= $mysqlBoundParameterFlagType;
				if ($columnValue !== null) {
					if ($mysqlBoundParameterFlagType === "s" or $mysqlBoundParameterFlagType === "b") {
						$rawQueryColumnValues[] = sprintf('"%s"', $this->getConnectionToDatabase($model->getDatabaseName())->escape_string($columnValue));
					} else {
						$rawQueryColumnValues[] = sprintf("%s", $this->getConnectionToDatabase($model->getDatabaseName())->escape_string((string)$columnValue));
					}
				}else{
					$rawQueryColumnValues[] = "NULL";
				}
			}

			// Handle the column definition MySQL syntax for the INSERT portion
			// and the UPDATE syntax
			$columnNameListAsMySQLSyntax = "";
			$columnUpdateMySQLSyntax = "";

			foreach($columnNameList as $columnName){
				$columnNameListAsMySQLSyntax .= sprintf("`%s`,", $columnName);

				// Do not update the primary key
				if ($columnName !== $primaryKeyName) {
					$columnUpdateMySQLSyntax .= sprintf("`%s` = VALUES(`%s`),", $columnName, $columnName);
				}
			}

			// Remove the trailing commas
			$columnNameListAsMySQLSyntax = rtrim($columnNameListAsMySQLSyntax, ",");
			$columnUpdateMySQLSyntax = rtrim($columnUpdateMySQLSyntax, ",");

			if ($usePreparedStatement) {
				$questionMarksForPreparedQuery = array_fill(0, count($columnNameList), "?");
				$query = sprintf(
					"INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
					$tableName,
					$columnNameListAsMySQLSyntax,
					implode(",", $questionMarksForPreparedQuery),
					$columnUpdateMySQLSyntax,
				);
			}else{
				$query = sprintf(
					"INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
					$tableName,
					$columnNameListAsMySQLSyntax,
					implode(",", $rawQueryColumnValues),
					$columnUpdateMySQLSyntax,
				);
			}

			return [
				"query"=>$query,
				"preparedStatementFlags"=>$preparedStatementTypeFlags,
				"columnValues"=>$columnValues
			];
		}

		/**
		 * Uses mysqli's multi_query functionality to mass save or create (UPDATE OR INSERT) an array
		 * of ModelClass instances. This lightens the network payload when sending mass updates instead
		 * of looping them one by one in PHP
		 * @param ModelClass[] $modelClasses
		 */
		public function saveOrCreateAll(array $modelClasses): void{
			if (!empty($modelClasses)) {
				$allQueries = [];
				$model = $modelClasses[0]::getModel();
				$primaryKeyName = $this->getPrimaryKey($model);

				foreach ($modelClasses as $modelClass) {
					/** @var array{query: string, preparedStatementFlags: array, columnValues: array} $builtQuery */
					$builtQuery = $this->buildSaveQuery(
						classInstance: $modelClass,
						usePreparedStatement: false,
					);
					$allQueries[] = $builtQuery['query'];
				}

				$this->getConnectionToDatabase($model->getDatabaseName())->multi_query(implode(";", $allQueries));
				$currentModelClassIndex = 0;
				do {
					$insertID = $this->getConnectionToDatabase($model->getDatabaseName())->insert_id;
					if ($insertID !== 0) {
						$modelClasses[$currentModelClassIndex]->{$primaryKeyName} = $insertID;
					}
					++$currentModelClassIndex;
				} while ($this->getConnectionToDatabase($model->getDatabaseName())->next_result());
			}
		}

		/**
		 * Either creates (using INSERT) or saves (UPDATEs values) a class following a Model's structure.
		 * The return will be null if an UPDATE occurred or an integer representing the new insert_id of the row
		 * if an INSERT occurred.
		 */
		public function saveOrCreate(ModelInstance $classInstance): null|int{

			/** @var array{query: string, preparedStatementFlags: array, columnValues: array} $builtQuery */
			$builtQuery = $this->buildSaveQuery(
				classInstance: $classInstance,
				usePreparedStatement:true,
			);

			$model = $classInstance::getModel();
			$statement = $this->getConnectionToDatabase($model->getDatabaseName())->prepare($builtQuery['query']);
			$statement->bind_param(
				$builtQuery['preparedStatementFlags'],
				...$builtQuery['columnValues'],
			);
			$statement->execute();
			$result = $statement->get_result();

			if ($statement->affected_rows > 0){
				if ($result === false) {
					$statement->close();
					return $this->getConnectionToDatabase($model->getDatabaseName())->insert_id;
				}
			}

			$statement->close();
			return null;
		}

		/**
		 * Deletes a row in a database identified by the class instance's model's primary key
		 */
		public function deleteRowByPrimaryKey(ModelInstance $classInstance): void{
			$model = $classInstance->getModel();
			$primaryKeyName = $this->getPrimaryKey($model);
			$primaryPropertyName = "";

			if ($primaryKeyName !== null){

				$boundParameterFlag = "i";
				// Find the column definition to get the bound parameter flag
				/** @var ColumnDefinition $columnDefinition */
				foreach($model->getColumns() as $columnDefinition){
					if ($columnDefinition->name === $primaryKeyName){
						$boundParameterFlag = $columnDefinition->dataType->mySQLBoundParameterType;
						$primaryPropertyName = $columnDefinition->classPropertyName;
					}
				}

				$statement = $this->getConnectionToDatabase($model->getDatabaseName())->prepare(
					sprintf("
						DELETE FROM `%s`
						WHERE `%s` = ?
					", $model->getName(), $primaryKeyName)
				);
				$statement->bind_param($boundParameterFlag, $classInstance->{$primaryPropertyName});
				$statement->execute();
			}else{
				throw new NoPrimaryKey("No primary key set for table " . $model->getName());
			}
		}

		/**
		 * Syncs all models to the database
		 * @param ReflectionClass[] $modelReflectionClasses
		 */
		public function syncModels(array $modelReflectionClasses): void{
			foreach ($modelReflectionClasses as $modelReflectionClass){
				/** @var MySQLModelInterface $instanceOfModel */
				$instanceOfModel = $modelReflectionClass->newInstance();
				$tableName = $instanceOfModel->getName();
				if ($this->doesTableExist($instanceOfModel, $tableName)){
					$this->updateExistingTable($instanceOfModel);
				}else{
					$this->createNewTable($instanceOfModel);
				}
			}
		}

		/**
		 * Creates the MySQL syntax of the column's data type definition
		 */
		private function getDataTypeDefinitionMySQLSyntax(DataType $dataType): string{
			// Column data type definition
			if (property_exists($dataType, "columnWidth")){
				return sprintf("%s(%d)", $dataType->mySQLDataTypeName, $dataType->columnWidth);
			}else{
				return sprintf("%s", $dataType->mySQLDataTypeName);
			}
		}

		/**
		 * Fetches the MySQL syntax of a column definition from a Model
		 */
		private function getColumnDefinitionAsMySQLSyntax(ColumnDefinition $definition): string{
			$mySQLSyntax = sprintf("`%s`", $definition->name);

			// Get the column's data type definition
			$mySQLSyntax .= " " . $this->getDataTypeDefinitionMySQLSyntax($definition->dataType);

			// Column nullable definition
			if ($definition->isNull){
				$mySQLSyntax .= " NULL";
			}else{
				$mySQLSyntax .= " NOT NULL";
			}

			// Column default value definition
			if (!$definition->autoIncrement) {
				// Some data types cannot have a default
				$reflector = new ReflectionClass($definition->dataType);
				if (!in_array($reflector->getName(), self::NO_DEFAULT_DATA_TYPE_CLASS_NAMES)) {
					if (is_string($definition->defaultValue)) {
						$mySQLSyntax .= sprintf(" DEFAULT \"%s\"", $definition->defaultValue);
					} elseif ($definition->defaultValue === null) {
						$mySQLSyntax .= " DEFAULT NULL";
					} else {
						$mySQLSyntax .= sprintf(" DEFAULT %s", $definition->defaultValue);
					}
				}
			}

			// Auto increment identifier
			if ($definition->autoIncrement){
				$mySQLSyntax .= " AUTO_INCREMENT";
			}

			return $mySQLSyntax;
		}

		/**
		 * Fetches the primary key from a model, if any
		 */
		public function getPrimaryKey(MySQLModelInterface $model): ?string{
			/** @var ColumnDefinition $columnDefinition */
			foreach($model->getColumns() as $columnDefinition){
				if ($columnDefinition->isPrimary){
					return $columnDefinition->name;
				}
			}

			return null;
		}

		/**
		 * Fetches the unique key from a model, if any
		 */
		private function getUniqueKey(MySQLModelInterface $model): ?string{
			/** @var ColumnDefinition $columnDefinition */
			foreach($model->getColumns() as $columnDefinition){
				if ($columnDefinition->isUnique){
					return $columnDefinition->name;
				}
			}

			return null;
		}

		/**
		 * Checks if a table already exists
		 */
		protected function doesTableExist(MySQLModelInterface $model, string $tableName): bool{
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf("SHOW TABLES LIKE \"%s\"", $tableName));
			return $result->num_rows > 0;
		}

		/**
		 * Checks if there is a UNIQUE index on a column in a table
		 */
		protected function isColumnAUniqueIndex(MySQLModelInterface $model, string $tableName, string $columnName): bool{
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW INDEXES FROM `%s` WHERE `Column_name`='%s' AND Non_unique=0 AND Key_name != \"PRIMARY\"",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Fetches the name of the index that has the column name
		 */
		protected function getUniqueIndexName(MySQLModelInterface $model, string $tableName, string $columnName): string{
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW INDEXES FROM `%s` WHERE `Column_name`='%s' AND Non_unique=0 AND Key_name != \"PRIMARY\"",
					$tableName, $columnName
				)
			);
			$row = $result->fetch_assoc();
			return $row['Key_name'];
		}

		/**
		 * Checks if there is a PRIMARY KEY index on a column in a table
		 */
		protected function isColumnAPrimaryKey(MySQLModelInterface $model, string $tableName, string $columnName): bool{
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW KEYS FROM `%s` WHERE `Column_name`='%s' AND Key_name=\"PRIMARY\"",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Checks if a column exists in a table
		 */
		protected function doesColumnExistInTable(MySQLModelInterface $model, string $tableName, string $columnName): bool{
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW COLUMNS FROM `%s` WHERE `Field`='%s'",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Checks if a column exists in a table
		 */
		protected function getAllColumnNamesInTable(MySQLModelInterface $model, string $tableName): array{
			$columnNames = [];
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW COLUMNS FROM `%s`",
					$tableName
				)
			);
			while ($row = $result->fetch_assoc()){
				$columnNames[] = $row['Field'];
			}

			return $columnNames;
		}

		/**
		 * Checks if a column is set to auto increment
		 */
		protected function isColumnSetToAutoIncrement(MySQLModelInterface $model, string $tableName, string $columnName): bool{
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW COLUMNS FROM `%s` WHERE `Field`='%s' AND `Extra` LIKE \"%auto_increment%\"",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Checks if a column is nullable
		 */
		protected function isColumnNullable(MySQLModelInterface $model, string $tableName, string $columnName): bool{
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW COLUMNS FROM `%s` WHERE `Field`='%s' AND `Null`=\"YES\"",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Checks if a column is set to auto increment
		 */
		protected function getColumnDefaultValue(MySQLModelInterface $model, string $tableName, string $columnName): mixed{
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW COLUMNS FROM `%s` WHERE `Field`='%s'",
					$tableName, $columnName
				)
			);
			$row = $result->fetch_assoc();
			return $row['Default'];
		}

		/**
		 * Checks if a column's data type definition matches the ColumnDefinition provided
		 */
		protected function doColumnDefinitionsMatch(MySQLModelInterface $model, string $tableName, string $columnName, ColumnDefinition $columnDefinition): bool{
			$dataTypeFromColumnDef = $this->getDataTypeDefinitionMySQLSyntax($columnDefinition->dataType);
			$result = $this->getConnectionToDatabase($model->getDatabaseName())->query(sprintf(
					"SHOW COLUMNS FROM `%s` WHERE `Field`='%s' AND `Type`='%s'",
					$tableName, $columnName, $dataTypeFromColumnDef,
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Updates an existing table to match the Model
		 */
		protected function updateExistingTable(MySQLModelInterface $model): void{
			$tableName = $model->getName();

			$queriesToExecute = "";

			// Keep track of column names in the model
			// to use this to drop unknown columns after the column
			// definitions are updated
			$columnNamesDefinedByModel = [];

			$previousColumnNameIterated = null;
			/** @var ColumnDefinition $columnDefinition */
			foreach($model->getColumns() as $columnDefinition){
				$columnName = $columnDefinition->name;
				$columnNamesDefinedByModel[] = $columnName;
				if (!$this->doesColumnExistInTable($model, $tableName, $columnName)){
					// Create the whole thing
					$queriesToExecute .= "ALTER TABLE `$tableName` ADD COLUMN " . $this->getColumnDefinitionAsMySQLSyntax($columnDefinition) . ";\n";
				}else{
					// Redefine the column to make sure it matches
					// Since we're altering, we have to check if a PRIMARY KEY needs to be appended
					$primaryKeyDefinitionString = "";
					if ($columnDefinition->isPrimary && !$this->isColumnAPrimaryKey($model, $tableName, $columnName)){
						$primaryKeyDefinitionString = "PRIMARY KEY";
					}
					$queriesToExecute .= sprintf(
						"ALTER TABLE `%s` MODIFY %s %s %s;\n",
						$tableName,
						$this->getColumnDefinitionAsMySQLSyntax($columnDefinition),
						$primaryKeyDefinitionString,
						$previousColumnNameIterated !== null ? "AFTER `$previousColumnNameIterated`" : "",
					);
				}

				// Is it a unique column?
				if ($this->isColumnAUniqueIndex($model, $tableName, $columnName)){
					if (!$columnDefinition->isUnique){
						// Needs to be dropped
						$indexName = $this->getUniqueIndexName($model, $tableName, $columnName);
						$queriesToExecute .= sprintf(
							"ALTER TABLE `%s` DROP INDEX %s;\n",
							$tableName, $indexName
						);
					}
				}else{
					if ($columnDefinition->isUnique){
						// Needs to be added
						$queriesToExecute .= sprintf(
							"ALTER TABLE `%s` ADD UNIQUE (`%s`);\n",
							$tableName, $columnName
						);
					}
				}

				$previousColumnNameIterated = $columnName;
			}

			// Get all the columns currently in the table
			$columnNamesInTable = $this->getAllColumnNamesInTable($model, $tableName);
			foreach($columnNamesInTable as $columnNameInTable){
				if (!in_array($columnNameInTable, $columnNamesDefinedByModel)){
					// Drop it
					$queriesToExecute .= sprintf("ALTER TABLE `%s` DROP COLUMN `%s`;", $tableName, $columnNameInTable);
				}
			}

			$this->getConnectionToDatabase($model->getDatabaseName())->multi_query($queriesToExecute);

			// Remove the queries from the result stack
			// Otherwise "commands out of sync" will occur
			while ($result = $this->getConnectionToDatabase($model->getDatabaseName())->next_result()){}
		}

		/**
		 * Creates a table following a model
		 */
		protected function createNewTable(MySQLModelInterface $model): void{
			$connection = $this->getConnectionToDatabase($model->getDatabaseName());
			$tableName = $model->getName();
			$tableCreationSyntax = "CREATE TABLE `$tableName`(";
			$columnDefinitionSyntax = "";

			/** @var ColumnDefinition $columnDefinition */
			foreach($model->getColumns() as $columnDefinition){
				$columnDefinitionSyntax .= $this->getColumnDefinitionAsMySQLSyntax($columnDefinition);
				$columnDefinitionSyntax .= ",";
			}

			// Remove the trailing comma
			$columnDefinitionSyntax = rtrim($columnDefinitionSyntax, ",");

			// Append the column definition syntax
			$tableCreationSyntax .= $columnDefinitionSyntax;

			// Add the primary key syntax, if any
			$primaryKey = $this->getPrimaryKey($model);
			if ($primaryKey !== null){
				$tableCreationSyntax .= sprintf(", PRIMARY KEY(`%s`)", $primaryKey);
			}

			// Add the unique index syntax, if any
			$uniqueIndex = $this->getUniqueKey($model);
			if ($uniqueIndex !== null){
				$tableCreationSyntax .= sprintf(", UNIQUE (`%s`)", $uniqueIndex);
			}

			// Close the open bracket from the top
			$tableCreationSyntax .= ")";

			// Run the query
			$connection->query($tableCreationSyntax);
		}
	}
