<?php
	namespace Nox\Http\Attributes;

	require_once __DIR__ . "/../Request.php";
	require_once __DIR__ . "/../../Router/BaseController.php";
	require_once __DIR__ . "/ChosenRouteAttribute.php";

	use Nox\Http\Request;
	use Nox\Router\BaseController;

	/**
	 * @since 1.5.0
	 */
	#[\Attribute(\Attribute::TARGET_METHOD)]
	class ProcessRequestBody extends ChosenRouteAttribute {
		public function __construct(){
			// First, try and just reference the POST data that PHP processes internally.
			// Because php://input is blank if this is a POST request that PHP already handled.
			if (strtolower($_SERVER['REQUEST_METHOD']) === "post" && !empty($_POST)){
				BaseController::$requestPayload = &$_POST;
			}else{
				$request = new Request();
				BaseController::$requestPayload = $request->processRequestBody();
			}
		}
	}