<?php

	namespace Users;

	use Nox\ORM\Interfaces\ModelInstance;
	use Nox\ORM\Interfaces\MySQLModelInterface;
	use Nox\ORM\ModelClass;

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