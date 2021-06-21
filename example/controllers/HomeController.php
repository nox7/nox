<?php

	// No need to require the autoload in the Controller.
	// The controller has the scope of the request.php file

	require_once __DIR__ . "/../attributes/NeverUsable.php";

	use Nox\RenderEngine\Renderer;
	use Nox\Router\Attributes\Route;

	class HomeController extends \Nox\Router\BaseController{

		#[Route("GET", "/")]
		public function homeView(): string{
			return Renderer::renderView("home.html");
		}

		#[Route("GET", "/always-404")]
		#[NeverUsable()]
		public function always404View(): string{
			return "uh-oh";
			// return Renderer::renderView("home.html");
		}
	}