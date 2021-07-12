<?php

	require_once __DIR__ . "/../models/UsersModel.php";

	use Nox\ORM\Interfaces\ModelInstance;
	use Nox\ORM\ModelClass;
	use Nox\ORM\Interfaces\MySQLModelInterface;

	class User extends ModelClass implements ModelInstance
	{
		public ?int $id = null;
		public string $name;
		public string $email;
		public ?int $creationTimestamp;

		public static function getModel(): MySQLModelInterface{
			return new UsersModel();
		}

		public function __construct(){
			parent::__construct($this);
		}
	}