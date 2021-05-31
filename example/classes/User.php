<?php
	class User implements \Nox\ORM\ModelInstance
	{
		public ?int $id = null;
		public string $name;
		public string $email;
		public ?int $creationTimestamp;

		public static function getModel(): \Nox\ORM\MySQLModelInterface{
			return new UsersModel();
		}

		public function save(): void{
			$abyss = new \Nox\ORM\Abyss;
			$rowID = $abyss->saveOrCreate($this);
			if ($rowID !== null){
				$this->id = $rowID;
			}
		}

		public function delete(): void{
			$abyss = new \Nox\ORM\Abyss;
			$abyss->deleteRowByPrimaryKey($this);
		}
	}