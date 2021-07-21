<?php
	namespace Nox\Router;

	class DynamicRoute{
		public function __construct(
			public string $requestMethod,
			public string $requestPath,
			public bool $isRegex,
			public \Closure $onRender,

			/**
			 * This function is called before onRender. It must return a DynamicRouteResponse
			 * that defines whether or not this route can pass.
			 */
			public ?\Closure $onRouteCheck = null,
		){
			// Force path and method to be lowercase
			$this->requestPath = strtolower($this->requestPath);
			$this->requestMethod = strtolower($this->requestMethod);
		}
	}