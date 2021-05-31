<?php

	namespace Nox\Http;

	/**
	* An abstraction to handle responses back to the client/router system.
	*/
	class Redirect{

		public string $path;
		public int $statusCode;

		public function __construct(string $path, int $statusCode = 302){
			$this->path = $path;
			$this->statusCode = $statusCode;
		}

	}
