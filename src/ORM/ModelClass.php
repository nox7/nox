<?php

	namespace Nox\ORM;

	use Nox\ORM\Exceptions\NoPrimaryKey;
	use \Nox\ORM\Interfaces\ModelInstance;
	use Nox\ORM\Interfaces\MySQLModelInterface;

	class ModelClass implements ModelInstance{

		/**
		 * Fetches a ModelClass by the primary key
		 */
		public static function fetch(mixed $primaryKey): ModelClass|null{
			$abyss = new Abyss();
			$thisModel = static::getModel();
			$primaryKeyClassPropertyName = $abyss->getPrimaryKey($thisModel);
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
			ColumnQuery $columnQuery = null,
			ResultOrder $resultOrder = null,
			Pager $pager = null,
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
		 * Runs a large-scale UPDATE query to save all of the
		 * ModelClass instances by their primary key
		 */
		public static function saveAll(array $modelClasses): void{
			$abyss = new Abyss();
			$abyss->saveOrCreateAll($modelClasses);
		}

		/**
		 * @throws Exceptions\ObjectMissingModelProperty
		 */
		public function __construct(ModelInstance $modelClass){
			$abyss = new Abyss();
			$abyss->prefillPropertiesWithColumnDefaults($modelClass);
		}

		/**
		 * Attempts to save a class instance to its corresponding model. Will then
		 * find the name of the primary key, find the class name's representation of it,
		 * then set the class' primary key property value.
		 */
		public function save():void{
			$abyss = new Abyss();
			$rowID = $abyss->saveOrCreate($this);
			if ($rowID !== null){
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
			$abyss = new Abyss;
			$abyss->deleteRowByPrimaryKey($this);
		}

		public static function getModel(): MySQLModelInterface
		{
			// TODO: Implement getModel() method.
		}
	}