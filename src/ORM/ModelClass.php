<?php

	namespace Nox\ORM;

	use Nox\ORM\Exceptions\NoPrimaryKey;
	use \Nox\ORM\Interfaces\ModelInstance;
	use Nox\ORM\Interfaces\MySQLModelInterface;

	class ModelClass implements ModelInstance{

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