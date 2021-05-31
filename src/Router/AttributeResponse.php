<?php
	namespace Nox\Router;

	class AttributeResponse{
		public function __construct(
			public bool $isRouteUsable,
			public ?int $responseCode = null,
			public ?string $newRequestPath = null
		){

		}
	}