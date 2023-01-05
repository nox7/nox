<?php
	namespace Nox\Http\Attributes;

	use Attribute;
	use Nox\Http\Request;
	use Nox\Router\BaseController;

	/**
	 * @since 1.5.0
	 */
	#[Attribute(Attribute::TARGET_METHOD)]
	class ProcessRequestBody extends ChosenRouteAttribute {
		public function __construct(){
			// Get the Router
			$router = BaseController::$noxInstance->router;

			// Get the current Request object
			$request = $router->currentRequest;

			// Process the request body
			$request->processRequestBody();
		}
	}