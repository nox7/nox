<?php

	namespace Nox\Router\Attributes;

	#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
	class RouteBase{

		/**
		* @param string $uri The base URI this route base will match for
		* @param bool $isRegex (Optional) Flag for whether the URI base is a regular expression
		*/
		public function __construct(
			public string $uri,
			public bool $isRegex = false
		){
		}
	}
