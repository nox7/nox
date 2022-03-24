<?php
	namespace Nox\Http\Attributes;

	use Nox\Router\BaseController;

	require_once __DIR__ . "/ChosenRouteAttribute.php";
	require_once __DIR__ . "/../../Router/BaseController.php";

	/**
	 * @since 1.5.0
	 */
	#[\Attribute(\Attribute::TARGET_METHOD)]
	class UseJSON extends ChosenRouteAttribute {
		public function __construct(){
			header("content-type: application/json; charset=UTF-8");
			BaseController::$outputArraysAsJSON = true;
		}
	}