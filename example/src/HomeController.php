<?php

	use Nox\Http\Request;
	use Nox\RenderEngine\Exceptions\LayoutDoesNotExist;
	use Nox\RenderEngine\Exceptions\ParseError;
	use Nox\RenderEngine\Exceptions\ViewFileDoesNotExist;
	use Nox\RenderEngine\Renderer;
	use Nox\Router\Attributes\Controller;
	use Nox\Router\Attributes\Route;
	use Nox\Router\BaseController;

	#[Controller]
	class HomeController extends BaseController{

		/**
		 * @throws ParseError
		 * @throws ViewFileDoesNotExist
		 * @throws LayoutDoesNotExist
		 */
		#[Route("PUT", "/")]
		public function homeView(Request $request): string{
			return Renderer::renderView("home.html");
		}

		/**
		 * @throws ParseError
		 * @throws ViewFileDoesNotExist
		 * @throws LayoutDoesNotExist
		 */
		#[Route("GET", "/404")]
		public function error404View(Request $request): string{
			return Renderer::renderView("errors/404.html");
		}

		#[Route("GET", "/always-404")]
		#[NeverUsable]
		public function always404View(Request $request): void{
			// Never runs
		}
	}