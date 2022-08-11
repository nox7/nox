<?php
	namespace Nox\Router;

	use ReflectionClass;

	class RoutableController{
		public function __construct(
			public ReflectionClass $reflectionClass,
			public null | string $baselessRequestPath,
		){}
	}