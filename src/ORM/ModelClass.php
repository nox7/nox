<?php

	namespace Nox\ORM;

	use Nox\ORM\Exceptions\NoColumnWithPropertyName;
	use Nox\ORM\Exceptions\NoPrimaryKey;
	use Nox\ORM\Interfaces\ModelInstance;
	use Nox\ORM\Interfaces\MySQLModelInterface;

	class ModelClass implements ModelInstance{

		private ModelInstance $childInstance;

		/**
		 * Fetches a ModelClass by the primary key
		 */
		public static function fetch(mixed $primaryKey): ModelClass|null{
			$thisModel = static::getModel();
			$abyss = new Abyss();
			return $abyss->fetchInstanceByModelPrimaryKey(
				model: $thisModel,
				keyValue: $primaryKey,
			);
		}

		/**
		 * Queries all instances of ModelClass that meet the provided query criteria from
		 * the provided parameters. Will always return an array, but the array may be empty.
		 */
		public static function query(
			ColumnQuery|null $columnQuery = null,
			ResultOrder|null $resultOrder = null,
			Pager|null $pager = null,
		): array {
			$abyss = new Abyss();
			return $abyss->fetchInstances(
				model: static::getModel(),
				columnQuery: $columnQuery,
				resultOrder: $resultOrder,
				pager: $pager,
			);
		}

		/**
		 * Performs a query on the instances of the ModelClass and returns a single matching ModelClass
		 * that meets the criteria provided, or null.
		 */
		public static function queryOne(
			ColumnQuery|null $columnQuery = null,
			ResultOrder|null $resultOrder = null,
			Pager|null $pager = null,
		): ?ModelClass {
			/** @var ModelClass[] $modelClasses */
			$modelClasses = self::query(
				columnQuery: $columnQuery,
				resultOrder: $resultOrder,
				pager: $pager,
			);

			if (empty($modelClasses)){
				return null;
			}else{
				return $modelClasses[0];
			}
		}

		/**
		 * Utilizes the MySQL COUNT() function to quickly
		 * fetch the number of results returned on this instance
		 * and provided $columnQuery
		 */
		public static function count(
			ColumnQuery|null $columnQuery = null,
		): int {
			$model = static::getModel();
			$abyss = new Abyss();

			$whereClause = "";
			$preparedStatementBindFlags = "";
			$boundValues = [];
			if ($columnQuery !== null && !empty($columnQuery->whereClauses)){
				list($whereClause,$preparedStatementBindFlags, $boundValues) = $abyss->buildWhereClause($model, $columnQuery);
			}

			$query = sprintf(
				"SELECT COUNT(*) AS totalCount FROM `%s` %s",
				$model->getName(),
				$whereClause,
			);
			$statement = $abyss->getConnectionToDatabase($model->getDatabaseName())->prepare($query);
			if ($columnQuery !== null && !empty($columnQuery->whereClauses)) {
				if (!empty($preparedStatementBindFlags)) {
					$statement->bind_param($preparedStatementBindFlags, ...$boundValues);
				}
			}
			$statement->execute();
			$result = $statement->get_result();

			$row = $result->fetch_assoc();
			return (int) $row['totalCount'];
		}

		/**
		 * Runs a large-scale UPDATE query to save all the
		 * ModelClass instances by their primary key. The model classes provided
		 * should be homogenous.
		 */
		public static function saveAll(array $modelClasses): void{
			if (!empty($modelClasses)) {
				$abyss = new Abyss();
				$abyss->saveOrCreateAll($modelClasses);
			}
		}

		/**
		 * @throws Exceptions\ObjectMissingModelProperty
		 */
		public function __construct(ModelInstance $modelClass){
			$abyss = new Abyss();
			$abyss->prefillPropertiesWithColumnDefaults($modelClass);
			$this->childInstance = $modelClass;
		}

		/**
		 * Attempts to save a class instance to its corresponding model. Will then
		 * find the name of the primary key, find the class name's representation of it,
		 * then set the class' primary key property value.
		 */
		public function save(): void{
			$abyss = new Abyss();
			$rowID = $abyss->saveOrCreate($this);

			// 0 Here would indicate a column that doesn't generate an automatically incremented ID
			if ($rowID !== null && $rowID !== 0){
				$primaryKeyClassPropertyName = $abyss->getPrimaryKey($this::getModel());
				if ($primaryKeyClassPropertyName) {
					$this->$primaryKeyClassPropertyName = $rowID;
				}
			}
		}

		/**
		 * Deletes a singular class model instance
		 * @throws NoPrimaryKey
		 */
		public function delete():void{
			$abyss = new Abyss();
			$abyss->deleteRowByPrimaryKey($this);
		}

		/**
		 * Fetches the MySQL column named from the PHP property defined for this ModelInstance's Model
		 * @throws NoColumnWithPropertyName
		 */
		public function getColumnName(string $propertyName): string{
			$model = $this->childInstance::getModel();

			/** @var ColumnDefinition[] $columns */
			$columns = $model->getColumns();

			foreach($columns as $column){
				if ($column->classPropertyName === $propertyName){
					return $column->name;
				}
			}

			$modelClass = $model::class;
			throw new NoColumnWithPropertyName("No column with property name {$propertyName} in {$modelClass}.");
		}

		public static function getModel(): MySQLModelInterface
		{
			// TODO: Implement getModel() method.
		}
	}