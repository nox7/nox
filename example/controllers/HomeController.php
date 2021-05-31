<?php

	// No need to require the autoload in the Controller.
	// The controller has the scope of the request.php file

	use Nox\Router\Attributes\Route;

	class HomeController extends \Nox\Router\BaseController{

		#[Route("GET", "/")]
		public function homeView(): string{
			return \Nox\RenderEngine\Renderer::renderView("home.html");
		}
	}