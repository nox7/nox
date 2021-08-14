<?php

	namespace Nox\Http;

	/**
	* An abstraction to handle responses back to the client/router system.
	*/
	class Rewrite{

		public string $path;
		public int $statusCode;

		public function __construct(string $path, int $statusCode = 200){
			$this->path = $path;
			$this->statusCode = $statusCode;
		}

	}
