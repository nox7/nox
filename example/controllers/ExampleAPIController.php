<?php

	// No need to require the autoload in the Controller.
	// The controller has the scope of the request.php file

	require_once __DIR__ . "/../attributes/NeverUsable.php";

	use Nox\RenderEngine\Renderer;
	use Nox\Router\Attributes\Route;
	use Nox\Router\Attributes\RouteBase;

	#[RouteBase("/\/api\/(?<version>v\d)/", true)]
	class ExampleAPIController extends \Nox\Router\BaseController{

		#[Route("GET", "/")]
		public function apiHomeView(): string{
			return "API home view example.";
		}

		#[Route("GET", "/sub-page")]
		public function subView(): string{
			return "API sub page example";
		}
	}