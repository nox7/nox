<?php

	// No need to require the autoload in the Controller.
	// The controller has the scope of the request.php file

	use Nox\Router\Attributes\Route;
	use Nox\Router\Attributes\RouteBase;
	use Nox\Router\BaseController;

	#[RouteBase("/\/api\/(?<version>v\d)/", true)]
	class ExampleAPIController extends BaseController{

		#[Route("GET", "/")]
		public function apiHomeView(): string{
			return "API home view example.";
		}

		#[Route("GET", "/sub-page")]
		public function subView(): string{
			return "API sub page example";
		}
	}