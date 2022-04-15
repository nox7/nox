<?php
	namespace Nox\ORM;

	use Nox\ORM\Exceptions\NoColumnWithPropertyName;

	class MySQLModel{

		/**
		 * @param string $propertyName
		 * @return string
		 * @throws NoColumnWithPropertyName
		 */
		public static function getColumnName(string $propertyName): string{
			/** @var \Nox\ORM\Interfaces\MySQLModelInterface $model */
			$model = new static();

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
	}