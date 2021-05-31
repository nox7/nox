<?php

	namespace Nox\ORM;

	use Nox\ORM\Exceptions\ObjectMissingModelProperty;
	use Nox\ORM\Interfaces\ModelInstance;
	use Nox\ORM\Interfaces\MySQLModelInterface;
	use Nox\ORM\MySQLDataTypes\DataType;

	require_once __DIR__ . "/Exceptions/ObjectMissingModelProperty.php";

	class Abyss{

		/**
		 * List of classes that cannot have DEFAULT values
		 */
		public const NO_DEFAULT_DATA_TYPE_CLASS_NAMES = [
			"Text","Blob","Geometry","JSON",
		];

		/**
		 * The directory housing MySQL table models
		 */
		public static ?string $modelsDirectory = null;

		/**
		 * The encoding to set the NAMES to
		 */
		public static string $characterEncoding = "utf8mb4";

		/**
		 * The default collation of the connection
		 */
		public static string $collation = "utf8mb4_unicode_ci";

		/**
		 * The current MySQLi resource being used
		 */
		private static \mysqli $mysqli;

		/**
		 * Loads the configuration files from a directory needed for the ORM
		 * when it is used without the Nox Router (such as a CLI script)
		 */
		public static function loadConfig(string $fromDirectory): void{
			// Fetch the nox.json
			$noxJson = file_get_contents($fromDirectory . "/nox.json");
			$noxConfig = json_decode($noxJson, true);

			if (isset($noxConfig['mysql-models-directory']) && !empty($noxConfig['mysql-models-directory'])){
				self::$modelsDirectory = $fromDirectory . $noxConfig['mysql-models-directory'];
			}

			/**
			 * Check if the NoxEnv is loaded
			 */
			if (!class_exists("NocEnv")){
				require_once $fromDirectory . "/nox-env.php";
			}

		}

		public function __construct(){

			// Check if the models directory is set
			// If not, then this is probably being CLI'd
			// and needs to be loaded in

			mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Set MySQLi to throw exceptions
			if (!isset(self::$mysqli)){
				try{
					self::$mysqli = new \mysqli(
						\NoxEnv::MYSQL_HOST,
						\NoxEnv::MYSQL_USERNAME,
						\NoxEnv::MYSQL_PASSWORD,
						\NoxEnv::MYSQL_DB_NAME,
						\NoxEnv::MYSQL_PORT
					);
				}catch(\mysqli_sql_exception $e){
					// Rethrow it
					throw $e;
				}

				self::$mysqli->query(
					sprintf(
						"SET NAMES %s COLLATE %s",
						self::$characterEncoding,
						self::$collation,
					),
				);
			}
		}

		public function getConnection(): \mysqli{
			return self::$mysqli;
		}

		/**
		 * Instantiates a class that follows a model with possible prefilled values.
		 */
		public function instanceFromModel(MySQLModelInterface $model, array $columnValues = []): mixed{
			$className = $model->getInstanceName();
			$instance = new $className();
			$instanceReflection = new \ReflectionClass($instance);
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

			$statement = $this->getConnection()->prepare(
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
			if ($columnQuery !== null) {
				$whereClause = "WHERE ";
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
						$condition = trim(strtolower($clause['condition']));
						$value = $clause['value'];
						if ($condition !== "is" && $condition !== "is not") {

							// Find the data type flag for this column name
							/** @var ColumnDefinition $columnDefinition */
							foreach ($model->getColumns() as $columnDefinition) {
								if ($columnDefinition->name === $columnName) {
									$preparedBindDataTypes .= $columnDefinition->dataType->mySQLBoundParameterType;
									break;
								}
							}

							$boundValues[] = $value;
							$whereClause .= sprintf("`%s` %s ?", $columnName, $condition);
						} else {
							// IS or IS NOT null checks
							$whereClause .= sprintf("`%s` %s %s", $columnName, $condition, $value);
						}
					}
				}
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

			$statement = $this->getConnection()->prepare($query);
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
		 * Either creates (using INSERT) or saves (UPDATEs values) a class following a Model's structure.
		 * The return will be null if an UPDATE occurred or an integer representing the new insert_id of the row
		 * if an INSERT occurred.
		 */
		public function saveOrCreate(ModelInstance $classInstance): null|int{
			$model = $classInstance->getModel();
			$tableName = $model->getName();
			$columnNameList = [];
			$columnValues = [];
			$preparedStatementTypeFlags = "";

			/** @var ColumnDefinition $columnDefinition */
			foreach($model->getColumns() as $columnDefinition){
				$columnNameList[] = $columnDefinition->name;
				$columnValues[] = $classInstance->{$columnDefinition->classPropertyName};
				$preparedStatementTypeFlags .= $columnDefinition->dataType->mySQLBoundParameterType;
			}

			// Handle the column definition MySQL syntax for the INSERT portion
			// and the UPDATE syntax
			$columnNameListAsMySQLSyntax = "";
			$columnUpdateMySQLSyntax = "";

			foreach($columnNameList as $columnName){
				$columnNameListAsMySQLSyntax .= sprintf("`%s`,", $columnName);
				$columnUpdateMySQLSyntax .= sprintf("`%s` = VALUES(`%s`),", $columnName, $columnName);
			}

			// Remove the trailing commas
			$columnNameListAsMySQLSyntax = rtrim($columnNameListAsMySQLSyntax, ",");
			$columnUpdateMySQLSyntax = rtrim($columnUpdateMySQLSyntax, ",");

			$questionMarksForPreparedQuery = array_fill(0, count($columnNameList), "?");
			$query = sprintf(
				"INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
				$tableName,
				$columnNameListAsMySQLSyntax,
				implode(",", $questionMarksForPreparedQuery),
				$columnUpdateMySQLSyntax,
			);

			$statement = $this->getConnection()->prepare($query);
			$statement->bind_param(
				$preparedStatementTypeFlags,
				...$columnValues
			);
			$statement->execute();
			$result = $statement->get_result();

			if ($statement->affected_rows > 0){
				if ($result === false) {
					$statement->close();
					return $this->getConnection()->insert_id;
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

				$statement = $this->getConnection()->prepare(
					sprintf("
						DELETE FROM `%s`
						WHERE `%s` = ?
					", $model->getName(), $primaryKeyName)
				);
				$statement->bind_param($boundParameterFlag, $classInstance->{$primaryPropertyName});
				$statement->execute();
			}else{
				throw new \Exception("No primary key set for table " . $model->getName());
			}
		}

		/**
		* Syncs all models to the database
		*/
		public function syncModels(): void{
			$fileNames = array_diff(scandir(self::$modelsDirectory), ['.','..']);
			foreach ($fileNames as $fileName){
				$modelPath = sprintf("%s/%s", self::$modelsDirectory, $fileName);
				$className = pathinfo($fileName, PATHINFO_FILENAME);
				require_once $modelPath;
				$classReflection = new $className();
				$tableName = $classReflection->getName();
				if ($this->doesTableExist($tableName)){
					$this->updateExistingTable($classReflection);
				}else{
					$this->createNewTable($classReflection);
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
				$reflector = new \ReflectionClass($definition->dataType);
				if (!in_array($reflector->getShortName(), self::NO_DEFAULT_DATA_TYPE_CLASS_NAMES)) {
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
		private function getPrimaryKey(MySQLModelInterface $model): ?string{
			/** @var\NoxMySQL\ColumnDefinition $columnDefinition */
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
		protected function doesTableExist(string $tableName): bool{
			$result = $this->getConnection()->query(sprintf("SHOW TABLES LIKE \"%s\"", $tableName));
			return $result->num_rows > 0;
		}

		/**
		 * Checks if there is a UNIQUE index on a column in a table
		 */
		protected function isColumnAUniqueIndex(string $tableName, string $columnName): bool{
			$result = $this->getConnection()->query(sprintf(
				"SHOW INDEXES FROM `%s` WHERE `Column_name`='%s' AND Non_unique=0 AND Key_name != \"PRIMARY\"",
				$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Fetches the name of the index that has the column name
		 */
		protected function getUniqueIndexName(string $tableName, string $columnName): string{
			$result = $this->getConnection()->query(sprintf(
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
		protected function isColumnAPrimaryKey(string $tableName, string $columnName): bool{
			$result = $this->getConnection()->query(sprintf(
					"SHOW KEYS FROM `%s` WHERE `Column_name`='%s' AND Key_name=\"PRIMARY\"",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Checks if a column exists in a table
		 */
		protected function doesColumnExistInTable(string $tableName, string $columnName): bool{
			$result = $this->getConnection()->query(sprintf(
					"SHOW COLUMNS FROM `%s` WHERE `Field`='%s'",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Checks if a column exists in a table
		 */
		protected function getAllColumnNamesInTable(string $tableName): array{
			$columnNames = [];
			$result = $this->getConnection()->query(sprintf(
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
		protected function isColumnSetToAutoIncrement(string $tableName, string $columnName): bool{
			$result = $this->getConnection()->query(sprintf(
					"SHOW COLUMNS FROM `%s` WHERE `Field`='%s' AND `Extra` LIKE \"%auto_increment%\"",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Checks if a column is nullable
		 */
		protected function isColumnNullable(string $tableName, string $columnName): bool{
			$result = $this->getConnection()->query(sprintf(
					"SHOW COLUMNS FROM `%s` WHERE `Field`='%s' AND `Null`=\"YES\"",
					$tableName, $columnName
				)
			);
			return $result->num_rows > 0;
		}

		/**
		 * Checks if a column is set to auto increment
		 */
		protected function getColumnDefaultValue(string $tableName, string $columnName): mixed{
			$result = $this->getConnection()->query(sprintf(
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
		protected function doColumnDefinitionsMatch(string $tableName, string $columnName, ColumnDefinition $columnDefinition): bool{
			$dataTypeFromColumnDef = $this->getDataTypeDefinitionMySQLSyntax($columnDefinition->dataType);
			$result = $this->getConnection()->query(sprintf(
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
				if (!$this->doesColumnExistInTable($tableName, $columnName)){
					// Create the whole thing
					$queriesToExecute .= "ALTER TABLE `$tableName` ADD COLUMN " . $this->getColumnDefinitionAsMySQLSyntax($columnDefinition) . ";\n";
				}else{
					// Redefine the column to make sure it matches
					// Since we're altering, we have to check if a PRIMARY KEY needs to be appended
					$primaryKeyDefinitionString = "";
					if ($columnDefinition->isPrimary && !$this->isColumnAPrimaryKey($tableName, $columnName)){
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
				if ($this->isColumnAUniqueIndex($tableName, $columnName)){
					if (!$columnDefinition->isUnique){
						// Needs to be dropped
						$indexName = $this->getUniqueIndexName($tableName, $columnName);
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

			// Get all of the columns currently in the table
			$columnNamesInTable = $this->getAllColumnNamesInTable($tableName);
			foreach($columnNamesInTable as $columnNameInTable){
				if (!in_array($columnNameInTable, $columnNamesDefinedByModel)){
					// Drop it
					$queriesToExecute .= sprintf("ALTER TABLE `%s` DROP COLUMN `%s`;", $tableName, $columnNameInTable);
				}
			}

			$this->getConnection()->multi_query($queriesToExecute);

			// Remove the queries from the result stack
			// Otherwise "commands out of sync" will occur
			while ($result = $this->getConnection()->next_result()){}
		}

		/**
		* Creates a table following a model
		*/
		protected function createNewTable(MySQLModelInterface $model): void{
			$connection = $this->getConnection();
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