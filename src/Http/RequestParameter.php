<?php

	namespace Nox\Http;

	class RequestParameter
	{
		public function __construct(
			public string $name,
			public string $value,
		){}
	}
