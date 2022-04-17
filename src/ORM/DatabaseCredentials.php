<?php
	namespace Nox\ORM;

	class DatabaseCredentials{
		public function __construct(
			public string $host,
			public string $username,
			public string $password,
			public string $database,
			public int $port,
		){}
	}