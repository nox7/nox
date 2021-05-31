<?php

	use Nox\ORM\Abyss;
	use Nox\ORM\Interfaces\MySQLModelInterface;

	class User implements \Nox\ORM\Interfaces\ModelInstance
	{
		public ?int $id = null;
		public string $name;
		public string $email;
		public ?int $creationTimestamp;

		public static function getModel(): MySQLModelInterface{
			return new UsersModel();
		}

		public function save(): void{
			$abyss = new Abyss;
			$rowID = $abyss->saveOrCreate($this);
			if ($rowID !== null){
				$this->id = $rowID;
			}
		}

		public function delete(): void{
			$abyss = new Abyss;
			$abyss->deleteRowByPrimaryKey($this);
		}
	}