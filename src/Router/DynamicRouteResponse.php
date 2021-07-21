<?php
	namespace Nox\Router;

	class DynamicRouteResponse{
		public function __construct(
			public bool $isRouteUsable,
			public ?int $responseCode = null,
			public ?string $newRequestPath = null
		){

		}
	}