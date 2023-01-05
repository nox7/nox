<?php

	namespace Nox\Http;

	/**
	 * Class that houses URL parameters for requests.
	 */
	class RequestParameters
	{
		/** @var RequestParameter[] */
		private array $parameters = [];

		public function addParameter(
			string $name,
			string $value,
		): void{
			$this->parameters[] = new RequestParameter(
				$name,
				$value,
			);
		}

		public function getParameter(string $name): ?RequestParameter{
			foreach($this->parameters as $parameter){
				if ($parameter->name === $name){
					return $parameter;
				}
			}

			return null;
		}
	}
