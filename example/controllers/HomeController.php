<?php

	// No need to require the autoload in the Controller.
	// The controller has the scope of the request.php file

	require_once __DIR__ . "/../attributes/NeverUsable.php";

	use Nox\RenderEngine\Exceptions\LayoutDoesNotExist;
	use Nox\RenderEngine\Exceptions\ViewFileDoesNotExist;
	use Nox\RenderEngine\Renderer;
	use Nox\Router\Attributes\Controller;
	use Nox\Router\Attributes\Route;
	use Nox\RenderEngine\Exceptions\ParseError;

	#[Controller]
	class HomeController extends \Nox\Router\BaseController{

		/**
		 * @throws ParseError
		 * @throws ViewFileDoesNotExist
		 * @throws LayoutDoesNotExist
		 */
		#[Route("GET", "/")]
		public function homeView(): string{
			return Renderer::renderView("home.html");
		}

		/**
		 * @throws ParseError
		 * @throws ViewFileDoesNotExist
		 * @throws LayoutDoesNotExist
		 */
		#[Route("GET", "/404")]
		public function error404View(): string{
			return Renderer::renderView("errors/404.html");
		}

		/**
		 * @throws ParseError
		 * @throws ViewFileDoesNotExist
		 * @throws LayoutDoesNotExist
		 */
		#[Route("GET", "/always-404")]
		#[NeverUsable()]
		public function always404View(): string{
			return "uh-oh";
			// return Renderer::renderView("home.html");
		}
	}