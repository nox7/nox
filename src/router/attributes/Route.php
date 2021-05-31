<?php
	#[Attribute(Attribute::TARGET_METHOD)]
	class Route{

		public string $method;
		public string $uri;
		public bool $isRegex;

		/**
		* @param string $method The HTTP method for this route
		* @param string $uri The URI this route will match
		* @param bool $isRegex (Optional) Flag for whether the URI is a regular expression
		*/
		public function __construct(string $method, string $uri, bool $isRegex = false){
			$this->method = $method;
			$this->uri = $uri;
			$this->isRegex = $isRegex;
		}
	}
