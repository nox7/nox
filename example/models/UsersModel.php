<?php

	require_once __DIR__ . "/../classes/User.php";

	use \Nox\ORM\ColumnDefinition;
	use \Nox\ORM\Interfaces\MySQLModelInterface;
	use \Nox\ORM\MySQLDataTypes\Integer;
	use \Nox\ORM\MySQLDataTypes\VariableCharacter;

	class UsersModel implements MySQLModelInterface {

		/**
		 * The name of this Model in the MySQL database as a table
		 */
		private string $mysqlTableName = "users";

		/**
		 * The string name of the class this model represents and can instantiate
		 */
		private string $representingClassName = User::class;

		public function getName(): string{
			return $this->mysqlTableName;
		}

		public function getInstanceName(): string{
			return $this->representingClassName;
		}

		public function getColumns(): array{
			return [
				new ColumnDefinition(
					name:"id",
					classPropertyName: "id",
					dataType : new Integer(),
					defaultValue: 0,
					autoIncrement: true,
					isPrimary: true,
					isNull:false,
				),
				new ColumnDefinition(
					name:"name",
					classPropertyName: "name",
					dataType : new VariableCharacter(65),
					defaultValue: "",
				),
				new ColumnDefinition(
					name:"email",
					classPropertyName: "email",
					dataType : new VariableCharacter(65),
					defaultValue:"",
				),
				new ColumnDefinition(
					name:"creation_timestamp",
					classPropertyName: "creationTimestamp",
					dataType : new Integer(),
				),
			];
		}
	}
