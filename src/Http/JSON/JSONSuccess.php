<?php
	namespace Nox\Http\JSON;

	require_once __DIR__ . "/../Interfaces/ArrayLike.php";

	use Nox\Http\Interfaces\ArrayLike;

	class JSONSuccess extends JSONResult implements ArrayLike{

		private array $data = [
			"status"=>1,
		];

		public function __construct(array $additionalData = []){
			$this->data = array_merge($this->data, $additionalData);
		}

		public function toArray(): array
		{
			return $this->data;
		}
	}